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

/** @covers \JKingWeb\Arsse\REST\Miniflux\V1<extended> */
class TestV1 extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $h;
    protected $transaction;
    protected $token = "Tk2o9YubmZIL2fm2w8Z4KlDEQJz532fNSOcTG0s2_xc=";

    protected function req(string $method, string $target, $data = "", array $headers = [], bool $authenticated = true, bool $body = true): ResponseInterface {
        $prefix = "/v1";
        $url = $prefix.$target;
        if ($body) {
            $params = [];
        } else {
            $params = $data;
            $data = [];
        }
        $req = $this->serverRequest($method, $url, $prefix, $headers, [], $data, "application/json", $params, $authenticated ? "john.doe@example.com" : "");
        return $this->h->dispatch($req);
    }

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
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
        $this->assertMessage($exp, $this->req("GET", "/", "", $headers, $auth));
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
        $exp = new ErrorResponse(["invalidInputType", 'field' => "url", 'expected' => "string", 'actual' => "integer"], 400);
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
        $exp = new ErrorResponse("fetch404", 500);
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => "http://localhost:8000/Feed/Discovery/Missing"]));
    }

    public function testQueryUsers(): void {
        $now = Date::normalize("now");
        $u = [
            ['num'=> 1, 'admin' => true,  'theme' => "custom", 'lang' => "fr_CA", 'tz' => "Asia/Gaza", 'sort_asc' => true, 'page_size' => 200,  'shortcuts' => false, 'reading_time' => false, 'swipe' => false, 'stylesheet' => "p {}"],
            ['num'=> 2, 'admin' => false, 'theme' => null,     'lang' => null,    'tz' => null,        'sort_asc' => null, 'page_size' => null, 'shortcuts' => null,  'reading_time' => null,  'swipe' => null,  'stylesheet' => null],
            new ExceptionConflict("doesNotExist"),
        ];
        $exp = [
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
                'last_login_at'           => Date::transform($now, "iso8601m"),
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
                'last_login_at'           => Date::transform($now, "iso8601m"),
                'entry_swipe'             => true,
                'extra'                   => [
                    'custom_css' => "",
                ],
            ]
        ];
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("list")->willReturn(["john.doe@example.com", "jane.doe@example.com", "admin@example.com"]);
        Arsse::$user->method("propertiesGet")->willReturnCallback(function(string $user, bool $includeLerge = true) use ($u) {
            if ($user === "john.doe@example.com") {
                return $u[0];
            } elseif ($user === "jane.doe@example.com") {
                return $u[1];
            }else {
                throw $u[2];
            }
        });
        $this->h = $this->createPartialMock(V1::class, ["now"]);
        $this->h->method("now")->willReturn($now);
        $this->assertMessage(new Response($exp), $this->req("GET", "/users"));
    }
}
