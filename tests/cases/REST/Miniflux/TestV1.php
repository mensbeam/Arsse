<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\REST\Miniflux\V1;
use JKingWeb\Arsse\REST\Miniflux\ErrorResponse;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\ExceptionInput as UserExceptionInput;
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
            'last_login_at'           => self::NOW,
            'is_admin'                => true,
            'theme'                   => "custom",
            'language'                => "fr_CA",
            'timezone'                => "Asia/Gaza",
            'entry_sorting_direction' => "asc",
            'entries_per_page'        => 200,
            'keyboard_shortcuts'      => false,
            'show_reading_time'       => false,
            'entry_swipe'             => false,
            'extra'                   => [
                'custom_css' => "p {}",
            ],
        ],
        [
            'id'                      => 2,
            'username'                => "jane.doe@example.com",
            'last_login_at'           => self::NOW,
            'is_admin'                => false,
            'theme'                   => "light_serif",
            'language'                => "en_US",
            'timezone'                => "UTC",
            'entry_sorting_direction' => "desc",
            'entries_per_page'        => 100,
            'keyboard_shortcuts'      => true,
            'show_reading_time'       => true,
            'entry_swipe'             => true,
            'extra'                   => [
                'custom_css' => "",
            ],
        ],
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
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        $this->transaction = \Phake::mock(Transaction::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($this->transaction);
        // create a mock user manager; we use a PHPUnitmock because Phake for reasons unknown is unable to mock the User class correctly, sometimes
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['num' => 42, 'admin' => false, 'root_folder_name' => null]);
        Arsse::$user->method("begin")->willReturn($this->transaction);
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
        $exp = new ErrorResponse(["InvalidInputType", 'field' => "url", 'expected' => "string", 'actual' => "integer"], 422);
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => 2112]));
    }

    /** @dataProvider provideDiscoveries */
    public function testDiscoverFeeds($in, ResponseInterface $exp): void {
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => $in]));
    }

    public function provideDiscoveries(): iterable {
        self::clearData();
        $discovered = [
            ['title' => "Feed", 'type' => "rss", 'url' => "http://localhost:8000/Feed/Discovery/Feed"],
            ['title' => "Feed", 'type' => "rss", 'url' => "http://localhost:8000/Feed/Discovery/Missing"],
        ];
        return [
            ["http://localhost:8000/Feed/Discovery/Valid",   new Response($discovered)],
            ["http://localhost:8000/Feed/Discovery/Invalid", new Response([])],
            ["http://localhost:8000/Feed/Discovery/Missing", new ErrorResponse("Fetch404", 502)],
            [1,                                              new ErrorResponse(["InvalidInputType", 'field' => "url", 'expected' => "string", 'actual' => "integer"], 422)],
            ["Not a URL",                                    new ErrorResponse(["InvalidInputValue", 'field' => "url"], 422)],
            [null,                                           new ErrorResponse(["MissingInputValue", 'field' => "url"], 422)],
        ];
    }

    /** @dataProvider provideUserQueries */
    public function testQueryUsers(bool $admin, string $route, ResponseInterface $exp): void {
        $u = [
            ['num' => 1, 'admin' => true,  'theme' => "custom", 'lang' => "fr_CA", 'tz' => "Asia/Gaza", 'sort_asc' => true, 'page_size' => 200,  'shortcuts' => false, 'reading_time' => false, 'swipe' => false, 'stylesheet' => "p {}"],
            ['num' => 2, 'admin' => false, 'theme' => null,     'lang' => null,    'tz' => null,        'sort_asc' => null, 'page_size' => null, 'shortcuts' => null,  'reading_time' => null,  'swipe' => null,  'stylesheet' => null],
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

    /** @dataProvider provideUserModifications */
    public function testModifyAUser(bool $admin, string $url, array $body, $in1, $out1, $in2, $out2, $in3, $out3, ResponseInterface $exp): void {
        $this->h = $this->createPartialMock(V1::class, ["now"]);
        $this->h->method("now")->willReturn(Date::normalize(self::NOW));
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("begin")->willReturn($this->transaction);
        Arsse::$user->method("propertiesGet")->willReturnCallback(function(string $u, bool $includeLarge) use ($admin) {
            if ($u === "john.doe@example.com" || $u === "ook") {
                return ['num' => 2, 'admin' => $admin];
            } else {
                return ['num' => 1, 'admin' => true];
            }
        });
        Arsse::$user->method("lookup")->willReturnCallback(function(int $u) {
            if ($u === 1) {
                return "jane.doe@example.com";
            } elseif ($u === 2) {
                return "john.doe@example.com";
            } else {
                throw new ExceptionConflict("doesNotExist");
            }
        });
        if ($out1 instanceof \Exception) {
            Arsse::$user->method("rename")->willThrowException($out1);
        } else {
            Arsse::$user->method("rename")->willReturn($out1 ?? false);
        }
        if ($out2 instanceof \Exception) {
            Arsse::$user->method("passwordSet")->willThrowException($out2);
        } else {
            Arsse::$user->method("passwordSet")->willReturn($out2 ?? "");
        }
        if ($out3 instanceof \Exception) {
            Arsse::$user->method("propertiesSet")->willThrowException($out3);
        } else {
            Arsse::$user->method("propertiesSet")->willReturn($out3 ?? []);
        }
        $user = $url === "/users/1" ? "jane.doe@example.com" : "john.doe@example.com";
        if ($in1 === null) {
            Arsse::$user->expects($this->exactly(0))->method("rename");
        } else {
            Arsse::$user->expects($this->exactly(1))->method("rename")->with($user, $in1);
            $user = $in1;
        }
        if ($in2 === null) {
            Arsse::$user->expects($this->exactly(0))->method("passwordSet");
        } else {
            Arsse::$user->expects($this->exactly(1))->method("passwordSet")->with($user, $in2);
        }
        if ($in3 === null) {
            Arsse::$user->expects($this->exactly(0))->method("propertiesSet");
        } else {
            Arsse::$user->expects($this->exactly(1))->method("propertiesSet")->with($user, $in3);
        }
        $this->assertMessage($exp, $this->req("PUT", $url, $body));
    }

    public function provideUserModifications(): iterable {
        $out1 = ['num' => 2, 'admin' => false];
        $out2 = ['num' => 1, 'admin' => false];
        $resp1 = array_merge($this->users[1], ['username' => "john.doe@example.com"]);
        $resp2 = array_merge($this->users[1], ['id' => 1, 'is_admin' => true]);
        return [
            [false, "/users/1", ['is_admin' => 0],                          null,  null,                                      null,  null,  null,                  null,                                            new ErrorResponse(["InvalidInputType", 'field' => "is_admin", 'expected' => "boolean", 'actual' => "integer"], 422)],
            [false, "/users/1", ['entry_sorting_direction' => "bad"],       null,  null,                                      null,  null,  null,                  null,                                            new ErrorResponse(["InvalidInputValue", 'field' => "entry_sorting_direction"], 422)],
            [false, "/users/1", ['theme' => "stark"],                       null,  null,                                      null,  null,  null,                  null,                                            new ErrorResponse("403", 403)],
            [false, "/users/2", ['is_admin' => true],                       null,  null,                                      null,  null,  null,                  null,                                            new ErrorResponse("InvalidElevation", 403)],
            [false, "/users/2", ['language' => "fr_CA"],                    null,  null,                                      null,  null,  ['lang' => "fr_CA"],   $out1,                                           new Response($resp1)],
            [false, "/users/2", ['entry_sorting_direction' => "asc"],       null,  null,                                      null,  null,  ['sort_asc' => true],  $out1,                                           new Response($resp1)],
            [false, "/users/2", ['entry_sorting_direction' => "desc"],      null,  null,                                      null,  null,  ['sort_asc' => false], $out1,                                           new Response($resp1)],
            [false, "/users/2", ['entries_per_page' => -1],                 null,  null,                                      null,  null,  ['page_size' => -1],   new UserExceptionInput("invalidNonZeroInteger"), new ErrorResponse(["InvalidInputValue", 'field' => "entries_per_page"], 422)],
            [false, "/users/2", ['timezone' => "Ook"],                      null,  null,                                      null,  null,  ['tz' => "Ook"],       new UserExceptionInput("invalidTimezone"),       new ErrorResponse(["InvalidInputValue", 'field' => "timezone"], 422)],
            [false, "/users/2", ['username' => "j:k"],                      "j:k", new UserExceptionInput("invalidUsername"), null,  null,  null,                  null,                                            new ErrorResponse(["InvalidInputValue", 'field' => "username"], 422)],
            [false, "/users/2", ['username' => "ook"],                      "ook", new ExceptionConflict("alreadyExists"),    null,  null,  null,                  null,                                            new ErrorResponse(["DuplicateUser", 'user' => "ook"], 409)],
            [false, "/users/2", ['password' => "ook"],                      null,  null,                                      "ook", "ook", null,                  null,                                            new Response(array_merge($resp1, ['password' => "ook"]))],
            [false, "/users/2", ['username' => "ook", 'password' => "ook"], "ook", true,                                      "ook", "ook", null,                  null,                                            new Response(array_merge($resp1, ['username' => "ook", 'password' => "ook"]))],
            [true,  "/users/1", ['theme' => "stark"],                       null,  null,                                      null,  null,  ['theme' => "stark"],  $out2,                                           new Response($resp2)],
            [true,  "/users/3", ['theme' => "stark"],                       null,  null,                                      null,  null,  null,                  null,                                            new ErrorResponse("404", 404)],
        ];
    }

    /** @dataProvider provideUserAdditions */
    public function testAddAUser(array $body, $in1, $out1, $in2, $out2, ResponseInterface $exp): void {
        $this->h = $this->createPartialMock(V1::class, ["now"]);
        $this->h->method("now")->willReturn(Date::normalize(self::NOW));
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("begin")->willReturn($this->transaction);
        Arsse::$user->method("propertiesGet")->willReturnCallback(function(string $u, bool $includeLarge) {
            if ($u === "john.doe@example.com") {
                return ['num' => 1, 'admin' => true];
            } else {
                return ['num' => 2, 'admin' => false];
            }
        });
        if ($out1 instanceof \Exception) {
            Arsse::$user->method("add")->willThrowException($out1);
        } else {
            Arsse::$user->method("add")->willReturn($in1[1] ?? "");
        }
        if ($out2 instanceof \Exception) {
            Arsse::$user->method("propertiesSet")->willThrowException($out2);
        } else {
            Arsse::$user->method("propertiesSet")->willReturn($out2 ?? []);
        }
        if ($in1 === null) {
            Arsse::$user->expects($this->exactly(0))->method("add");
        } else {
            Arsse::$user->expects($this->exactly(1))->method("add")->with(...($in1 ?? []));
        }
        if ($in2 === null) {
            Arsse::$user->expects($this->exactly(0))->method("propertiesSet");
        } else {
            Arsse::$user->expects($this->exactly(1))->method("propertiesSet")->with($body['username'], $in2);
        }
        $this->assertMessage($exp, $this->req("POST", "/users", $body));
    }

    public function provideUserAdditions(): iterable {
        $resp1 = array_merge($this->users[1], ['username' => "ook", 'password' => "eek"]);
        return [
            [[],                                                                   null,           null,                                      null,                   null,                                            new ErrorResponse(["MissingInputValue", 'field' => "username"], 422)],
            [['username' => "ook"],                                                null,           null,                                      null,                   null,                                            new ErrorResponse(["MissingInputValue", 'field' => "password"], 422)],
            [['username' => "ook", 'password' => "eek"],                           ["ook", "eek"], new ExceptionConflict("alreadyExists"),    null,                   null,                                            new ErrorResponse(["DuplicateUser", 'user' => "ook"], 409)],
            [['username' => "j:k", 'password' => "eek"],                           ["j:k", "eek"], new UserExceptionInput("invalidUsername"), null,                   null,                                            new ErrorResponse(["InvalidInputValue", 'field' => "username"], 422)],
            [['username' => "ook", 'password' => "eek", 'timezone' => "ook"],      ["ook", "eek"], "eek",                                     ['tz' => "ook"],        new UserExceptionInput("invalidTimezone"),       new ErrorResponse(["InvalidInputValue", 'field' => "timezone"], 422)],
            [['username' => "ook", 'password' => "eek", 'entries_per_page' => -1], ["ook", "eek"], "eek",                                     ['page_size' => -1],    new UserExceptionInput("invalidNonZeroInteger"), new ErrorResponse(["InvalidInputValue", 'field' => "entries_per_page"], 422)],
            [['username' => "ook", 'password' => "eek", 'theme' => "default"],     ["ook", "eek"], "eek",                                     ['theme' => "default"], ['theme' => "default"],                          new Response($resp1, 201)],
        ];
    }

    public function testAddAUserWithoutAuthority(): void {
        $this->assertMessage(new ErrorResponse("403", 403), $this->req("POST", "/users", []));
    }

    public function testDeleteAUser(): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['admin' => true]);
        Arsse::$user->method("lookup")->willReturn("john.doe@example.com");
        Arsse::$user->method("remove")->willReturn(true);
        Arsse::$user->expects($this->exactly(1))->method("lookup")->with(2112);
        Arsse::$user->expects($this->exactly(1))->method("remove")->with("john.doe@example.com");
        $this->assertMessage(new EmptyResponse(204), $this->req("DELETE", "/users/2112"));
    }

    public function testDeleteAMissingUser(): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['admin' => true]);
        Arsse::$user->method("lookup")->willThrowException(new ExceptionConflict("doesNotExist"));
        Arsse::$user->method("remove")->willReturn(true);
        Arsse::$user->expects($this->exactly(1))->method("lookup")->with(2112);
        Arsse::$user->expects($this->exactly(0))->method("remove");
        $this->assertMessage(new ErrorResponse("404", 404), $this->req("DELETE", "/users/2112"));
    }

    public function testDeleteAUserWithoutAuthority(): void {
        Arsse::$user->expects($this->exactly(0))->method("lookup");
        Arsse::$user->expects($this->exactly(0))->method("remove");
        $this->assertMessage(new ErrorResponse("403", 403), $this->req("DELETE", "/users/2112"));
    }

    public function testMarkAllArticlesAsRead(): void {
        \Phake::when(Arsse::$db)->articleMark->thenReturn(true);
        $this->assertMessage(new ErrorResponse("403", 403), $this->req("PUT", "/users/1/mark-all-as-read"));
        $this->assertMessage(new EmptyResponse(204), $this->req("PUT", "/users/42/mark-all-as-read"));
        \Phake::verify(Arsse::$db)->articleMark("john.doe@example.com", ['read' => true], (new Context)->hidden(false));
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
            ["New",       new Response(['id' => 2112, 'title' => "New", 'user_id' => 42], 201)],
            ["Duplicate", new ErrorResponse(["DuplicateCategory", 'title' => "Duplicate"], 409)],
            ["",          new ErrorResponse(["InvalidCategory", 'title' => ""], 422)],
            [" ",         new ErrorResponse(["InvalidCategory", 'title' => " "], 422)],
            [null,        new ErrorResponse(["MissingInputValue", 'field' => "title"], 422)],
            [false,       new ErrorResponse(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
        ];
    }

    /** @dataProvider provideCategoryUpdates */
    public function testRenameACategory(int $id, $title, $out, ResponseInterface $exp): void {
        Arsse::$user->method("propertiesSet")->willReturn(['root_folder_name' => $title]);
        if (is_string($out)) {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenThrow(new ExceptionInput($out));
        } else {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenReturn($out);
        }
        $times = (int) ($id === 1 && is_string($title) && strlen(trim($title)));
        Arsse::$user->expects($this->exactly($times))->method("propertiesSet")->with("john.doe@example.com", ['root_folder_name' => $title]);
        $this->assertMessage($exp, $this->req("PUT", "/categories/$id", ['title' => $title]));
        $times = (int) ($id !== 1 && is_string($title));
        \Phake::verify(Arsse::$db, \Phake::times($times))->folderPropertiesSet("john.doe@example.com", $id - 1, ['name' => $title]);
    }

    public function provideCategoryUpdates(): iterable {
        return [
            [3, "New",       "subjectMissing",      new ErrorResponse("404", 404)],
            [2, "New",       true,                  new Response(['id' => 2, 'title' => "New", 'user_id' => 42])],
            [2, "Duplicate", "constraintViolation", new ErrorResponse(["DuplicateCategory", 'title' => "Duplicate"], 409)],
            [2, "",          "missing",             new ErrorResponse(["InvalidCategory", 'title' => ""], 422)],
            [2, " ",         "whitespace",          new ErrorResponse(["InvalidCategory", 'title' => " "], 422)],
            [2, null,        "missing",             new ErrorResponse(["MissingInputValue", 'field' => "title"], 422)],
            [2, false,       "subjectMissing",      new ErrorResponse(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
            [1, "New",       true,                  new Response(['id' => 1, 'title' => "New", 'user_id' => 42])],
            [1, "Duplicate", "constraintViolation", new Response(['id' => 1, 'title' => "Duplicate", 'user_id' => 42])], // This is allowed because the name of the root folder is only a duplicate in circumstances where it is used
            [1, "",          "missing",             new ErrorResponse(["InvalidCategory", 'title' => ""], 422)],
            [1, " ",         "whitespace",          new ErrorResponse(["InvalidCategory", 'title' => " "], 422)],
            [1, null,        "missing",             new ErrorResponse(["MissingInputValue", 'field' => "title"], 422)],
            [1, false,       false,                 new ErrorResponse(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
        ];
    }

    public function testDeleteARealCategory(): void {
        \Phake::when(Arsse::$db)->folderRemove->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $this->assertMessage(new EmptyResponse(204), $this->req("DELETE", "/categories/2112"));
        \Phake::verify(Arsse::$db)->folderRemove("john.doe@example.com", 2111);
        $this->assertMessage(new ErrorResponse("404", 404), $this->req("DELETE", "/categories/47"));
        \Phake::verify(Arsse::$db)->folderRemove("john.doe@example.com", 46);
    }

    public function testDeleteTheSpecialCategory(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result($this->v([
            ['id' => 1],
            ['id' => 47],
            ['id' => 2112],
        ])));
        \Phake::when(Arsse::$db)->subscriptionRemove->thenReturn(true);
        $this->assertMessage(new EmptyResponse(204), $this->req("DELETE", "/categories/1"));
        \Phake::inOrder(
            \Phake::verify(Arsse::$db)->begin(),
            \Phake::verify(Arsse::$db)->subscriptionList("john.doe@example.com", null, false),
            \Phake::verify(Arsse::$db)->subscriptionRemove("john.doe@example.com", 1),
            \Phake::verify(Arsse::$db)->subscriptionRemove("john.doe@example.com", 47),
            \Phake::verify(Arsse::$db)->subscriptionRemove("john.doe@example.com", 2112),
            \Phake::verify($this->transaction)->commit()
        );
    }

    public function testMarkACategoryAsRead(): void {
        \Phake::when(Arsse::$db)->articleMark->thenReturn(1)->thenReturn(1)->thenThrow(new ExceptionInput("idMissing"));
        $this->assertMessage(new EmptyResponse(204), $this->req("PUT", "/categories/2/mark-all-as-read"));
        $this->assertMessage(new EmptyResponse(204), $this->req("PUT", "/categories/1/mark-all-as-read"));
        $this->assertMessage(new ErrorResponse("404", 404), $this->req("PUT", "/categories/2112/mark-all-as-read"));
        \Phake::inOrder(
            \Phake::verify(Arsse::$db)->articleMark("john.doe@example.com", ['read' => true], (new Context)->folder(1)),
            \Phake::verify(Arsse::$db)->articleMark("john.doe@example.com", ['read' => true], (new Context)->folderShallow(0)),
            \Phake::verify(Arsse::$db)->articleMark("john.doe@example.com", ['read' => true], (new Context)->folder(2111))
        );
    }

    public function testListReeds(): void {
        \Phake::when(Arsse::$db)->folderList->thenReturn(new Result([
            ['id' => 5, 'name' => "Cat Ook"],
        ]));
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result([
            ['id' => 1,  'feed' => 12, 'url' => "http://example.com/ook",                      'title' => "Ook", 'source' => "http://example.com/", 'icon_id' => 47,   'icon_url' => "http://example.com/icon", 'folder' => 2112, 'top_folder' => 5,    'pinned' => 0, 'err_count' => 1, 'err_msg' => "Oopsie", 'order_type' => 0, 'keep_rule' => "this|that", 'block_rule' => "both", 'added' => "2020-12-21 21:12:00", 'updated' => "2021-01-05 13:51:32", 'edited' => "2021-01-01 00:00:00", 'modified' => "2020-11-30 04:08:52", 'next_fetch' => "2021-01-20 00:00:00", 'etag' => "OOKEEK", 'scrape' => 0, 'unread' => 42],
            ['id' => 55, 'feed' => 12, 'url' => "http://j%20k:super%20secret@example.com/eek", 'title' => "Eek", 'source' => "http://example.com/", 'icon_id' => null, 'icon_url' => null,                      'folder' => null, 'top_folder' => null, 'pinned' => 0, 'err_count' => 0, 'err_msg' => null,     'order_type' => 0, 'keep_rule' => null,        'block_rule' => null,   'added' => "2020-12-21 21:12:00", 'updated' => "2021-01-05 13:51:32", 'edited' => null,                  'modified' => "2020-11-30 04:08:52", 'next_fetch' => null,                  'etag' => null,     'scrape' => 1, 'unread' => 0],
        ]));
        $exp = new Response([
            [
                'id' => 1,
                'user_id' => 42,
                'feed_url' => "http://example.com/ook",
                'site_url' => "http://example.com/",
                'title' => "Ook",
                'checked_at' => "2021-01-05T13:51:32.000000Z",
                'next_check_at' => "2021-01-20T00:00:00.000000Z",
                'etag_header' => "OOKEEK",
                'last_modified_header' => "Fri, 01 Jan 2021 00:00:00 GMT",
                'parsing_error_message' => "Oopsie",
                'parsing_error_count' => 1,
                'scraper_rules' => "",
                'rewrite_rules' => "",
                'crawler' => false,
                'blocklist_rules' => "both",
                'keeplist_rules' => "this|that",
                'user_agent' => "",
                'username' => "",
                'password' => "",
                'disabled' => false,
                'ignore_http_cache' => false,
                'fetch_via_proxy' => false,
                'category' => [
                    'id' => 6,
                    'title' => "Cat Ook",
                    'user_id' => 42
                ],
                'icon' => [
                    'feed_id' => 1,
                    'icon_id' => 47            
                ],
            ],
            [
                'id' => 55,
                'user_id' => 42,
                'feed_url' => "http://example.com/eek",
                'site_url' => "http://example.com/",
                'title' => "Eek",
                'checked_at' => "2021-01-05T13:51:32.000000Z",
                'next_check_at' => "0001-01-01T00:00:00.000000Z",
                'etag_header' => "",
                'last_modified_header' => "",
                'parsing_error_message' => "",
                'parsing_error_count' => 0,
                'scraper_rules' => "",
                'rewrite_rules' => "",
                'crawler' => true,
                'blocklist_rules' => "",
                'keeplist_rules' => "",
                'user_agent' => "",
                'username' => "j k",
                'password' => "super secret",
                'disabled' => false,
                'ignore_http_cache' => false,
                'fetch_via_proxy' => false,
                'category' => [
                    'id' => 1,
                    'title' => "All",
                    'user_id' => 42
                ],
                'icon' => null,
            ],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/feeds"));
    }

    /** @dataProvider provideFeedCreations */
    public function testCreateAFeed(array $in, $out1, $out2, $out3, ResponseInterface $exp): void {
        if ($out1 instanceof \Exception) {
            \Phake::when(Arsse::$db)->feedAdd->thenThrow($out1);
        } else {
            \Phake::when(Arsse::$db)->feedAdd->thenReturn($out1);
        }
        if ($out2 instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionAdd->thenThrow($out2);
        } else {
            \Phake::when(Arsse::$db)->subscriptionAdd->thenReturn($out2);
        }
        if ($out3 instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenThrow($out3);
        } else {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn($out3);
        }
        $this->assertMessage($exp, $this->req("POST", "/feeds", $in));
        $in1 = $out1 !== null;
        $in2 = $out2 !== null;
        $in3 = $out3 !== null;
        if ($in1) {
            \Phake::verify(Arsse::$db)->feedAdd($in['feed_url'], $in['username'] ?? "", $in['password'] ?? "", true, $in['crawler'] ?? false);
        } else {
            \Phake::verify(Arsse::$db, \Phake::times(0))->feedAdd;
        }
        if ($in2) {
            \Phake::verify(Arsse::$db)->subscriptionAdd("john.doe@example.com", $in['feed_url'], $in['username'] ?? "", $in['password'] ?? "", true, $in['crawler'] ?? false);
        } else {
            \Phake::verify(Arsse::$db, \Phake::times(0))->subscriptionAdd;
        }
        if ($in3) {
            $props = [
                'keep_rule'  => $in['keeplist_rules'],
                'block_rule' => $in['blocklist_rules'],
                'folder'     => $in['category_id'] - 1,
                'scrape'     => $in['crawler'] ?? false,
            ];
            \Phake::verify(Arsse::$db)->subscriptionPropertiesSet("john.doe@example.com", $out2, $props);
        } else {
            \Phake::verify(Arsse::$db, \Phake::times(0))->subscriptionPropertiesSet;
        }
    }

    public function provideFeedCreations(): iterable {
        self::clearData();
        return [
            [['category_id' => 1],                                                                null, null, null, new ErrorResponse(["MissingInputValue", 'field' => "feed_url"], 422)],
            [['feed_url' => "http://example.com/"],                                               null, null, null, new ErrorResponse(["MissingInputValue", 'field' => "category_id"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => "1"],                         null, null, null, new ErrorResponse(["InvalidInputType", 'field' => "category_id", 'expected' => "integer", 'actual' => "string"], 422)],
            [['feed_url' => "Not a URL", 'category_id' => 1],                                     null, null, null, new ErrorResponse(["InvalidInputValue", 'field' => "feed_url"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 0],                           null, null, null, new ErrorResponse(["InvalidInputValue", 'field' => "category_id"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'keeplist_rules' => "["],  null, null, null, new ErrorResponse(["InvalidInputValue", 'field' => "keeplist_rules"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'blocklist_rules' => "["], null, null, null, new ErrorResponse(["InvalidInputValue", 'field' => "blocklist_rules"], 422)],
        ];
    }
}
