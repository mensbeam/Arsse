<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\REST\Miniflux\V1;
use JKingWeb\Arsse\REST\Miniflux\ErrorResponse;
use JKingWeb\Arsse\User\ExceptionConflict;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse as Response;
use Laminas\Diactoros\Response\EmptyResponse;
use JKingWeb\Arsse\Test\Result;

/** @covers \JKingWeb\Arsse\REST\Miniflux\V1<extended> */
class TestV1 extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const NOW = "2020-12-09T22:35:10.023419Z";

    protected $h;
    protected $transaction;
    protected $token = "Tk2o9YubmZIL2fm2w8Z4KlDEQJz532fNSOcTG0s2_xc=";
    protected $users = [
        [
            'id'                      => 1,
            'username'                => "john.doe@example.com",
            'is_admin'                => true,
            'theme'                   => "custom",
            'language'                => "fr_CA",
            'timezone'                => "Asia/Gaza",
            'entry_sorting_direction' => "asc",
            'entries_per_page'        => 200,
            'keyboard_shortcuts'      => false,
            'show_reading_time'       => false,
            'last_login_at'           => self::NOW,
            'entry_swipe'             => false,
            'extra'                   => [
                'custom_css' => "p {}",
            ],
        ],
        [
            'id'                      => 2,
            'username'                => "jane.doe@example.com",
            'is_admin'                => false,
            'theme'                   => "light_serif",
            'language'                => "en_US",
            'timezone'                => "UTC",
            'entry_sorting_direction' => "desc",
            'entries_per_page'        => 100,
            'keyboard_shortcuts'      => true,
            'show_reading_time'       => true,
            'last_login_at'           => self::NOW,
            'entry_swipe'             => true,
            'extra'                   => [
                'custom_css' => "",
            ],
        ]
    ];

    protected function req(string $method, string $target, $data = "", array $headers = [], ?string $user = "john.doe@example.com", bool $body = true): ResponseInterface {
        $prefix = "/v1";
        $url = $prefix.$target;
        if ($body) {
            $params = [];
        } else {
            $params = $data;
            $data = [];
        }
        $req = $this->serverRequest($method, $url, $prefix, $headers, [], $data, "application/json", $params, $user);
        return $this->h->dispatch($req);
    }

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock user manager; we use a PHPUnitmock because Phake for reasons unknown is unable to mock the User class correctly, sometimes
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['num' => 42, 'admin' => false, 'root_folder_name' => null]);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        $this->transaction = \Phake::mock(Transaction::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($this->transaction);
        //initialize a handler
        $this->h = new V1();
    }

    public function tearDown(): void {
        self::clearData();
    }

    protected function v($value) {
        return $value;
    }

    /** @dataProvider provideAuthResponses */
    public function testAuthenticateAUser($token, bool $auth, bool $success): void {
        $exp = $success ? new EmptyResponse(404) : new ErrorResponse("401", 401);
        $user = "john.doe@example.com";
        if ($token !== null) {
            $headers = ['X-Auth-Token' => $token];
        } else {
            $headers = [];
        }
        Arsse::$user->id = null;
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("miniflux.login", $this->token)->thenReturn(['user' => $user]);
        $this->assertMessage($exp, $this->req("GET", "/", "", $headers, $auth ? "john.doe@example.com" : null));
        $this->assertSame($success ? $user : null, Arsse::$user->id);
    }

    public function provideAuthResponses(): iterable {
        return [
            [null,                     false, false],
            [null,                     true,  true],
            [$this->token,             false, true],
            [[$this->token, "BOGUS"],  false, true],
            ["",                       true,  true],
            [["", "BOGUS"],            true,  true],
            ["NOT A TOKEN",            false, false],
            ["NOT A TOKEN",            true,  false],
            [["BOGUS", $this->token],  false, false],
            [["", $this->token],       false, false],
        ];
    }

    /** @dataProvider provideInvalidPaths */
    public function testRespondToInvalidPaths($path, $method, $code, $allow = null): void {
        $exp = new EmptyResponse($code, $allow ? ['Allow' => $allow] : []);
        $this->assertMessage($exp, $this->req($method, $path));
    }

    public function provideInvalidPaths(): array {
        return [
            ["/",                  "GET",     404],
            ["/",                  "OPTIONS", 404],
            ["/me",                "POST",    405, "GET"],
            ["/me/",               "GET",     404],
        ];
    }

    /** @dataProvider provideOptionsRequests */
    public function testRespondToOptionsRequests(string $url, string $allow, string $accept): void {
        $exp = new EmptyResponse(204, [
            'Allow'  => $allow,
            'Accept' => $accept,
        ]);
        $this->assertMessage($exp, $this->req("OPTIONS", $url));
    }

    public function provideOptionsRequests(): array {
        return [
            ["/feeds",          "HEAD, GET, POST",          "application/json"],
            ["/feeds/2112",     "HEAD, GET, PUT, DELETE",   "application/json"],
            ["/me",             "HEAD, GET",                "application/json"],
            ["/users/someone",  "HEAD, GET",                "application/json"],
            ["/import",         "POST",                     "application/xml, text/xml, text/x-opml"],
        ];
    }

    public function testRejectBadlyTypedData(): void {
        $exp = new ErrorResponse(["InvalidInputType", 'field' => "url", 'expected' => "string", 'actual' => "integer"], 400);
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => 2112]));
    }

    public function testDiscoverFeeds(): void {
        $exp = new Response([
            ['title' => "Feed", 'type' => "rss", 'url' => "http://localhost:8000/Feed/Discovery/Feed"],
            ['title' => "Feed", 'type' => "rss", 'url' => "http://localhost:8000/Feed/Discovery/Missing"],
        ]);
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => "http://localhost:8000/Feed/Discovery/Valid"]));
        $exp = new Response([]);
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => "http://localhost:8000/Feed/Discovery/Invalid"]));
        $exp = new ErrorResponse("Fetch404", 500);
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => "http://localhost:8000/Feed/Discovery/Missing"]));
    }

    /** @dataProvider provideUserQueries */
    public function testQueryUsers(bool $admin, string $route, ResponseInterface $exp): void {
        $u = [
            ['num'=> 1, 'admin' => true,  'theme' => "custom", 'lang' => "fr_CA", 'tz' => "Asia/Gaza", 'sort_asc' => true, 'page_size' => 200,  'shortcuts' => false, 'reading_time' => false, 'swipe' => false, 'stylesheet' => "p {}"],
            ['num'=> 2, 'admin' => false, 'theme' => null,     'lang' => null,    'tz' => null,        'sort_asc' => null, 'page_size' => null, 'shortcuts' => null,  'reading_time' => null,  'swipe' => null,  'stylesheet' => null],
            new ExceptionConflict("doesNotExist"),
        ];
        $user = $admin ? "john.doe@example.com" : "jane.doe@example.com";
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("list")->willReturn(["john.doe@example.com", "jane.doe@example.com", "admin@example.com"]);
        Arsse::$user->method("propertiesGet")->willReturnCallback(function(string $user, bool $includeLerge = true) use ($u) {
            if ($user === "john.doe@example.com") {
                return $u[0];
            } elseif ($user === "jane.doe@example.com") {
                return $u[1];
            } else {
                throw $u[2];
            }
        });
        Arsse::$user->method("lookup")->willReturnCallback(function(int $num) use ($u) {
            if ($num === 1) {
                return "john.doe@example.com";
            } elseif ($num === 2) {
                return "jane.doe@example.com";
            } else {
                throw $u[2];
            }
        });
        $this->h = $this->createPartialMock(V1::class, ["now"]);
        $this->h->method("now")->willReturn(Date::normalize(self::NOW));
        $this->assertMessage($exp, $this->req("GET", $route, "", [], $user));
    }

    public function provideUserQueries(): iterable {
        self::clearData();
        return [
            [true,  "/users",                      new Response($this->users)],
            [true,  "/me",                         new Response($this->users[0])],
            [true,  "/users/john.doe@example.com", new Response($this->users[0])],
            [true,  "/users/1",                    new Response($this->users[0])],
            [true,  "/users/jane.doe@example.com", new Response($this->users[1])],
            [true,  "/users/2",                    new Response($this->users[1])],
            [true,  "/users/jack.doe@example.com", new ErrorResponse("404", 404)],
            [true,  "/users/47",                   new ErrorResponse("404", 404)],
            [false, "/users",                      new ErrorResponse("403", 403)],
            [false, "/me",                         new Response($this->users[1])],
            [false, "/users/john.doe@example.com", new ErrorResponse("403", 403)],
            [false, "/users/1",                    new ErrorResponse("403", 403)],
            [false, "/users/jane.doe@example.com", new ErrorResponse("403", 403)],
            [false, "/users/2",                    new ErrorResponse("403", 403)],
            [false, "/users/jack.doe@example.com", new ErrorResponse("403", 403)],
            [false, "/users/47",                   new ErrorResponse("403", 403)],
        ];
    }

    public function testListCategories(): void {
        \Phake::when(Arsse::$db)->folderList->thenReturn(new Result($this->v([
            ['id' => 1,  'name' => "Science"],
            ['id' => 20, 'name' => "Technology"],
        ])));
        $exp = new Response([
            ['id' => 1,  'title' => "All",        'user_id' => 42],
            ['id' => 2,  'title' => "Science",    'user_id' => 42],
            ['id' => 21, 'title' => "Technology", 'user_id' => 42],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories"));
        \Phake::verify(Arsse::$db)->folderList("john.doe@example.com", null, false);
        // run test again with a renamed root folder
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['num' => 47, 'admin' => false, 'root_folder_name' => "Uncategorized"]);
        $exp = new Response([
            ['id' => 1,  'title' => "Uncategorized", 'user_id' => 47],
            ['id' => 2,  'title' => "Science",       'user_id' => 47],
            ['id' => 21, 'title' => "Technology",    'user_id' => 47],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories"));
    }

    /** @dataProvider provideCategoryAdditions */
    public function testAddACategory($title, ResponseInterface $exp): void {
        if (!strlen((string) $title)) {
            \Phake::when(Arsse::$db)->folderAdd->thenThrow(new ExceptionInput("missing"));
        } elseif (!strlen(trim((string) $title))) {
            \Phake::when(Arsse::$db)->folderAdd->thenThrow(new ExceptionInput("whitespace"));
        } elseif ($title === "Duplicate") {
            \Phake::when(Arsse::$db)->folderAdd->thenThrow(new ExceptionInput("constraintViolation"));
        } else {
            \Phake::when(Arsse::$db)->folderAdd->thenReturn(2111);
        }
        $this->assertMessage($exp, $this->req("POST", "/categories", ['title' => $title]));
    }

    public function provideCategoryAdditions(): iterable {
        return [
            ["New",       new Response(['id' => 2112, 'title' => "New", 'user_id' => 42])],
            ["Duplicate", new ErrorResponse(["DuplicateCategory", 'title' => "Duplicate"], 500)],
            ["",          new ErrorResponse(["InvalidCategory", 'title' => ""], 500)],
            [" ",         new ErrorResponse(["InvalidCategory", 'title' => " "], 500)],
            [false,       new ErrorResponse(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 400)],
        ];
    }
}
