<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use Eloquent\Phony\Mock\Handle\InstanceHandle;
use Eloquent\Phony\Phpunit\Phony;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Context\RootContext;
use JKingWeb\Arsse\Context\UnionContext;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\REST\Miniflux\V1;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\ImportExport\Exception as ImportException;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\User\ExceptionConflict;
use JKingWeb\Arsse\User\ExceptionInput as UserExceptionInput;
use JKingWeb\Arsse\Test\Result;
use Psr\Http\Message\ResponseInterface;

/** @covers \JKingWeb\Arsse\REST\Miniflux\V1<extended> */
class TestV1 extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const NOW = "2020-12-09T22:35:10.023419Z";
    protected const TOKEN = "Tk2o9YubmZIL2fm2w8Z4KlDEQJz532fNSOcTG0s2_xc=";
    protected const USERS = [
        ['id' => 1, 'username' => "john.doe@example.com", 'last_login_at' => self::NOW, 'google_id' => "", 'openid_connect_id' => "", 'is_admin' => true,  'theme' => "custom",      'language' => "fr_CA", 'timezone' => "Asia/Gaza", 'entry_sorting_direction' => "asc",  'entries_per_page' => 200, 'keyboard_shortcuts' => false, 'show_reading_time' => false, 'entry_swipe' => false, 'stylesheet' => "p {}"],
        ['id' => 2, 'username' => "jane.doe@example.com", 'last_login_at' => self::NOW, 'google_id' => "", 'openid_connect_id' => "", 'is_admin' => false, 'theme' => "light_serif", 'language' => "en_US", 'timezone' => "UTC",       'entry_sorting_direction' => "desc", 'entries_per_page' => 100, 'keyboard_shortcuts' => true,  'show_reading_time' => true,  'entry_swipe' => true,  'stylesheet' => ""],
    ];
    protected const FEEDS = [
        ['id' => 1,  'feed' => 12, 'url' => "http://example.com/ook",                      'title' => "Ook", 'source' => "http://example.com/", 'icon_id' => 47,   'icon_url' => "http://example.com/icon", 'folder' => 2112, 'top_folder' => 5,    'folder_name' => "Cat Eek", 'top_folder_name' => "Cat Ook", 'pinned' => 0, 'err_count' => 1, 'err_msg' => "Oopsie", 'order_type' => 0, 'keep_rule' => "this|that", 'block_rule' => "both", 'added' => "2020-12-21 21:12:00", 'updated' => "2021-01-05 13:51:32", 'edited' => "2021-01-01 00:00:00", 'modified' => "2020-11-30 04:08:52", 'next_fetch' => "2021-01-20 00:00:00", 'etag' => "OOKEEK", 'scrape' => 0, 'unread' => 42],
        ['id' => 55, 'feed' => 12, 'url' => "http://j%20k:super%20secret@example.com/eek", 'title' => "Eek", 'source' => "http://example.com/", 'icon_id' => null, 'icon_url' => null,                      'folder' => null, 'top_folder' => null, 'folder_name' => null,      'top_folder_name' => null,      'pinned' => 0, 'err_count' => 0, 'err_msg' => null,     'order_type' => 0, 'keep_rule' => null,        'block_rule' => null,   'added' => "2020-12-21 21:12:00", 'updated' => "2021-01-05 13:51:32", 'edited' => null,                  'modified' => "2020-11-30 04:08:52", 'next_fetch' => null,                  'etag' => null,     'scrape' => 1, 'unread' => 0],
    ];
    protected const FEEDS_OUT = [
        ['id' => 1,  'user_id' => 42, 'feed_url' => "http://example.com/ook", 'site_url' => "http://example.com/", 'title' => "Ook", 'checked_at' => "2021-01-05T15:51:32.000000+02:00", 'next_check_at' => "2021-01-20T02:00:00.000000+02:00", 'etag_header' => "OOKEEK", 'last_modified_header' => "Fri, 01 Jan 2021 00:00:00 GMT", 'parsing_error_message' => "Oopsie", 'parsing_error_count' => 1, 'scraper_rules' => "", 'rewrite_rules' => "", 'crawler' => false, 'blocklist_rules' => "both", 'keeplist_rules' => "this|that", 'user_agent' => "", 'username' => "",    'password' => "",             'disabled' => false, 'ignore_http_cache' => false, 'fetch_via_proxy' => false, 'category' => ['id' => 6, 'title' => "Cat Ook", 'user_id' => 42], 'icon' => ['feed_id' => 1,'icon_id' => 47]],
        ['id' => 55, 'user_id' => 42, 'feed_url' => "http://example.com/eek", 'site_url' => "http://example.com/", 'title' => "Eek", 'checked_at' => "2021-01-05T15:51:32.000000+02:00", 'next_check_at' => "0001-01-01T00:00:00Z",             'etag_header' => "",       'last_modified_header' => "",                              'parsing_error_message' => "",       'parsing_error_count' => 0, 'scraper_rules' => "", 'rewrite_rules' => "", 'crawler' => true,  'blocklist_rules' => "",     'keeplist_rules' => "",          'user_agent' => "", 'username' => "j k", 'password' => "super secret", 'disabled' => false, 'ignore_http_cache' => false, 'fetch_via_proxy' => false, 'category' => ['id' => 1,'title'  => "All",     'user_id' => 42], 'icon' => null],
    ];
    protected const ENTRIES = [
        ['id' => 42,   'url' => "http://example.com/42",   'title' => "Title 42",   'subscription' => 55, 'author' => "Thomas Costain", 'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'modified_date' => "2021-01-22 13:44:47", 'starred' => 0, 'unread' => 0, 'hidden' => 0, 'content' => "Content 42",   'media_url' => null,                                'media_type' => null],
        ['id' => 44,   'url' => "http://example.com/44",   'title' => "Title 44",   'subscription' => 55, 'author' => null,             'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'modified_date' => "2021-01-22 13:44:47", 'starred' => 1, 'unread' => 1, 'hidden' => 0, 'content' => "Content 44",   'media_url' => "http://example.com/44/enclosure",   'media_type' => null],
        ['id' => 47,   'url' => "http://example.com/47",   'title' => "Title 47",   'subscription' => 55, 'author' => null,             'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'modified_date' => "2021-01-22 13:44:47", 'starred' => 0, 'unread' => 1, 'hidden' => 1, 'content' => "Content 47",   'media_url' => "http://example.com/47/enclosure",   'media_type' => ""],
        ['id' => 2112, 'url' => "http://example.com/2112", 'title' => "Title 2112", 'subscription' => 55, 'author' => null,             'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'modified_date' => "2021-01-22 13:44:47", 'starred' => 0, 'unread' => 0, 'hidden' => 1, 'content' => "Content 2112", 'media_url' => "http://example.com/2112/enclosure", 'media_type' => "image/png"],
    ];
    protected const ENTRIES_OUT = [
        ['id' => 42,   'user_id' => 42, 'feed_id' => 55, 'status' => "read",    'hash' => "FINGERPRINT", 'title' => "Title 42",   'url' => "http://example.com/42",   'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'content' => "Content 42",   'author' => "Thomas Costain", 'share_code' => "", 'starred' => false, 'reading_time' => 0, 'enclosures' => null, 'feed' => self::FEEDS_OUT[1]],
        ['id' => 44,   'user_id' => 42, 'feed_id' => 55, 'status' => "unread",  'hash' => "FINGERPRINT", 'title' => "Title 44",   'url' => "http://example.com/44",   'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'content' => "Content 44",   'author' => "",               'share_code' => "", 'starred' => true,  'reading_time' => 0, 'enclosures' => [['id' => 44,   'user_id' => 42, 'entry_id' => 44,   'url' => "http://example.com/44/enclosure",   'mime_type' => "application/octet-stream", 'size' => 0]], 'feed' => self::FEEDS_OUT[1]],
        ['id' => 47,   'user_id' => 42, 'feed_id' => 55, 'status' => "removed", 'hash' => "FINGERPRINT", 'title' => "Title 47",   'url' => "http://example.com/47",   'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'content' => "Content 47",   'author' => "",               'share_code' => "", 'starred' => false, 'reading_time' => 0, 'enclosures' => [['id' => 47,   'user_id' => 42, 'entry_id' => 47,   'url' => "http://example.com/47/enclosure",   'mime_type' => "application/octet-stream", 'size' => 0]], 'feed' => self::FEEDS_OUT[1]],
        ['id' => 2112, 'user_id' => 42, 'feed_id' => 55, 'status' => "removed", 'hash' => "FINGERPRINT", 'title' => "Title 2112", 'url' => "http://example.com/2112", 'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'content' => "Content 2112", 'author' => "",               'share_code' => "", 'starred' => false, 'reading_time' => 0, 'enclosures' => [['id' => 2112, 'user_id' => 42, 'entry_id' => 2112, 'url' => "http://example.com/2112/enclosure", 'mime_type' => "image/png",                'size' => 0]], 'feed' => self::FEEDS_OUT[1]],
    ];

    protected $h;
    protected $transaction;

    protected function req(string $method, string $target, $data = "", array $headers = [], ?string $user = "john.doe@example.com", bool $body = true): ResponseInterface {
        Arsse::$obj = $this->objMock->get();
        Arsse::$db = $this->dbMock->get();
        if ($this->h instanceof InstanceHandle) {
            $this->h = $this->h->get();
        }
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
        parent::setUp();
        self::setConf();
        // create mock timestamps
        $this->objMock->get->with(\DateTimeImmutable::class)->returns(new \DateTimeImmutable(self::NOW));
        // create a mock database interface
        $this->transaction = $this->mock(Transaction::class);
        $this->dbMock = $this->mock(Database::class);
        $this->dbMock->begin->returns($this->transaction->get());
        // create a mock user manager; we use a PHPUnitmock because Phake for reasons unknown is unable to mock the User class correctly, sometimes
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['num' => 42, 'admin' => false, 'root_folder_name' => null, 'tz' => "Asia/Gaza"]);
        Arsse::$user->method("begin")->willReturn($this->transaction->get());
        //initialize a handler
        $this->h = new V1();
    }

    protected function v($value) {
        return $value;
    }

    public function testGenerateErrorResponse() {
        $act = V1::respError(["DuplicateUser", 'user' => "john.doe"], 409, ['Cache-Control' => "no-store"]);
        $exp = HTTP::respJson(['error_message' => 'The user name "john.doe" already exists'], 409, ['Cache-Control' => "no-store"]);
        $this->assertMessage($exp, $act);
    }

    /** @dataProvider provideAuthResponses */
    public function testAuthenticateAUser($token, bool $auth, bool $success): void {
        $exp = $success ? HTTP::respEmpty(404) : V1::respError("401", 401);
        $user = "john.doe@example.com";
        if ($token !== null) {
            $headers = ['X-Auth-Token' => $token];
        } else {
            $headers = [];
        }
        Arsse::$user->id = null;
        $this->dbMock->tokenLookup->throws(new ExceptionInput("subjectMissing"));
        $this->dbMock->tokenLookup->with("miniflux.login", self::TOKEN)->returns(['user' => $user]);
        $this->assertMessage($exp, $this->req("GET", "/", "", $headers, $auth ? "john.doe@example.com" : null));
        $this->assertSame($success ? $user : null, Arsse::$user->id);
    }

    public function provideAuthResponses(): iterable {
        return [
            [null,                   false, false],
            [null,                   true,  true],
            [self::TOKEN,            false, true],
            [[self::TOKEN, "BOGUS"], false, true],
            ["",                     true,  true],
            [["", "BOGUS"],          true,  true],
            ["NOT A TOKEN",          false, false],
            ["NOT A TOKEN",          true,  false],
            [["BOGUS", self::TOKEN], false, false],
            [["", self::TOKEN],      false, false],
        ];
    }

    /** @dataProvider provideInvalidPaths */
    public function testRespondToInvalidPaths($path, $method, $code, $allow = null): void {
        $exp = HTTP::respEmpty($code, $allow ? ['Allow' => $allow] : []);
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
        $exp = HTTP::respEmpty(204, [
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

    public function testRejectMalformedData(): void {
        $exp = V1::respError(["InvalidBodyJSON", "Syntax error"], 400);
        $this->assertMessage($exp, $this->req("POST", "/discover", "{"));
    }

    public function testRejectBadlyTypedData(): void {
        $exp = V1::respError(["InvalidInputType", 'field' => "url", 'expected' => "string", 'actual' => "integer"], 422);
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
            ["http://localhost:8000/Feed/Discovery/Valid",   HTTP::respJson($discovered)],
            ["http://localhost:8000/Feed/Discovery/Invalid", HTTP::respJson([])],
            ["http://localhost:8000/Feed/Discovery/Missing", V1::respError("Fetch404", 502)],
            [1,                                              V1::respError(["InvalidInputType", 'field' => "url", 'expected' => "string", 'actual' => "integer"], 422)],
            ["Not a URL",                                    V1::respError(["InvalidInputValue", 'field' => "url"], 422)],
            [null,                                           V1::respError(["MissingInputValue", 'field' => "url"], 422)],
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
        $this->assertMessage($exp, $this->req("GET", $route, "", [], $user));
    }

    public function provideUserQueries(): iterable {
        self::clearData();
        return [
            [true,  "/users",                      HTTP::respJson(self::USERS)],
            [true,  "/me",                         HTTP::respJson(self::USERS[0])],
            [true,  "/users/john.doe@example.com", HTTP::respJson(self::USERS[0])],
            [true,  "/users/1",                    HTTP::respJson(self::USERS[0])],
            [true,  "/users/jane.doe@example.com", HTTP::respJson(self::USERS[1])],
            [true,  "/users/2",                    HTTP::respJson(self::USERS[1])],
            [true,  "/users/jack.doe@example.com", V1::respError("404", 404)],
            [true,  "/users/47",                   V1::respError("404", 404)],
            [false, "/users",                      V1::respError("403", 403)],
            [false, "/me",                         HTTP::respJson(self::USERS[1])],
            [false, "/users/john.doe@example.com", V1::respError("403", 403)],
            [false, "/users/1",                    V1::respError("403", 403)],
            [false, "/users/jane.doe@example.com", V1::respError("403", 403)],
            [false, "/users/2",                    V1::respError("403", 403)],
            [false, "/users/jack.doe@example.com", V1::respError("403", 403)],
            [false, "/users/47",                   V1::respError("403", 403)],
        ];
    }

    /** @dataProvider provideUserModifications */
    public function testModifyAUser(bool $admin, string $url, array $body, $in1, $out1, $in2, $out2, $in3, $out3, ResponseInterface $exp): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("begin")->willReturn($this->transaction->get());
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
        $resp1 = array_merge(self::USERS[1], ['username' => "john.doe@example.com"]);
        $resp2 = array_merge(self::USERS[1], ['id' => 1, 'is_admin' => true]);
        return [
            [false, "/users/1", ['is_admin' => 0],                          null,  null,                                      null,  null,  null,                  null,                                            V1::respError(["InvalidInputType", 'field' => "is_admin", 'expected' => "boolean", 'actual' => "integer"], 422)],
            [false, "/users/1", ['entry_sorting_direction' => "bad"],       null,  null,                                      null,  null,  null,                  null,                                            V1::respError(["InvalidInputValue", 'field' => "entry_sorting_direction"], 422)],
            [false, "/users/1", ['theme' => "stark"],                       null,  null,                                      null,  null,  null,                  null,                                            V1::respError("403", 403)],
            [false, "/users/2", ['is_admin' => true],                       null,  null,                                      null,  null,  null,                  null,                                            V1::respError("InvalidElevation", 403)],
            [false, "/users/2", ['language' => "fr_CA"],                    null,  null,                                      null,  null,  ['lang' => "fr_CA"],   $out1,                                           HTTP::respJson($resp1, 201)],
            [false, "/users/2", ['entry_sorting_direction' => "asc"],       null,  null,                                      null,  null,  ['sort_asc' => true],  $out1,                                           HTTP::respJson($resp1, 201)],
            [false, "/users/2", ['entry_sorting_direction' => "desc"],      null,  null,                                      null,  null,  ['sort_asc' => false], $out1,                                           HTTP::respJson($resp1, 201)],
            [false, "/users/2", ['entries_per_page' => -1],                 null,  null,                                      null,  null,  ['page_size' => -1],   new UserExceptionInput("invalidNonZeroInteger"), V1::respError(["InvalidInputValue", 'field' => "entries_per_page"], 422)],
            [false, "/users/2", ['timezone' => "Ook"],                      null,  null,                                      null,  null,  ['tz' => "Ook"],       new UserExceptionInput("invalidTimezone"),       V1::respError(["InvalidInputValue", 'field' => "timezone"], 422)],
            [false, "/users/2", ['username' => "j:k"],                      "j:k", new UserExceptionInput("invalidUsername"), null,  null,  null,                  null,                                            V1::respError(["InvalidInputValue", 'field' => "username"], 422)],
            [false, "/users/2", ['username' => "ook"],                      "ook", new ExceptionConflict("alreadyExists"),    null,  null,  null,                  null,                                            V1::respError(["DuplicateUser", 'user' => "ook"], 409)],
            [false, "/users/2", ['password' => "ook"],                      null,  null,                                      "ook", "ook", null,                  null,                                            HTTP::respJson(array_merge($resp1, ['password' => "ook"]), 201)],
            [false, "/users/2", ['username' => "ook", 'password' => "ook"], "ook", true,                                      "ook", "ook", null,                  null,                                            HTTP::respJson(array_merge($resp1, ['username' => "ook", 'password' => "ook"]), 201)],
            [true,  "/users/1", ['theme' => "stark"],                       null,  null,                                      null,  null,  ['theme' => "stark"],  $out2,                                           HTTP::respJson($resp2, 201)],
            [true,  "/users/3", ['theme' => "stark"],                       null,  null,                                      null,  null,  null,                  null,                                            V1::respError("404", 404)],
        ];
    }

    /** @dataProvider provideUserAdditions */
    public function testAddAUser(array $body, $in1, $out1, $in2, $out2, ResponseInterface $exp): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("begin")->willReturn($this->transaction->get());
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
        $resp1 = array_merge(self::USERS[1], ['username' => "ook", 'password' => "eek"]);
        return [
            [[],                                                                   null,           null,                                      null,                   null,                                            V1::respError(["MissingInputValue", 'field' => "username"], 422)],
            [['username' => "ook"],                                                null,           null,                                      null,                   null,                                            V1::respError(["MissingInputValue", 'field' => "password"], 422)],
            [['username' => "ook", 'password' => "eek"],                           ["ook", "eek"], new ExceptionConflict("alreadyExists"),    null,                   null,                                            V1::respError(["DuplicateUser", 'user' => "ook"], 409)],
            [['username' => "j:k", 'password' => "eek"],                           ["j:k", "eek"], new UserExceptionInput("invalidUsername"), null,                   null,                                            V1::respError(["InvalidInputValue", 'field' => "username"], 422)],
            [['username' => "ook", 'password' => "eek", 'timezone' => "ook"],      ["ook", "eek"], "eek",                                     ['tz' => "ook"],        new UserExceptionInput("invalidTimezone"),       V1::respError(["InvalidInputValue", 'field' => "timezone"], 422)],
            [['username' => "ook", 'password' => "eek", 'entries_per_page' => -1], ["ook", "eek"], "eek",                                     ['page_size' => -1],    new UserExceptionInput("invalidNonZeroInteger"), V1::respError(["InvalidInputValue", 'field' => "entries_per_page"], 422)],
            [['username' => "ook", 'password' => "eek", 'theme' => "default"],     ["ook", "eek"], "eek",                                     ['theme' => "default"], ['theme' => "default"],                          HTTP::respJson($resp1, 201)],
        ];
    }

    public function testAddAUserWithoutAuthority(): void {
        $this->assertMessage(V1::respError("403", 403), $this->req("POST", "/users", []));
    }

    public function testDeleteAUser(): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['admin' => true]);
        Arsse::$user->method("lookup")->willReturn("john.doe@example.com");
        Arsse::$user->method("remove")->willReturn(true);
        Arsse::$user->expects($this->exactly(1))->method("lookup")->with(2112);
        Arsse::$user->expects($this->exactly(1))->method("remove")->with("john.doe@example.com");
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/users/2112"));
    }

    public function testDeleteAMissingUser(): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['admin' => true]);
        Arsse::$user->method("lookup")->willThrowException(new ExceptionConflict("doesNotExist"));
        Arsse::$user->method("remove")->willReturn(true);
        Arsse::$user->expects($this->exactly(1))->method("lookup")->with(2112);
        Arsse::$user->expects($this->exactly(0))->method("remove");
        $this->assertMessage(V1::respError("404", 404), $this->req("DELETE", "/users/2112"));
    }

    public function testDeleteAUserWithoutAuthority(): void {
        Arsse::$user->expects($this->exactly(0))->method("lookup");
        Arsse::$user->expects($this->exactly(0))->method("remove");
        $this->assertMessage(V1::respError("403", 403), $this->req("DELETE", "/users/2112"));
    }

    public function testListCategories(): void {
        $this->dbMock->folderList->returns(new Result($this->v([
            ['id' => 1,  'name' => "Science"],
            ['id' => 20, 'name' => "Technology"],
        ])));
        $exp = HTTP::respJson([
            ['id' => 1,  'title' => "All",        'user_id' => 42],
            ['id' => 2,  'title' => "Science",    'user_id' => 42],
            ['id' => 21, 'title' => "Technology", 'user_id' => 42],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories"));
        $this->dbMock->folderList->calledWith("john.doe@example.com", null, false);
        // run test again with a renamed root folder
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn(['num' => 47, 'admin' => false, 'root_folder_name' => "Uncategorized"]);
        $exp = HTTP::respJson([
            ['id' => 1,  'title' => "Uncategorized", 'user_id' => 47],
            ['id' => 2,  'title' => "Science",       'user_id' => 47],
            ['id' => 21, 'title' => "Technology",    'user_id' => 47],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories"));
    }

    /** @dataProvider provideCategoryAdditions */
    public function testAddACategory($title, ResponseInterface $exp): void {
        if (!strlen((string) $title)) {
            $this->dbMock->folderAdd->throws(new ExceptionInput("missing"));
        } elseif (!strlen(trim((string) $title))) {
            $this->dbMock->folderAdd->throws(new ExceptionInput("whitespace"));
        } elseif ($title === "Duplicate") {
            $this->dbMock->folderAdd->throws(new ExceptionInput("constraintViolation"));
        } else {
            $this->dbMock->folderAdd->returns(2111);
        }
        $this->assertMessage($exp, $this->req("POST", "/categories", ['title' => $title]));
    }

    public function provideCategoryAdditions(): iterable {
        return [
            ["New",       HTTP::respJson(['id' => 2112, 'title' => "New", 'user_id' => 42], 201)],
            ["Duplicate", V1::respError(["DuplicateCategory", 'title' => "Duplicate"], 409)],
            ["",          V1::respError(["InvalidCategory", 'title' => ""], 422)],
            [" ",         V1::respError(["InvalidCategory", 'title' => " "], 422)],
            [null,        V1::respError(["MissingInputValue", 'field' => "title"], 422)],
            [false,       V1::respError(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
        ];
    }

    /** @dataProvider provideCategoryUpdates */
    public function testRenameACategory(int $id, $title, $out, ResponseInterface $exp): void {
        Arsse::$user->method("propertiesSet")->willReturn(['root_folder_name' => $title]);
        if (is_string($out)) {
            $this->dbMock->folderPropertiesSet->throws(new ExceptionInput($out));
        } else {
            $this->dbMock->folderPropertiesSet->returns($out);
        }
        $times = (int) ($id === 1 && is_string($title) && strlen(trim($title)));
        Arsse::$user->expects($this->exactly($times))->method("propertiesSet")->with("john.doe@example.com", ['root_folder_name' => $title]);
        $this->assertMessage($exp, $this->req("PUT", "/categories/$id", ['title' => $title]));
        $times = (int) ($id !== 1 && is_string($title));
        $this->dbMock->folderPropertiesSet->times($times)->calledWith("john.doe@example.com", $id - 1, ['name' => $title]);
    }

    public function provideCategoryUpdates(): iterable {
        return [
            [3, "New",       "subjectMissing",      V1::respError("404", 404)],
            [2, "New",       true,                  HTTP::respJson(['id' => 2, 'title' => "New", 'user_id' => 42], 201)],
            [2, "Duplicate", "constraintViolation", V1::respError(["DuplicateCategory", 'title' => "Duplicate"], 409)],
            [2, "",          "missing",             V1::respError(["InvalidCategory", 'title' => ""], 422)],
            [2, " ",         "whitespace",          V1::respError(["InvalidCategory", 'title' => " "], 422)],
            [2, null,        "missing",             V1::respError(["MissingInputValue", 'field' => "title"], 422)],
            [2, false,       "subjectMissing",      V1::respError(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
            [1, "New",       true,                  HTTP::respJson(['id' => 1, 'title' => "New", 'user_id' => 42], 201)],
            [1, "Duplicate", "constraintViolation", HTTP::respJson(['id' => 1, 'title' => "Duplicate", 'user_id' => 42], 201)], // This is allowed because the name of the root folder is only a duplicate in circumstances where it is used
            [1, "",          "missing",             V1::respError(["InvalidCategory", 'title' => ""], 422)],
            [1, " ",         "whitespace",          V1::respError(["InvalidCategory", 'title' => " "], 422)],
            [1, null,        "missing",             V1::respError(["MissingInputValue", 'field' => "title"], 422)],
            [1, false,       false,                 V1::respError(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
        ];
    }

    public function testDeleteARealCategory(): void {
        $this->dbMock->folderRemove->returns(true)->throws(new ExceptionInput("subjectMissing"));
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/categories/2112"));
        $this->dbMock->folderRemove->calledWith("john.doe@example.com", 2111);
        $this->assertMessage(V1::respError("404", 404), $this->req("DELETE", "/categories/47"));
        $this->dbMock->folderRemove->calledWith("john.doe@example.com", 46);
    }

    public function testDeleteTheSpecialCategory(): void {
        $this->dbMock->subscriptionList->returns(new Result($this->v([
            ['id' => 1],
            ['id' => 47],
            ['id' => 2112],
        ])));
        $this->dbMock->subscriptionRemove->returns(true);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/categories/1"));
        Phony::inOrder(
            $this->dbMock->begin->calledWith(),
            $this->dbMock->subscriptionList->calledWith("john.doe@example.com", null, false),
            $this->dbMock->subscriptionRemove->calledWith("john.doe@example.com", 1),
            $this->dbMock->subscriptionRemove->calledWith("john.doe@example.com", 47),
            $this->dbMock->subscriptionRemove->calledWith("john.doe@example.com", 2112),
            $this->transaction->commit->called()
        );
    }

    public function testListFeeds(): void {
        $this->dbMock->subscriptionList->returns(new Result($this->v(self::FEEDS)));
        $exp = HTTP::respJson(self::FEEDS_OUT);
        $this->assertMessage($exp, $this->req("GET", "/feeds"));
    }

    public function testListFeedsOfACategory(): void {
        $this->dbMock->subscriptionList->returns(new Result($this->v(self::FEEDS)));
        $exp = HTTP::respJson(self::FEEDS_OUT);
        $this->assertMessage($exp, $this->req("GET", "/categories/2112/feeds"));
        $this->dbMock->subscriptionList->calledWith(Arsse::$user->id, 2111, true);
    }

    public function testListFeedsOfTheRootCategory(): void {
        $this->dbMock->subscriptionList->returns(new Result($this->v(self::FEEDS)));
        $exp = HTTP::respJson(self::FEEDS_OUT);
        $this->assertMessage($exp, $this->req("GET", "/categories/1/feeds"));
        $this->dbMock->subscriptionList->calledWith(Arsse::$user->id, 0, false);
    }

    public function testListFeedsOfAMissingCategory(): void {
        $this->dbMock->subscriptionList->throws(new ExceptionInput("idMissing"));
        $exp = V1::respError("404", 404);
        $this->assertMessage($exp, $this->req("GET", "/categories/2112/feeds"));
        $this->dbMock->subscriptionList->calledWith(Arsse::$user->id, 2111, true);
    }

    public function testGetAFeed(): void {
        $this->dbMock->subscriptionPropertiesGet->returns($this->v(self::FEEDS[0]))->returns($this->v(self::FEEDS[1]));
        $this->assertMessage(HTTP::respJson(self::FEEDS_OUT[0]), $this->req("GET", "/feeds/1"));
        $this->dbMock->subscriptionPropertiesGet->calledWith(Arsse::$user->id, 1);
        $this->assertMessage(HTTP::respJson(self::FEEDS_OUT[1]), $this->req("GET", "/feeds/55"));
        $this->dbMock->subscriptionPropertiesGet->calledWith(Arsse::$user->id, 55);
    }

    public function testGetAMissingFeed(): void {
        $this->dbMock->subscriptionPropertiesGet->throws(new ExceptionInput("subjectMissing"));
        $this->assertMessage(V1::respError("404", 404), $this->req("GET", "/feeds/1"));
        $this->dbMock->subscriptionPropertiesGet->calledWith(Arsse::$user->id, 1);
    }

    /** @dataProvider provideFeedCreations */
    public function testCreateAFeed(array $in, $out1, $out2, $out3, $out4, ResponseInterface $exp): void {
        if ($out1 instanceof \Exception) {
            $this->dbMock->feedAdd->throws($out1);
        } else {
            $this->dbMock->feedAdd->returns($out1);
        }
        if ($out2 instanceof \Exception) {
            $this->dbMock->subscriptionAdd->throws($out2);
        } else {
            $this->dbMock->subscriptionAdd->returns($out2);
        }
        if ($out3 instanceof \Exception) {
            $this->dbMock->subscriptionPropertiesSet->throws($out3);
        } elseif ($out4 instanceof \Exception) {
            $this->dbMock->subscriptionPropertiesSet->returns($out3)->throws($out4);
        } else {
            $this->dbMock->subscriptionPropertiesSet->returns($out3)->returns($out4);
        }
        $this->assertMessage($exp, $this->req("POST", "/feeds", $in));
        $in1 = $out1 !== null;
        $in2 = $out2 !== null;
        $in3 = $out3 !== null;
        $in4 = $out4 !== null;
        if ($in1) {
            $this->dbMock->feedAdd->calledWith($in['feed_url'], $in['username'] ?? "", $in['password'] ?? "", false, $in['crawler'] ?? false);
        } else {
            $this->dbMock->feedAdd->never()->called();
        }
        if ($in2) {
            $this->dbMock->begin->calledWith();
            $this->dbMock->subscriptionAdd->calledWith("john.doe@example.com", $in['feed_url'], $in['username'] ?? "", $in['password'] ?? "", false, $in['crawler'] ?? false);
        } else {
            $this->dbMock->begin->never()->called();
            $this->dbMock->subscriptionAdd->never()->called();
        }
        if ($in3) {
            $props = [
                'folder'     => $in['category_id'] - 1,
                'scrape'     => $in['crawler'] ?? false,
            ];
            $this->dbMock->subscriptionPropertiesSet->calledWith("john.doe@example.com", $out2, $props);
            if (!$out3 instanceof \Exception) {
                $this->transaction->commit->called();
            }
        } else {
            $this->dbMock->subscriptionPropertiesSet->never()->called();
        }
        if ($in4) {
            $rules = [
                'keep_rule'  => $in['keeplist_rules'] ?? null,
                'block_rule' => $in['blocklist_rules'] ?? null,
            ];
            $this->dbMock->subscriptionPropertiesSet->calledWith("john.doe@example.com", $out2, $rules);
        } else {
            $this->dbMock->subscriptionPropertiesSet->atMost(1)->called();
        }
    }

    public function provideFeedCreations(): iterable {
        self::clearData();
        return [
            [['category_id' => 1],                                                                null,                                       null,                                      null,                            null, V1::respError(["MissingInputValue", 'field' => "feed_url"], 422)],
            [['feed_url' => "http://example.com/"],                                               null,                                       null,                                      null,                            null, V1::respError(["MissingInputValue", 'field' => "category_id"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => "1"],                         null,                                       null,                                      null,                            null, V1::respError(["InvalidInputType", 'field' => "category_id", 'expected' => "integer", 'actual' => "string"], 422)],
            [['feed_url' => "Not a URL", 'category_id' => 1],                                     null,                                       null,                                      null,                            null, V1::respError(["InvalidInputValue", 'field' => "feed_url"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 0],                           null,                                       null,                                      null,                            null, V1::respError(["InvalidInputValue", 'field' => "category_id"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'keeplist_rules' => "["],  null,                                       null,                                      null,                            null, V1::respError(["InvalidInputValue", 'field' => "keeplist_rules"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'blocklist_rules' => "["], null,                                       null,                                      null,                            null, V1::respError(["InvalidInputValue", 'field' => "blocklist_rules"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("internalError"),         null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("invalidCertificate"),    null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("invalidUrl"),            null,                                      null,                            null, V1::respError("Fetch404", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("maxRedirect"),           null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("maxSize"),               null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("timeout"),               null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("forbidden"),             null,                                      null,                            null, V1::respError("Fetch403", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("unauthorized"),          null,                                      null,                            null, V1::respError("Fetch401", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("transmissionError"),     null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("connectionFailed"),      null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("malformedXml"),          null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("xmlEntity"),             null,                                      null,                            null, V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("subscriptionNotFound"),  null,                                      null,                            null, V1::respError("Fetch404", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("unsupportedFeedFormat"), null,                                      null,                            null, V1::respError("FetchFormat", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           2112,                                       new ExceptionInput("constraintViolation"), null,                            null, V1::respError("DuplicateFeed", 409)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           2112,                                       44,                                        new ExceptionInput("idMissing"), null, V1::respError("MissingCategory", 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           2112,                                       44,                                        true,                            null, HTTP::respJson(['feed_id' => 44], 201)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'keeplist_rules' => "^A"], 2112,                                       44,                                        true,                            true, HTTP::respJson(['feed_id' => 44], 201)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'blocklist_rules' => "A"], 2112,                                       44,                                        true,                            true, HTTP::respJson(['feed_id' => 44], 201)],
        ];
    }

    /** @dataProvider provideFeedModifications */
    public function testModifyAFeed(array $in, array $data, $out, ResponseInterface $exp): void {
        $this->h = $this->partialMock(V1::class);
        $this->h->getFeed->returns(HTTP::respJson(self::FEEDS_OUT[0]));
        if ($out instanceof \Exception) {
            $this->dbMock->subscriptionPropertiesSet->throws($out);
        } else {
            $this->dbMock->subscriptionPropertiesSet->returns($out);
        }
        $this->assertMessage($exp, $this->req("PUT", "/feeds/2112", $in));
        $this->dbMock->subscriptionPropertiesSet->calledWith(Arsse::$user->id, 2112, $data);
    }

    public function provideFeedModifications(): iterable {
        self::clearData();
        $success = HTTP::respJson(self::FEEDS_OUT[0], 201);
        return [
            [[],                                     [],                                    true,                                 $success],
            [[],                                     [],                                    new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            [['title' => ""],                        ['title' => ""],                       new ExceptionInput("missing"),        V1::respError("InvalidTitle", 422)],
            [['title' => " "],                       ['title' => " "],                      new ExceptionInput("whitespace"),     V1::respError("InvalidTitle", 422)],
            [['title' => " "],                       ['title' => " "],                      new ExceptionInput("whitespace"),     V1::respError("InvalidTitle", 422)],
            [['category_id' => 47],                  ['folder' => 46],                      new ExceptionInput("idMissing"),      V1::respError("MissingCategory", 422)],
            [['crawler' => false],                   ['scrape' => false],                   true,                                 $success],
            [['keeplist_rules' => ""],               ['keep_rule' => ""],                   true,                                 $success],
            [['blocklist_rules' => "ook"],           ['block_rule' => "ook"],               true,                                 $success],
            [['title' => "Ook!", 'crawler' => true], ['title' => "Ook!", 'scrape' => true], true,                                 $success],
        ];
    }

    public function testModifyAFeedWithNoBody(): void {
        $this->h = $this->partialMock(V1::class);
        $this->h->getFeed->returns(HTTP::respJson(self::FEEDS_OUT[0]));
        $this->dbMock->subscriptionPropertiesSet->returns(true);
        $this->assertMessage(HTTP::respJson(self::FEEDS_OUT[0], 201), $this->req("PUT", "/feeds/2112", ""));
        $this->dbMock->subscriptionPropertiesSet->calledWith(Arsse::$user->id, 2112, []);
    }

    public function testDeleteAFeed(): void {
        $this->dbMock->subscriptionRemove->returns(true);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/feeds/2112"));
        $this->dbMock->subscriptionRemove->calledWith(Arsse::$user->id, 2112);
    }

    public function testDeleteAMissingFeed(): void {
        $this->dbMock->subscriptionRemove->throws(new ExceptionInput("subjectMissing"));
        $this->assertMessage(V1::respError("404", 404), $this->req("DELETE", "/feeds/2112"));
        $this->dbMock->subscriptionRemove->calledWith(Arsse::$user->id, 2112);
    }

    /** @dataProvider provideIcons */
    public function testGetTheIconOfASubscription($out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            $this->dbMock->subscriptionIcon->throws($out);
        } else {
            $this->dbMock->subscriptionIcon->returns($this->v($out));
        }
        $this->assertMessage($exp, $this->req("GET", "/feeds/2112/icon"));
        $this->dbMock->subscriptionIcon->calledWith(Arsse::$user->id, 2112);
    }

    public function provideIcons(): iterable {
        return [
            [['id' => 44, 'type' => "image/svg+xml", 'data' => "<svg/>"], HTTP::respJson(['id' => 44, 'data' => "image/svg+xml;base64,PHN2Zy8+", 'mime_type' => "image/svg+xml"])],
            [['id' => 47, 'type' => "",              'data' => "<svg/>"], V1::respError("404", 404)],
            [['id' => 47, 'type' => null,            'data' => "<svg/>"], V1::respError("404", 404)],
            [['id' => 47, 'type' => null,            'data' => null],     V1::respError("404", 404)],
            [null,                                                        V1::respError("404", 404)],
            [new ExceptionInput("subjectMissing"),                        V1::respError("404", 404)],
        ];
    }

    /** @dataProvider provideEntryQueries */
    public function testGetEntries(string $url, ?RootContext $c, ?array $order, $out, bool $count, ResponseInterface $exp): void {
        $this->dbMock->subscriptionList->returns(new Result($this->v(self::FEEDS)));
        $this->dbMock->articleCount->returns(2112);
        if ($out instanceof \Exception) {
            $this->dbMock->articleList->throws($out);
        } else {
            $this->dbMock->articleList->returns(new Result($this->v($out)));
        }
        $this->assertMessage($exp, $this->req("GET", $url));
        if ($c) {
            $this->dbMock->articleList->calledWith(Arsse::$user->id, $this->equalTo($c), array_keys(self::ENTRIES[0]), $order);
        } else {
            $this->dbMock->articleList->never()->called();
        }
        if ($out && !$out instanceof \Exception) {
            $this->dbMock->subscriptionList->calledWith(Arsse::$user->id);
        } else {
            $this->dbMock->subscriptionList->never()->called();
        }
        if ($count) {
            $this->dbMock->articleCount->calledWith(Arsse::$user->id, $this->equalTo((clone $c)->limit(0)->offset(0)));
        } else {
            $this->dbMock->articleCount->never()->called();
        }
    }

    public function provideEntryQueries(): iterable {
        self::clearData();
        $c = (new Context)->limit(100);
        $o = ["modified_date"]; // the default sort order
        return [
            ["/entries?after=A",                                   null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "after"], 400)],
            ["/entries?before=B",                                  null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "before"], 400)],
            ["/entries?category_id=0",                             null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "category_id"], 400)],
            ["/entries?after_entry_id=0",                          null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "after_entry_id"], 400)],
            ["/entries?before_entry_id=0",                         null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "before_entry_id"], 400)],
            ["/entries?limit=-1",                                  null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "limit"], 400)],
            ["/entries?offset=-1",                                 null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "offset"], 400)],
            ["/entries?direction=sideways",                        null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "direction"], 400)],
            ["/entries?order=false",                               null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "order"], 400)],
            ["/entries?starred&starred",                           null,                                                                  null,                      [],                              false, V1::respError(["DuplicateInputValue", 'field' => "starred"], 400)],
            ["/entries?after&after=0",                             null,                                                                  null,                      [],                              false, V1::respError(["DuplicateInputValue", 'field' => "after"], 400)],
            ["/entries",                                           $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?category_id=47",                            (clone $c)->folder(46),                                                $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?category_id=1",                             (clone $c)->folderShallow(0),                                          $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=unread",                             (clone $c)->unread(true)->hidden(false),                               $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=read",                               (clone $c)->unread(false)->hidden(false),                              $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=removed",                            (clone $c)->hidden(true),                                              $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=unread&status=read",                 (clone $c)->hidden(false),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=unread&status=removed",              new UnionContext((clone $c)->unread(true), (clone $c)->hidden(true)),  $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=removed&status=read",                new UnionContext((clone $c)->unread(false), (clone $c)->hidden(true)), $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=removed&status=read&status=removed", new UnionContext((clone $c)->unread(false), (clone $c)->hidden(true)), $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?status=removed&status=read&status=unread",  $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?starred",                                   (clone $c)->starred(true),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?starred=",                                  (clone $c)->starred(true),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?starred=true",                              (clone $c)->starred(true),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?starred=false",                             (clone $c)->starred(true),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?after=0",                                   (clone $c)->modifiedRange(0, null),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?before=0",                                  $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?before=1",                                  (clone $c)->modifiedRange(null, 1),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?before=1&after=0",                          (clone $c)->modifiedRange(0, 1),                                       $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?after_entry_id=42",                         (clone $c)->articleRange(43, null),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?before_entry_id=47",                        (clone $c)->articleRange(null, 46),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?search=alpha%20beta",                       (clone $c)->searchTerms(["alpha", "beta"]),                            $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?limit=4",                                   (clone $c)->limit(4),                                                  $o,                        self::ENTRIES,                   true,  HTTP::respJson(['total' => 2112, 'entries' => self::ENTRIES_OUT])],
            ["/entries?offset=20",                                 (clone $c)->offset(20),                                                $o,                        [],                              true,  HTTP::respJson(['total' => 2112, 'entries' => []])],
            ["/entries?direction=asc",                             $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=id",                                  $c,                                                                    ["id"],                    self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=published_at",                        $c,                                                                    ["modified_date"],         self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=category_id",                         $c,                                                                    ["top_folder"],            self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=category_title",                      $c,                                                                    ["top_folder_name"],       self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=status",                              $c,                                                                    ["hidden", "unread desc"], self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?direction=desc",                            $c,                                                                    ["modified_date desc"],    self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=id&direction=desc",                   $c,                                                                    ["id desc"],               self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=published_at&direction=desc",         $c,                                                                    ["modified_date desc"],    self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=category_id&direction=desc",          $c,                                                                    ["top_folder desc"],       self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=category_title&direction=desc",       $c,                                                                    ["top_folder_name desc"],  self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?order=status&direction=desc",               $c,                                                                    ["hidden desc", "unread"], self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/entries?category_id=2112",                          (clone $c)->folder(2111),                                              $o,                        new ExceptionInput("idMissing"), false, V1::respError("MissingCategory")],
            ["/feeds/42/entries",                                  (clone $c)->subscription(42),                                          $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/feeds/42/entries?category_id=47",                   (clone $c)->subscription(42)->folder(46),                              $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/feeds/2112/entries",                                (clone $c)->subscription(2112),                                        $o,                        new ExceptionInput("idMissing"), false, V1::respError("404", 404)],
            ["/categories/42/entries",                             (clone $c)->folder(41),                                                $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/categories/42/entries?category_id=47",              (clone $c)->folder(41),                                                $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/categories/42/entries?starred",                     (clone $c)->folder(41)->starred(true),                                 $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/categories/1/entries",                              (clone $c)->folderShallow(0),                                          $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof(self::ENTRIES_OUT), 'entries' => self::ENTRIES_OUT])],
            ["/categories/2112/entries",                           (clone $c)->folder(2111),                                              $o,                        new ExceptionInput("idMissing"), false, V1::respError("404", 404)],
        ];
    }

    /** @dataProvider provideSingleEntryQueries */
    public function testGetASingleEntry(string $url, Context $c, $out, ResponseInterface $exp): void {
        $this->dbMock->subscriptionPropertiesGet->returns($this->v(self::FEEDS[1]));
        if ($out instanceof \Exception) {
            $this->dbMock->articleList->throws($out);
        } else {
            $this->dbMock->articleList->returns(new Result($this->v($out)));
        }
        $this->assertMessage($exp, $this->req("GET", $url));
        if ($c) {
            $this->dbMock->articleList->calledWith(Arsse::$user->id, $this->equalTo($c), array_keys(self::ENTRIES[0]));
        } else {
            $this->dbMock->articleList->never()->called();
        }
        if ($out && is_array($out)) {
            $this->dbMock->subscriptionPropertiesGet->calledWith(Arsse::$user->id, 55);
        } else {
            $this->dbMock->subscriptionList->never()->called();
        }
    }

    public function provideSingleEntryQueries(): iterable {
        self::clearData();
        $c = new Context;
        return [
            ["/entries/42",                 (clone $c)->article(42),                     [self::ENTRIES[1]],                   HTTP::respJson(self::ENTRIES_OUT[1])],
            ["/entries/2112",               (clone $c)->article(2112),                   new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            ["/feeds/47/entries/42",        (clone $c)->subscription(47)->article(42),   [self::ENTRIES[1]],                   HTTP::respJson(self::ENTRIES_OUT[1])],
            ["/feeds/47/entries/44",        (clone $c)->subscription(47)->article(44),   [],                                   V1::respError("404", 404)],
            ["/feeds/47/entries/2112",      (clone $c)->subscription(47)->article(2112), new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            ["/feeds/2112/entries/47",      (clone $c)->subscription(2112)->article(47), new ExceptionInput("idMissing"),      V1::respError("404", 404)],
            ["/categories/47/entries/42",   (clone $c)->folder(46)->article(42),         [self::ENTRIES[1]],                   HTTP::respJson(self::ENTRIES_OUT[1])],
            ["/categories/47/entries/44",   (clone $c)->folder(46)->article(44),         [],                                   V1::respError("404", 404)],
            ["/categories/47/entries/2112", (clone $c)->folder(46)->article(2112),       new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            ["/categories/2112/entries/47", (clone $c)->folder(2111)->article(47),       new ExceptionInput("idMissing"),      V1::respError("404", 404)],
            ["/categories/1/entries/42",    (clone $c)->folderShallow(0)->article(42),   [self::ENTRIES[1]],                   HTTP::respJson(self::ENTRIES_OUT[1])],
        ];
    }

    /** @dataProvider provideEntryMarkings */
    public function testMarkEntries(array $in, ?array $data, ResponseInterface $exp): void {
        $this->dbMock->articleMark->returns(0);
        $this->assertMessage($exp, $this->req("PUT", "/entries", $in));
        if ($data) {
            $this->dbMock->articleMark->calledWith(Arsse::$user->id, $data, (new Context)->articles($in['entry_ids']));
        } else {
            $this->dbMock->articleMark->never()->called();
        }
    }

    public function provideEntryMarkings(): iterable {
        self::clearData();
        return [
            [['status' => "read"],                           null,                                 V1::respError(["MissingInputValue", 'field' => "entry_ids"], 422)],
            [['entry_ids' => [1]],                           null,                                 V1::respError(["MissingInputValue", 'field' => "status"], 422)],
            [['entry_ids' => [], 'status' => "read"],        null,                                 V1::respError(["MissingInputValue", 'field' => "entry_ids"], 422)],
            [['entry_ids' => 1, 'status' => "read"],         null,                                 V1::respError(["InvalidInputType", 'field' => "entry_ids", 'expected' => "array", 'actual' => "integer"], 422)],
            [['entry_ids' => ["1"], 'status' => "read"],     null,                                 V1::respError(["InvalidInputType", 'field' => "entry_ids", 'expected' => "integer", 'actual' => "string"], 422)],
            [['entry_ids' => [1], 'status' => 1],            null,                                 V1::respError(["InvalidInputType", 'field' => "status", 'expected' => "string", 'actual' => "integer"], 422)],
            [['entry_ids' => [0], 'status' => "read"],       null,                                 V1::respError(["InvalidInputValue", 'field' => "entry_ids"], 422)],
            [['entry_ids' => [1], 'status' => "reread"],     null,                                 V1::respError(["InvalidInputValue", 'field' => "status"], 422)],
            [['entry_ids' => [1, 2], 'status' => "read"],    ['read' => true,  'hidden' => false], HTTP::respEmpty(204)],
            [['entry_ids' => [1, 2], 'status' => "unread"],  ['read' => false, 'hidden' => false], HTTP::respEmpty(204)],
            [['entry_ids' => [1, 2], 'status' => "removed"], ['read' => true,  'hidden' => true],  HTTP::respEmpty(204)],
        ];
    }

    /** @dataProvider provideMassMarkings */
    public function testMassMarkEntries(string $url, Context $c, $out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            $this->dbMock->articleMark->throws($out);
        } else {
            $this->dbMock->articleMark->returns($out);
        }
        $this->assertMessage($exp, $this->req("PUT", $url));
        if ($out !== null) {
            $this->dbMock->articleMark->calledWith(Arsse::$user->id, ['read' => true], $this->equalTo($c));
        } else {
            $this->dbMock->articleMark->never()->called();
        }
    }

    public function provideMassMarkings(): iterable {
        self::clearData();
        $c = (new Context)->hidden(false);
        return [
            ["/users/42/mark-all-as-read",        $c,                             1123,                            HTTP::respEmpty(204)],
            ["/users/2112/mark-all-as-read",      $c,                             null,                            V1::respError("403", 403)],
            ["/feeds/47/mark-all-as-read",        (clone $c)->subscription(47),   2112,                            HTTP::respEmpty(204)],
            ["/feeds/2112/mark-all-as-read",      (clone $c)->subscription(2112), new ExceptionInput("idMissing"), V1::respError("404", 404)],
            ["/categories/47/mark-all-as-read",   (clone $c)->folder(46),         1337,                            HTTP::respEmpty(204)],
            ["/categories/2112/mark-all-as-read", (clone $c)->folder(2111),       new ExceptionInput("idMissing"), V1::respError("404", 404)],
            ["/categories/1/mark-all-as-read",    (clone $c)->folderShallow(0),   6666,                            HTTP::respEmpty(204)],
        ];
    }

    /** @dataProvider provideBookmarkTogglings */
    public function testToggleABookmark($before, ?bool $after, ResponseInterface $exp): void {
        $c = (new Context)->article(2112);
        $this->dbMock->articleMark->returns(1);
        if ($before instanceof \Exception) {
            $this->dbMock->articleCount->throws($before);
        } else {
            $this->dbMock->articleCount->returns($before);
        }
        $this->assertMessage($exp, $this->req("PUT", "/entries/2112/bookmark"));
        if ($after !== null) {
            Phony::inOrder(
                $this->dbMock->begin->calledWith(),
                $this->dbMock->articleCount->calledWith(Arsse::$user->id, (clone $c)->starred(false)),
                $this->dbMock->articleMark->calledWith(Arsse::$user->id, ['starred' => $after], $c),
                $this->transaction->commit->called()
            );
        } else {
            Phony::inOrder(
                $this->dbMock->begin->calledWith(),
                $this->dbMock->articleCount->calledWith(Arsse::$user->id, (clone $c)->starred(false))
            );
            $this->dbMock->articleMark->never()->called();
            $this->transaction->commit->never()->called();
        }
    }

    public function provideBookmarkTogglings(): iterable {
        self::clearData();
        return [
            [1,                                    true,  HTTP::respEmpty(204)],
            [0,                                    false, HTTP::respEmpty(204)],
            [new ExceptionInput("subjectMissing"), null,  V1::respError("404", 404)],
        ];
    }

    public function testRefreshAFeed(): void {
        $this->dbMock->subscriptionPropertiesGet->returns([]);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("PUT", "/feeds/47/refresh"));
        $this->dbMock->subscriptionPropertiesGet->calledWith(Arsse::$user->id, 47);
    }

    public function testRefreshAMissingFeed(): void {
        $this->dbMock->subscriptionPropertiesGet->throws(new ExceptionInput("subjectMissing"));
        $this->assertMessage(V1::respError("404", 404), $this->req("PUT", "/feeds/2112/refresh"));
        $this->dbMock->subscriptionPropertiesGet->calledWith(Arsse::$user->id, 2112);
    }

    public function testRefreshAllFeeds(): void {
        $this->assertMessage(HTTP::respEmpty(204), $this->req("PUT", "/feeds/refresh"));
    }

    /** @dataProvider provideImports */
    public function testImport($out, ResponseInterface $exp): void {
        $opml = $this->mock(OPML::class);
        $this->objMock->get->with(OPML::class)->returns($opml);
        $action = ($out instanceof \Exception) ? "throws" : "returns";
        $opml->import->$action($out);
        $this->assertMessage($exp, $this->req("POST", "/import", "IMPORT DATA"));
        $opml->import->calledWith(Arsse::$user->id, "IMPORT DATA");
    }

    public function provideImports(): iterable {
        self::clearData();
        return [
            [new ImportException("invalidSyntax"),                              V1::respError("InvalidBodyXML", 400)],
            [new ImportException("invalidSemantics"),                           V1::respError("InvalidBodyOPML", 422)],
            [new ImportException("invalidFolderName"),                          V1::respError("InvalidImportCategory", 422)],
            [new ImportException("invalidFolderCopy"),                          V1::respError("DuplicateImportCategory", 422)],
            [new ImportException("invalidTagName"),                             V1::respError("InvalidImportLabel", 422)],
            [new FeedException("invalidUrl", ['url' => "http://example.com/"]), V1::respError(["FailedImportFeed", 'url' => "http://example.com/", 'code' => 10502], 502)],
            [true,                                                              HTTP::respJson(['message' => Arsse::$lang->msg("API.Miniflux.ImportSuccess")])],
        ];
    }

    public function testExport(): void {
        $opml = $this->mock(OPML::class);
        $this->objMock->get->with(OPML::class)->returns($opml);
        $opml->export->returns("<EXPORT_DATA/>");
        $this->assertMessage(HTTP::respText("<EXPORT_DATA/>", 200, ['Content-Type' => "application/xml"]), $this->req("GET", "/export"));
        $opml->export->calledWith(Arsse::$user->id);
    }
}
