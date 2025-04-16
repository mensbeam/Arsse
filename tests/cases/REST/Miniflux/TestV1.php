<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Miniflux\V1::class)]
class TestV1 extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const NOW = "2020-12-09T22:35:10.023419Z";
    protected const TOKEN = "Tk2o9YubmZIL2fm2w8Z4KlDEQJz532fNSOcTG0s2_xc=";
    protected const USERS = [
        ['num' => 1, 'admin' => true,  'lang' => "fr_CA", 'tz' => "Asia/Gaza", 'root_folder_name' => "Uncategorized"],
        ['num' => 2, 'admin' => false, 'lang' => null,    'tz' => null,        'root_folder_name' => null],
        ['num' => 3, 'admin' => true,  'lang' => "fr_CA", 'tz' => "Asia/Gaza", 'root_folder_name' => "Uncategorized"],
        ['num' => 4, 'admin' => false, 'lang' => null,    'tz' => null,        'root_folder_name' => null],
    ];
    protected const USERS_META = [
        ['theme' => "system_serif", 'sort_asc' => false, 'page_size' => 200,  'shortcuts' => false, 'reading_time' => false, 'gestures' => false, 'stylesheet' => "p {}"],
        [],
        ['miniflux_prefs' => '{"id":1,"username":"john.doe@example.com","theme":"system_serif","language":"fr_CA","timezone":"Asia/Gaza","entry_sorting_direction":"desc","entries_per_page":200,"keyboard_shortcuts":false,"show_reading_time":false,"entry_swipe":false,"stylesheet":"p {}"}'],
        ['miniflux_prefs' => '{"gesture_nav":"none"}'],
    ];
    protected const USERS_OUT = [
        ['id' => 1, 'username' => "john.doe@example.com", 'last_login_at' => self::NOW, 'is_admin' => true,  'theme' => "system_serif", 'language' => "fr_CA", 'timezone' => "Asia/Gaza", 'entry_sorting_direction' => "desc", 'entries_per_page' => 200, 'keyboard_shortcuts' => false, 'show_reading_time' => false, 'entry_swipe' => false, 'stylesheet' => "p {}"],
        ['id' => 2, 'username' => "jane.doe@example.com", 'last_login_at' => self::NOW, 'is_admin' => false, 'theme' => "light_serif",  'language' => "en_US", 'timezone' => "UTC",       'entry_sorting_direction' => "asc",  'entries_per_page' => 100, 'keyboard_shortcuts' => true,  'show_reading_time' => true,  'entry_swipe' => true,  'stylesheet' => ""],
        ['id' => 3, 'username' => "juan.doe@example.com", 'last_login_at' => self::NOW, 'is_admin' => true,  'theme' => "system_serif", 'language' => "fr_CA", 'timezone' => "Asia/Gaza", 'entry_sorting_direction' => "desc", 'entries_per_page' => 200, 'keyboard_shortcuts' => false, 'show_reading_time' => false, 'entry_swipe' => false, 'stylesheet' => "p {}"],
        ['id' => 4, 'username' => "baby.doe@example.com", 'last_login_at' => self::NOW, 'is_admin' => false, 'theme' => "light_serif",  'language' => "en_US", 'timezone' => "UTC",       'entry_sorting_direction' => "asc",  'entries_per_page' => 100, 'keyboard_shortcuts' => true,  'show_reading_time' => true,  'entry_swipe' => true,  'stylesheet' => "", 'gesture_nav' => "none"],
    ];
    protected const USER_OUT_STATIC = [
        'id'                                   => null,
        'username'                             => null,
        'is_admin'                             => null,
        'theme'                                => "light_serif",
        'language'                             => "en_US",
        'timezone'                             => "UTC",
        'entry_sorting_direction'              => "asc",
        'entry_sorting_order'                  => "published_at",
        'stylesheet'                           => "",
        'custom_js'                            => "",
        'external_font_hosts'                  => "",
        'google_id'                            => "",
        'openid_connect_id'                    => "",
        'entries_per_page'                     => 100,
        'keyboard_shortcuts'                   => true,
        'show_reading_time'                    => true,
        'entry_swipe'                          => true,
        'gesture_nav'                          => "tap",
        'last_login_at'                        => null,
        'display_mode'                         => "standalone",
        'default_reading_speed'                => 265,
        'cjk_reading_speed'                    => 500,
        'default_home_page'                    => "unread",
        'categories_sorting_order'             => "unread_count",
        'mark_read_on_view'                    => true,
        'mark_read_on_media_player_completion' => false,
        'media_playback_rate'                  => 1,
        'block_filter_entry_rules'             => "",
        'keep_filter_entry_rules'              => "",
    ];
    protected const FEEDS = [
        ['id' => 1,  'feed' => 1,  'url' => "http://example.com/ook",                      'title' => "Ook", 'source' => "http://example.com/", 'icon_id' => 47,   'icon_url' => "http://example.com/icon", 'folder' => 2112, 'top_folder' => 5,    'folder_name' => "Cat Eek", 'top_folder_name' => "Cat Ook", 'pinned' => 0, 'err_count' => 1, 'err_msg' => "Oopsie", 'order_type' => 0, 'keep_rule' => "this|that", 'block_rule' => "both", 'added' => "2020-12-21 21:12:00", 'updated' => "2021-01-05 13:51:32", 'edited' => "2021-01-01 00:00:00", 'modified' => "2020-11-30 04:08:52", 'next_fetch' => "2021-01-20 00:00:00", 'etag' => "OOKEEK", 'scrape' => 0, 'user_agent' => null,             'cookie' => null,  'unread' => 42, 'read' => 5],
        ['id' => 55, 'feed' => 55, 'url' => "http://j%20k:super%20secret@example.com/eek", 'title' => "Eek", 'source' => "http://example.com/", 'icon_id' => null, 'icon_url' => null,                      'folder' => null, 'top_folder' => null, 'folder_name' => null,      'top_folder_name' => null,      'pinned' => 0, 'err_count' => 0, 'err_msg' => null,     'order_type' => 0, 'keep_rule' => null,        'block_rule' => null,   'added' => "2020-12-21 21:12:00", 'updated' => "2021-01-05 13:51:32", 'edited' => null,                  'modified' => "2020-11-30 04:08:52", 'next_fetch' => null,                  'etag' => null,     'scrape' => 1, 'user_agent' => "Miniflux/2.2.7", 'cookie' => "a=b", 'unread' => 0,  'read' => 2112],
    ];
    protected const FEEDS_OUT = [
        ['id' => 1,  'user_id' => 42, 'feed_url' => "http://example.com/ook", 'site_url' => "http://example.com/", 'title' => "Ook", 'checked_at' => "2021-01-05T15:51:32.000000+02:00", 'next_check_at' => "2021-01-20T02:00:00.000000+02:00", 'etag_header' => "OOKEEK", 'last_modified_header' => "Fri, 01 Jan 2021 00:00:00 GMT", 'parsing_error_message' => "Oopsie", 'parsing_error_count' => 1, 'crawler' => false, 'blocklist_rules' => "both", 'keeplist_rules' => "this|that", 'user_agent' => "",               'cookie' => "",    'username' => "",    'password' => "",             'disabled' => false, 'category' => ['id' => 6, 'title' => "Cat Ook", 'user_id' => 42, 'hide_globally' => false], 'icon' => ['feed_id' => 1,  'icon_id' => 47, 'external_icon_id' => "47"]],
        ['id' => 55, 'user_id' => 42, 'feed_url' => "http://example.com/eek", 'site_url' => "http://example.com/", 'title' => "Eek", 'checked_at' => "2021-01-05T15:51:32.000000+02:00", 'next_check_at' => "0001-01-01T00:00:00Z",             'etag_header' => "",       'last_modified_header' => "",                              'parsing_error_message' => "",       'parsing_error_count' => 0, 'crawler' => true,  'blocklist_rules' => "",     'keeplist_rules' => "",          'user_agent' => "Miniflux/2.2.7", 'cookie' => "a=b", 'username' => "j k", 'password' => "super secret", 'disabled' => false, 'category' => ['id' => 1,'title'  => "All",     'user_id' => 42, 'hide_globally' => false], 'icon' => ['feed_id' => 55, 'icon_id' => 0,  'external_icon_id' => ""]],
    ];
    protected const FEED_OUT_STATIC = [
        'id'                             => null,
        'user_id'                        => null,
        'feed_url'                       => null,
        'site_url'                       => null,
        'title'                          => null,
        'description'                    => "",
        'checked_at'                     => null,
        'next_check_at'                  => null,
        'etag_header'                    => null,
        'last_modified_header'           => null,
        'parsing_error_message'          => null,
        'parsing_error_count'            => null,
        'scraper_rules'                  => "",
        'rewrite_rules'                  => "",
        'crawler'                        => null,
        'blocklist_rules'                => null,
        'keeplist_rules'                 => null,
        'urlrewrite_rules'               => "",
        'user_agent'                     => null,
        'cookie'                         => null,
        'username'                       => null,
        'password'                       => null,
        'disabled'                       => false,
        'no_media_player'                => false,
        'ignore_http_cache'              => false,
        'allow_self_signed_certificates' => false,
        'fetch_via_proxy'                => false,
        'hide_globally'                  => false,
        'disable_http2'                  => false,
        'apprise_service_urls'           => "",
        'webhook_url'                    => "",
        'ntfy_enabled'                   => false,
        'ntfy_priority'                  => 3,
        'ntfy_topic'                     => "",
        'category'                       => null,
        'icon'                           => null,
    ];
    protected const ENTRIES = [
        ['id' => 42,   'url' => "http://example.com/42",   'title' => "Title 42",   'subscription' => 55, 'author' => "Thomas Costain", 'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'added_date' => "2021-01-22 13:44:47", 'modified_date' => "2021-01-22 21:12:42", 'starred' => 0, 'unread' => 0, 'hidden' => 0, 'content' => "Content 42",   'media_url' => null,                                'media_type' => null],
        ['id' => 44,   'url' => "http://example.com/44",   'title' => "Title 44",   'subscription' => 55, 'author' => null,             'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'added_date' => "2021-01-22 13:44:47", 'modified_date' => "2021-01-22 21:12:42", 'starred' => 1, 'unread' => 1, 'hidden' => 0, 'content' => "Content 44",   'media_url' => "http://example.com/44/enclosure",   'media_type' => null],
        ['id' => 47,   'url' => "http://example.com/47",   'title' => "Title 47",   'subscription' => 55, 'author' => null,             'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'added_date' => "2021-01-22 13:44:47", 'modified_date' => "2021-01-22 21:12:42", 'starred' => 0, 'unread' => 1, 'hidden' => 1, 'content' => "Content 47",   'media_url' => "http://example.com/47/enclosure",   'media_type' => ""],
        ['id' => 2112, 'url' => "http://example.com/2112", 'title' => "Title 2112", 'subscription' => 55, 'author' => null,             'fingerprint' => "FINGERPRINT", 'published_date' => "2021-01-22 02:21:12", 'added_date' => "2021-01-22 13:44:47", 'modified_date' => "2021-01-22 21:12:42", 'starred' => 0, 'unread' => 0, 'hidden' => 1, 'content' => "Content 2112", 'media_url' => "http://example.com/2112/enclosure", 'media_type' => "image/png"],
    ];
    protected const TAGS = [
        42   => ["History", "Canada"],
        44   => [],
        47   => ["News", "Tech"],
        2112 => ["Travel"],
    ];
    protected const ENTRIES_OUT = [
        ['id' => 42,   'user_id' => 42, 'feed_id' => 55, 'status' => "read",    'hash' => "FINGERPRINT", 'title' => "Title 42",   'url' => "http://example.com/42",   'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'changed_at' => "2021-01-22T23:12:42.000000+02:00", 'content' => "Content 42",   'author' => "Thomas Costain", 'share_code' => "", 'starred' => false, 'reading_time' => 0, 'enclosures' => [],                                                                                                                                                                                    'feed' => 1, 'tags' => self::TAGS[42]],
        ['id' => 44,   'user_id' => 42, 'feed_id' => 55, 'status' => "unread",  'hash' => "FINGERPRINT", 'title' => "Title 44",   'url' => "http://example.com/44",   'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'changed_at' => "2021-01-22T23:12:42.000000+02:00", 'content' => "Content 44",   'author' => "",               'share_code' => "", 'starred' => true,  'reading_time' => 0, 'enclosures' => [['id' => 44,   'user_id' => 42, 'entry_id' => 44,   'url' => "http://example.com/44/enclosure",   'mime_type' => "application/octet-stream", 'size' => 0, 'media_progression' => 0]], 'feed' => 1, 'tags' => self::TAGS[44]],
        ['id' => 47,   'user_id' => 42, 'feed_id' => 55, 'status' => "removed", 'hash' => "FINGERPRINT", 'title' => "Title 47",   'url' => "http://example.com/47",   'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'changed_at' => "2021-01-22T23:12:42.000000+02:00", 'content' => "Content 47",   'author' => "",               'share_code' => "", 'starred' => false, 'reading_time' => 0, 'enclosures' => [['id' => 47,   'user_id' => 42, 'entry_id' => 47,   'url' => "http://example.com/47/enclosure",   'mime_type' => "application/octet-stream", 'size' => 0, 'media_progression' => 0]], 'feed' => 1, 'tags' => self::TAGS[47]],
        ['id' => 2112, 'user_id' => 42, 'feed_id' => 55, 'status' => "removed", 'hash' => "FINGERPRINT", 'title' => "Title 2112", 'url' => "http://example.com/2112", 'comments_url' => "", 'published_at' => "2021-01-22T04:21:12+02:00", 'created_at' => "2021-01-22T15:44:47.000000+02:00", 'changed_at' => "2021-01-22T23:12:42.000000+02:00", 'content' => "Content 2112", 'author' => "",               'share_code' => "", 'starred' => false, 'reading_time' => 0, 'enclosures' => [['id' => 2112, 'user_id' => 42, 'entry_id' => 2112, 'url' => "http://example.com/2112/enclosure", 'mime_type' => "image/png",                'size' => 0, 'media_progression' => 0]], 'feed' => 1, 'tags' => self::TAGS[2112]],
    ];

    protected $h;
    protected $transaction;

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
        parent::setUp();
        self::setConf();
        $this->transaction = \Phake::mock(Transaction::class);
        // create mock timestamps
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($this->transaction);
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['num' => 42, 'admin' => false, 'root_folder_name' => null, 'tz' => "Asia/Gaza"]);
        \Phake::when(Arsse::$user)->begin->thenReturn($this->transaction);
        //initialize a handler
        $this->h = new V1;
    }

    protected static function v($value) {
        return $value;
    }

    protected static function userssOut(): array {
        $out = [];
        foreach (self::USERS_OUT as $u) {
            $a = self::USER_OUT_STATIC;
            foreach (self::USER_OUT_STATIC as $k => $v) {
                if (isset($u[$k])) {
                    $a[$k] = $u[$k];
                }
            }
            $out[] = $a;
        }
        return $out;
    }

    protected static function feedsOut(): array {
        return array_map(function($v) {
            return array_merge(self::FEED_OUT_STATIC, $v);
        }, self::FEEDS_OUT);
    }

    public function testGenerateErrorResponse() {
        $act = V1::respError(["DuplicateUser", 'user' => "john.doe"], 409, ['Cache-Control' => "no-store"]);
        $exp = HTTP::respJson(['error_message' => 'The user name "john.doe" already exists'], 409, ['Cache-Control' => "no-store"]);
        $this->assertMessage($exp, $act);
    }

    #[DataProvider("provideAuthResponses")]
    public function testAuthenticateAUser($token, bool $auth, bool $success): void {
        $exp = $success ? HTTP::respEmpty(404) : V1::respError("401", 401);
        $user = "john.doe@example.com";
        if ($token !== null) {
            $headers = ['X-Auth-Token' => $token];
        } else {
            $headers = [];
        }
        Arsse::$user->id = null;
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("miniflux.login", self::TOKEN)->thenReturn(['user' => $user]);
        $this->assertMessage($exp, $this->req("GET", "/", "", $headers, $auth ? "john.doe@example.com" : null));
        $this->assertSame($success ? $user : null, Arsse::$user->id);
    }

    public static function provideAuthResponses(): iterable {
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

    #[DataProvider("provideInvalidPaths")]
    public function testRespondToInvalidPaths($path, $method, $code, $allow = null): void {
        $exp = HTTP::respEmpty($code, $allow ? ['Allow' => $allow] : []);
        $this->assertMessage($exp, $this->req($method, $path));
    }

    public static function provideInvalidPaths(): array {
        return [
            ["/",                  "GET",     404],
            ["/",                  "OPTIONS", 404],
            ["/me",                "POST",    405, "GET"],
            ["/me/",               "GET",     404],
        ];
    }

    #[DataProvider("provideOptionsRequests")]
    public function testRespondToOptionsRequests(string $url, string $allow, string $accept): void {
        $exp = HTTP::challenge(HTTP::respEmpty(204, [
            'Allow'  => $allow,
            'Accept' => $accept,
        ]));
        $this->assertMessage($exp, $this->req("OPTIONS", $url));
    }

    public static function provideOptionsRequests(): array {
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

    #[DataProvider("provideDiscoveries")]
    public function testDiscoverFeeds($in, ResponseInterface $exp): void {
        $this->assertMessage($exp, $this->req("POST", "/discover", ['url' => $in]));
    }

    public static function provideDiscoveries(): iterable {
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

    #[DataProvider("provideUserQueries")]
    public function testQueryUsers(bool $admin, string $route, ResponseInterface $exp): void {
        $user = $admin ? "john.doe@example.com" : "jane.doe@example.com";
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->list->thenReturn(["john.doe@example.com", "jane.doe@example.com", "juan.doe@example.com", "baby.doe@example.com", "admin@example.com"]);
        \Phake::when(Arsse::$user)->propertiesGet->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$user)->propertiesGet("john.doe@example.com")->thenReturn(self::USERS[0]);
        \Phake::when(Arsse::$user)->propertiesGet("jane.doe@example.com")->thenReturn(self::USERS[1]);
        \Phake::when(Arsse::$user)->propertiesGet("juan.doe@example.com")->thenReturn(self::USERS[2]);
        \Phake::when(Arsse::$user)->propertiesGet("baby.doe@example.com")->thenReturn(self::USERS[3]);
        \Phake::when(Arsse::$db)->userPropertiesGet->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$db)->userPropertiesGet("john.doe@example.com")->thenReturn(self::USERS_META[0]);
        \Phake::when(Arsse::$db)->userPropertiesGet("jane.doe@example.com")->thenReturn(self::USERS_META[1]);
        \Phake::when(Arsse::$db)->userPropertiesGet("juan.doe@example.com")->thenReturn(self::USERS_META[2]);
        \Phake::when(Arsse::$db)->userPropertiesGet("baby.doe@example.com")->thenReturn(self::USERS_META[3]);
        \Phake::when(Arsse::$user)->lookup->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$user)->lookup(1)->thenReturn("john.doe@example.com");
        \Phake::when(Arsse::$user)->lookup(2)->thenReturn("jane.doe@example.com");
        \Phake::when(Arsse::$user)->lookup(3)->thenReturn("juan.doe@example.com");
        \Phake::when(Arsse::$user)->lookup(4)->thenReturn("baby.doe@example.com");
        $this->assertMessage($exp, $this->req("GET", $route, "", [], $user));
    }

    public static function provideUserQueries(): iterable {
        self::clearData();
        return [
            [true,  "/users",                      HTTP::respJson(self::userssOut())],
            [true,  "/me",                         HTTP::respJson(self::userssOut()[0])],
            [true,  "/users/john.doe@example.com", HTTP::respJson(self::userssOut()[0])],
            [true,  "/users/1",                    HTTP::respJson(self::userssOut()[0])],
            [true,  "/users/jane.doe@example.com", HTTP::respJson(self::userssOut()[1])],
            [true,  "/users/2",                    HTTP::respJson(self::userssOut()[1])],
            [true,  "/users/juan.doe@example.com", HTTP::respJson(self::userssOut()[2])],
            [true,  "/users/3",                    HTTP::respJson(self::userssOut()[2])],
            [true,  "/users/jack.doe@example.com", V1::respError("404", 404)],
            [true,  "/users/47",                   V1::respError("404", 404)],
            [false, "/users",                      V1::respError("403", 403)],
            [false, "/me",                         HTTP::respJson(self::userssOut()[1])],
            [false, "/users/john.doe@example.com", V1::respError("403", 403)],
            [false, "/users/1",                    V1::respError("403", 403)],
            [false, "/users/jane.doe@example.com", V1::respError("403", 403)],
            [false, "/users/2",                    V1::respError("403", 403)],
            [false, "/users/juan.doe@example.com", V1::respError("403", 403)],
            [false, "/users/3",                    V1::respError("403", 403)],
            [false, "/users/jack.doe@example.com", V1::respError("403", 403)],
            [false, "/users/47",                   V1::respError("403", 403)],
        ];
    }

    #[DataProvider("provideUserModifications")]
    public function testModifyAUser(bool $admin, string $url, array $body, $metaIn, $prefsIn, $passwordOut, $renameOut, ResponseInterface $exp): void {
        $subject = self::USERS_OUT[array_reverse(explode("/", $url))[0] - 1]['username'] ?? "";
        \Phake::when(Arsse::$user)->lookup->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$user)->lookup(1)->thenReturn("john.doe@example.com");
        \Phake::when(Arsse::$user)->lookup(2)->thenReturn("jane.doe@example.com");
        \Phake::when(Arsse::$user)->lookup(3)->thenReturn("juan.doe@example.com");
        \Phake::when(Arsse::$user)->lookup(4)->thenReturn("baby.doe@example.com");
        \Phake::when(Arsse::$user)->propertiesGet->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$user)->propertiesGet("john.doe@example.com")->thenReturn(array_merge(self::USERS[0], ['admin' => $admin]));
        \Phake::when(Arsse::$user)->propertiesGet("jane.doe@example.com")->thenReturn(self::USERS[1]);
        \Phake::when(Arsse::$user)->propertiesGet("juan.doe@example.com")->thenReturn(self::USERS[2]);
        \Phake::when(Arsse::$user)->propertiesGet("baby.doe@example.com")->thenReturn(self::USERS[3]);
        \Phake::when(Arsse::$db)->userPropertiesGet("john.doe@example.com")->thenReturn(self::USERS_META[0]);
        \Phake::when(Arsse::$db)->userPropertiesGet("jane.doe@example.com")->thenReturn(self::USERS_META[1]);
        \Phake::when(Arsse::$db)->userPropertiesGet("juan.doe@example.com")->thenReturn(self::USERS_META[2]);
        \Phake::when(Arsse::$db)->userPropertiesGet("baby.doe@example.com")->thenReturn(self::USERS_META[3]);
        \Phake::when(Arsse::$user)->rename->thenReturn($renameOut ?? false);
        \Phake::when(Arsse::$user)->passwordSet->thenReturn($passwordOut ?? "");
        \Phake::when(Arsse::$user)->propertiesGet("ook")->thenReturn(array_merge(self::USERS[0], ['admin' => $admin]));
        \Phake::when(Arsse::$db)->userPropertiesGet("ook")->thenReturn(array_merge(self::USERS_META[0], []));
        if ($renameOut instanceof \Exception) {
            \Phake::when(Arsse::$user)->rename->thenThrow($renameOut);
        }
        if (isset($body['timezone']) && !preg_match('/^(UTC|[A-Z][a-z]*\/[A-Z]([a-z]*|_[A-Z])*)$/', $body['timezone'])) {
            \Phake::when(Arsse::$user)->propertiesSet->thenThrow(new UserExceptionInput("invalidTimezone"));
        }
        Arsse::$user->id = "john.doe@example.com";
        $this->assertMessage($exp, $this->req("PUT", $url, $body));
        if (isset($body['username'])) {
            \Phake::verify(Arsse::$user)->rename($subject, $body['username']);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->rename(\Phake::anyParameters());
        }
        if (isset($body['password'])) {
            \Phake::verify(Arsse::$user)->passwordSet($body['username'] ?? $subject, $body['password']);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->passwordSet(\Phake::anyParameters());
        }
        if (isset($metaIn)) {
            \Phake::verify(Arsse::$user)->propertiesSet($body['username'] ?? $subject, $metaIn);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->propertiesSet(\Phake::anyParameters());
        }
        if (isset($prefsIn)) {
            \Phake::verify(Arsse::$db)->userPropertiesSet($body['username'] ?? $subject, $prefsIn);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->userPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideUserModifications(): iterable {
        self::clearData();
        return [
            [true,  "/users/9", ['theme' => "dark_sans_serif"],             null,                null,                                                                null,  null,                                      V1::respError("404", 404)],
            [false, "/users/1", ['is_admin' => 0],                          null,                null,                                                                null,  null,                                      V1::respError(["InvalidInputType", 'field' => "is_admin", 'expected' => "boolean", 'actual' => "integer"], 422)],
            [false, "/users/1", ['entry_sorting_direction' => "bad"],       null,                null,                                                                null,  null,                                      V1::respError(["InvalidInputValue", 'field' => "entry_sorting_direction"], 422)],
            [false, "/users/1", ['username' => "j:k"],                      null,                null,                                                                null,  new UserExceptionInput("invalidUsername"), V1::respError(["InvalidInputValue", 'field' => "username"], 422)],
            [false, "/users/1", ['username' => "juan.doe@example.com"],     null,                null,                                                                null,  new ExceptionConflict("alreadyExists"),    V1::respError(["DuplicateUser", 'user' => "juan.doe@example.com"], 409)],
            [false, "/users/2", ['theme' => "dark_serif"],                  null,                null,                                                                null,  null,                                      V1::respError("403", 403)],
            [false, "/users/1", ['is_admin' => true],                       null,                null,                                                                null,  null,                                      V1::respError("InvalidElevation", 403)],
            [false, "/users/1", ['password' => "ook"],                      null,                null,                                                                "ook", null,                                      HTTP::respJson(array_merge(self::userssOut()[0], ['is_admin' => false, 'password' => "ook"]), 201)],
            [false, "/users/1", ['username' => "ook", 'password' => "ook"], null,                null,                                                                "ook", true,                                      HTTP::respJson(array_merge(self::userssOut()[0], ['is_admin' => false, 'username' => "ook", 'password' => "ook"]), 201)],
            [false, "/users/1", ['timezone' => "Ook"],                      ['tz' => "Ook"],     null,                                                                null,  null,                                      V1::respError(["InvalidInputValue", 'field' => "timezone"], 422)],
            [true,  "/users/2", ['language' => "fr_CA"],                    ['lang' => "fr_CA"], null,                                                                null,  null,                                      HTTP::respJson(array_merge(self::userssOut()[1], ['language' => "fr_CA"]), 201)],
            [true,  "/users/2", ['theme' => "dark_sans_serif"],             null,                ['miniflux_prefs' => '{"theme":"dark_sans_serif"}'],                 null,  null,                                      HTTP::respJson(array_merge(self::userssOut()[1], ['theme' => "dark_sans_serif"]), 201)],
            [true,  "/users/4", ['theme' => "dark_serif"],                  null,                ['miniflux_prefs' => '{"theme":"dark_serif","gesture_nav":"none"}'], null,  null,                                      HTTP::respJson(array_merge(self::userssOut()[3], ['theme' => "dark_serif"]), 201)],
        ];
    }

    #[DataProvider("provideUserAdditions")]
    public function testAddAUser(array $body, $addOut, ResponseInterface $exp): void {
        \Phake::when(Arsse::$user)->add->thenReturn($addOut ?? "");
        \Phake::when(Arsse::$user)->propertiesGet->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$user)->propertiesGet("john.doe@example.com")->thenReturn(self::USERS[0]);
        \Phake::when(Arsse::$user)->propertiesGet("ook")->thenReturn(array_merge(self::USERS[1], []));
        \Phake::when(Arsse::$db)->userPropertiesGet("ook")->thenReturn(array_merge(self::USERS_META[1], []));
        if (isset($body['timezone']) && !preg_match('/^(UTC|[A-Z][a-z]*\/[A-Z]([a-z]*|_[A-Z])*)$/', $body['timezone'])) {
            \Phake::when(Arsse::$user)->propertiesSet->thenThrow(new UserExceptionInput("invalidTimezone"));
        }
        if ($addOut instanceof \Exception) {
            \Phake::when(Arsse::$user)->add->thenThrow($addOut);
        }
        Arsse::$user->id = "john.doe@example.com";
        $this->assertMessage($exp, $this->req("POST", "/users", $body));
        if ($addOut) {
            \Phake::verify(Arsse::$user)->add($body['username'], $body['password']);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->add(\Phake::anyParameters());
        }
    }

    public static function provideUserAdditions(): iterable {
        return [
            [[],                                                                   null,                                      V1::respError(["MissingInputValue", 'field' => "username"], 422)],
            [['username' => "ook"],                                                null,                                      V1::respError(["MissingInputValue", 'field' => "password"], 422)],
            [['username' => "ook", 'password' => "eek"],                           new ExceptionConflict("alreadyExists"),    V1::respError(["DuplicateUser", 'user' => "ook"], 409)],
            [['username' => "j:k", 'password' => "eek"],                           new UserExceptionInput("invalidUsername"), V1::respError(["InvalidInputValue", 'field' => "username"], 422)],
            [['username' => "ook", 'password' => "eek", 'timezone' => "ook"],      "eek",                                     V1::respError(["InvalidInputValue", 'field' => "timezone"], 422)],
            [['username' => "ook", 'password' => "eek", 'theme' => "dark_serif"],  "eek",                                     HTTP::respJson(array_merge(self::userssOut()[1], ['username' => 'ook', 'theme' => "dark_serif", 'password' => "eek"]), 201)],
        ];
    }

    public function testAddAUserWithoutAuthority(): void {
        $this->assertMessage(V1::respError("403", 403), $this->req("POST", "/users", []));
    }

    public function testDeleteAUser(): void {
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => true]);
        \Phake::when(Arsse::$user)->lookup->thenReturn("john.doe@example.com");
        \Phake::when(Arsse::$user)->remove->thenReturn(true);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/users/2112"));
        \Phake::verify(Arsse::$user)->lookup(2112);
        \Phake::verify(Arsse::$user)->remove("john.doe@example.com");
    }

    public function testDeleteAMissingUser(): void {
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => true]);
        \Phake::when(Arsse::$user)->lookup->thenThrow(new ExceptionConflict("doesNotExist"));
        \Phake::when(Arsse::$user)->remove->thenReturn(true);
        $this->assertMessage(V1::respError("404", 404), $this->req("DELETE", "/users/2112"));
        \Phake::verify(Arsse::$user)->lookup(2112);
        \Phake::verify(Arsse::$user, \Phake::never())->remove($this->anything());
    }

    public function testDeleteAUserWithoutAuthority(): void {
        $this->assertMessage(V1::respError("403", 403), $this->req("DELETE", "/users/2112"));
        \Phake::verify(Arsse::$user, \Phake::never())->lookup($this->anything());
        \Phake::verify(Arsse::$user, \Phake::never())->remove($this->anything());
    }

    public function testListCategories(): void {
        \Phake::when(Arsse::$db)->folderList->thenReturn(new Result(self::v([
            ['id' => 1,  'name' => "Science"],
            ['id' => 20, 'name' => "Technology"],
        ])));
        $exp = HTTP::respJson([
            ['id' => 1,  'title' => "All",        'user_id' => 42, 'hide_globally' => false],
            ['id' => 2,  'title' => "Science",    'user_id' => 42, 'hide_globally' => false],
            ['id' => 21, 'title' => "Technology", 'user_id' => 42, 'hide_globally' => false],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories"));
        \Phake::verify(Arsse::$db)->folderList("john.doe@example.com", null, false);
        // run test again with a renamed root folder
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['num' => 47, 'admin' => false, 'root_folder_name' => "Uncategorized"]);
        $exp = HTTP::respJson([
            ['id' => 1,  'title' => "Uncategorized", 'user_id' => 47, 'hide_globally' => false],
            ['id' => 2,  'title' => "Science",       'user_id' => 47, 'hide_globally' => false],
            ['id' => 21, 'title' => "Technology",    'user_id' => 47, 'hide_globally' => false],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories"));
    }

    public function testListCategoriesWithCounts(): void {
        \Phake::when(Arsse::$db)->folderList->thenReturn(new Result(self::v([
            ['id' => 1,  'name' => "Science"   , 'feeds' => 0],
            ['id' => 20, 'name' => "Technology", 'feeds' => 3],
        ])));
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v([
            ['top_folder' => 20,   'unread' => 5],
            ['top_folder' => 20,   'unread' => 3],
            ['top_folder' => 20,   'unread' => 0],
            ['top_folder' => null, 'unread' => 20],
            ['top_folder' => null, 'unread' => 42],
        ])));
        $exp = HTTP::respJson([
            ['id' => 1,  'title' => "All",        'user_id' => 42, 'hide_globally' => false, 'feed_count' => 2, 'total_unread' => 62],
            ['id' => 2,  'title' => "Science",    'user_id' => 42, 'hide_globally' => false, 'feed_count' => 0, 'total_unread' => 0],
            ['id' => 21, 'title' => "Technology", 'user_id' => 42, 'hide_globally' => false, 'feed_count' => 3, 'total_unread' => 8],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories?counts=true"));
        \Phake::verify(Arsse::$db)->folderList("john.doe@example.com", null, false);
        // run test again with a renamed root folder
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['num' => 47, 'admin' => false, 'root_folder_name' => "Uncategorized"]);
        $exp = HTTP::respJson([
            ['id' => 1,  'title' => "Uncategorized", 'user_id' => 47, 'hide_globally' => false, 'feed_count' => 2, 'total_unread' => 62],
            ['id' => 2,  'title' => "Science",       'user_id' => 47, 'hide_globally' => false, 'feed_count' => 0, 'total_unread' => 0],
            ['id' => 21, 'title' => "Technology",    'user_id' => 47, 'hide_globally' => false, 'feed_count' => 3, 'total_unread' => 8],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/categories?counts=true"));
    }

    #[DataProvider("provideCategoryAdditions")]
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

    public static function provideCategoryAdditions(): iterable {
        self::clearData();
        return [
            ["New",       HTTP::respJson(['id' => 2112, 'title' => "New", 'user_id' => 42, 'hide_globally' => false], 201)],
            ["Duplicate", V1::respError(["DuplicateCategory", 'title' => "Duplicate"], 409)],
            ["",          V1::respError(["InvalidCategory", 'title' => ""], 422)],
            [" ",         V1::respError(["InvalidCategory", 'title' => " "], 422)],
            [null,        V1::respError(["MissingInputValue", 'field' => "title"], 422)],
            [false,       V1::respError(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
        ];
    }

    #[DataProvider("provideCategoryRenamings")]
    public function testRenameACategory(int $id, $title, $out, ResponseInterface $exp): void {
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['num' => 42, 'root_folder_name' => $title]);
        \Phake::when(Arsse::$db)->folderPropertiesGet->thenReturn(['id' => $id - 1, 'name' => $title]);
        if (is_string($out)) {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenThrow(new ExceptionInput($out));
        } else {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("PUT", "/categories/$id", ['title' => $title]));
        if ($id === 1 && strlen(trim((string) $title))) {
            \Phake::verify(Arsse::$user)->propertiesSet("john.doe@example.com", ['root_folder_name' => $title]);
            \Phake::verify(Arsse::$db)->folderPropertiesSet("john.doe@example.com", $id - 1, []);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->propertiesSet(\Phake::anyParameters());
            if (($id === 1 && strlen(trim((string) $title))) || ($id !== 1 && $title !== false)) {
                \Phake::verify(Arsse::$db)->folderPropertiesSet("john.doe@example.com", $id - 1, ['name' => $title]);
            } else {
                \Phake::verify(Arsse::$db, \Phake::never())->folderPropertiesSet(\Phake::anyParameters());
            }
        }
    }

    public static function provideCategoryRenamings(): iterable {
        self::clearData();
        return [
            [3, "New",       "subjectMissing",      V1::respError("404", 404)],
            [2, "New",       true,                  HTTP::respJson(['id' => 2, 'title' => "New", 'user_id' => 42, 'hide_globally' => false], 201)],
            [2, "Duplicate", "constraintViolation", V1::respError(["DuplicateCategory", 'title' => "Duplicate"], 409)],
            [2, "",          "missing",             V1::respError(["InvalidCategory", 'title' => ""], 422)],
            [2, " ",         "whitespace",          V1::respError(["InvalidCategory", 'title' => " "], 422)],
            [2, false,       "subjectMissing",      V1::respError(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
            [1, "New",       true,                  HTTP::respJson(['id' => 1, 'title' => "New", 'user_id' => 42, 'hide_globally' => false], 201)],
            [1, "",          "missing",             V1::respError(["InvalidCategory", 'title' => ""], 422)],
            [1, " ",         "whitespace",          V1::respError(["InvalidCategory", 'title' => " "], 422)],
            [1, false,       false,                 V1::respError(["InvalidInputType", 'field' => "title", 'actual' => "boolean", 'expected' => "string"], 422)],
        ];
    }

    #[DataProvider("provideCategoryModifications")]
    public function testModifyACategory(int $id, array $in, array $dbIn, $out, ResponseInterface $exp): void {
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['num' => 42, 'root_folder_name' => $in['title'] ?? "All"]);
        \Phake::when(Arsse::$db)->folderPropertiesGet->thenReturn(['id' => $id -1, 'name' => $in['title'] ?? "Existing"]);
        if (is_string($out)) {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenThrow(new ExceptionInput($out));
        } else {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("PUT", "/categories/$id", $in));
        if ($id === 1 && isset($in['title'])) {
            \Phake::verify(Arsse::$user)->propertiesSet("john.doe@example.com", ['root_folder_name' => $in['title']]);
        } else {
            \Phake::verify(Arsse::$user, \Phake::never())->propertiesSet(\Phake::anyParameters());
        }
        \Phake::verify(Arsse::$db)->folderPropertiesSet("john.doe@example.com", $id - 1, $dbIn);
    }

    public static function provideCategoryModifications(): iterable {
        self::clearData();
        return [
            [3, [],                        [],                "subjectMissing", V1::respError("404", 404)],
            [3, ['title' => "New"],        ['name' => "New"], true,             HTTP::respJson(['id' => 3, 'title' => "New", 'user_id' => 42, 'hide_globally' => false], 201)],
            [3, ['hide_globally' => true], [],                false,            HTTP::respJson(['id' => 3, 'title' => "Existing", 'user_id' => 42, 'hide_globally' => false], 201)],
        ];
    }

    public function testDeleteARealCategory(): void {
        \Phake::when(Arsse::$db)->folderRemove->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/categories/2112"));
        \Phake::verify(Arsse::$db)->folderRemove("john.doe@example.com", 2111);
        $this->assertMessage(V1::respError("404", 404), $this->req("DELETE", "/categories/47"));
        \Phake::verify(Arsse::$db)->folderRemove("john.doe@example.com", 46);
    }

    public function testDeleteTheSpecialCategory(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v([
            ['id' => 1],
            ['id' => 47],
            ['id' => 2112],
        ])));
        \Phake::when(Arsse::$db)->subscriptionRemove->thenReturn(true);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/categories/1"));
        \Phake::inOrder(
            \Phake::verify(Arsse::$db)->begin(),
            \Phake::verify(Arsse::$db)->subscriptionList("john.doe@example.com", null, false),
            \Phake::verify(Arsse::$db)->subscriptionRemove("john.doe@example.com", 1),
            \Phake::verify(Arsse::$db)->subscriptionRemove("john.doe@example.com", 47),
            \Phake::verify(Arsse::$db)->subscriptionRemove("john.doe@example.com", 2112),
            \Phake::verify($this->transaction)->commit()
        );
    }

    public function testListFeeds(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v(self::FEEDS)));
        $exp = HTTP::respJson(self::feedsOut());
        $this->assertMessage($exp, $this->req("GET", "/feeds"));
    }

    public function testListFeedsOfACategory(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v(self::FEEDS)));
        $exp = HTTP::respJson(self::feedsOut());
        $this->assertMessage($exp, $this->req("GET", "/categories/2112/feeds"));
        \Phake::verify(Arsse::$db)->subscriptionList(Arsse::$user->id, 2111, true);
    }

    public function testListFeedsOfTheRootCategory(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v(self::FEEDS)));
        $exp = HTTP::respJson(self::feedsOut());
        $this->assertMessage($exp, $this->req("GET", "/categories/1/feeds"));
        \Phake::verify(Arsse::$db)->subscriptionList(Arsse::$user->id, 0, false);
    }

    public function testListFeedsOfAMissingCategory(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenThrow(new ExceptionInput("idMissing"));
        $exp = V1::respError("404", 404);
        $this->assertMessage($exp, $this->req("GET", "/categories/2112/feeds"));
        \Phake::verify(Arsse::$db)->subscriptionList(Arsse::$user->id, 2111, true);
    }

    public function testGetAFeed(): void {
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn(self::v(self::FEEDS[0]))->thenReturn(self::v(self::FEEDS[1]));
        $this->assertMessage(HTTP::respJson(self::feedsOut()[0]), $this->req("GET", "/feeds/1"));
        $this->assertMessage(HTTP::respJson(self::feedsOut()[1]), $this->req("GET", "/feeds/55"));
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 1);
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 55);
    }

    public function testGetAMissingFeed(): void {
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenThrow(new ExceptionInput("subjectMissing"));
        $this->assertMessage(V1::respError("404", 404), $this->req("GET", "/feeds/1"));
        \Phake::verify(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 1);
    }

    #[DataProvider("provideFeedCreations")]
    public function testCreateAFeed(array $in, $out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionAdd->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->subscriptionAdd->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("POST", "/feeds", $in));
        if ($out) {
            $props = [
                'folder'     => ($in['category_id'] ?? 1) - 1,
                'scrape'     => $in['crawler'] ?? false,
                'keep_rule'  => $in['keeplist_rules'] ?? null,
                'block_rule' => $in['blocklist_rules'] ?? null,
                'username'   => $in['username'] ?? null,
                'password'   => $in['password'] ?? null,
                'user_agent' => $in['user_agent'] ?? null,
                'cookie'     => $in['cookie'] ?? null,

            ];
            \Phake::verify(Arsse::$db)->subscriptionAdd("john.doe@example.com", $in['feed_url'], false, $props);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionAdd(\Phake::anyParameters());
        }
    }

    public static function provideFeedCreations(): iterable {
        self::clearData();
        return [
            [['category_id' => 1],                                                                null,                                       V1::respError(["MissingInputValue", 'field' => "feed_url"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => "1"],                         null,                                       V1::respError(["InvalidInputType", 'field' => "category_id", 'expected' => "integer", 'actual' => "string"], 422)],
            [['feed_url' => "Not a URL", 'category_id' => 1],                                     null,                                       V1::respError(["InvalidInputValue", 'field' => "feed_url"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 0],                           null,                                       V1::respError(["InvalidInputValue", 'field' => "category_id"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'keeplist_rules' => "["],  null,                                       V1::respError(["InvalidInputValue", 'field' => "keeplist_rules"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'blocklist_rules' => "["], null,                                       V1::respError(["InvalidInputValue", 'field' => "blocklist_rules"], 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("internalError"),         V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("invalidCertificate"),    V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("invalidUrl"),            V1::respError("Fetch404", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("maxRedirect"),           V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("maxSize"),               V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("timeout"),               V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("forbidden"),             V1::respError("Fetch403", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("unauthorized"),          V1::respError("Fetch401", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("transmissionError"),     V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("connectionFailed"),      V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("malformedXml"),          V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("xmlEntity"),             V1::respError("FetchOther", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("subscriptionNotFound"),  V1::respError("Fetch404", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new FeedException("unsupportedFeedFormat"), V1::respError("FetchFormat", 502)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new ExceptionInput("constraintViolation"),  V1::respError("DuplicateFeed", 409)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           new ExceptionInput("idMissing"),            V1::respError("MissingCategory", 422)],
            [['feed_url' => "http://example.com/", 'category_id' => 1],                           44,                                         HTTP::respJson(['feed_id' => 44], 201)],
            [['feed_url' => "http://example.com/"],                                               44,                                         HTTP::respJson(['feed_id' => 44], 201)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'crawler' => true],        44,                                         HTTP::respJson(['feed_id' => 44], 201)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'keeplist_rules' => "^A"], 44,                                         HTTP::respJson(['feed_id' => 44], 201)],
            [['feed_url' => "http://example.com/", 'category_id' => 1, 'blocklist_rules' => "A"], 44,                                         HTTP::respJson(['feed_id' => 44], 201)],
        ];
    }

    #[DataProvider("provideFeedModifications")]
    public function testModifyAFeed(array $in, array $data, $out, ResponseInterface $exp): void {
        $this->h = \Phake::partialMock(V1::class);
        \Phake::when($this->h)->getFeed->thenReturn(HTTP::respJson(self::feedsOut()[0]));
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("PUT", "/feeds/2112", $in));
        \Phake::verify(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 2112, $this->identicalTo($data));
    }

    public static function provideFeedModifications(): iterable {
        self::clearData();
        $success = HTTP::respJson(self::feedsOut()[0], 201);
        return [
            [[],                                     [],                                    true,                                                        $success],
            [[],                                     [],                                    new ExceptionInput("subjectMissing"),                        V1::respError("404", 404)],
            [['title' => ""],                        ['title' => ""],                       new ExceptionInput("missing"),                               V1::respError("InvalidTitle", 422)],
            [['title' => " "],                       ['title' => " "],                      new ExceptionInput("whitespace"),                            V1::respError("InvalidTitle", 422)],
            [['title' => " "],                       ['title' => " "],                      new ExceptionInput("whitespace"),                            V1::respError("InvalidTitle", 422)],
            [['category_id' => 47],                  ['folder' => 46],                      new ExceptionInput("idMissing"),                             V1::respError("MissingCategory", 422)],
            [['crawler' => false],                   ['scrape' => false],                   true,                                                        $success],
            [['keeplist_rules' => ""],               ['keep_rule' => ""],                   true,                                                        $success],
            [['blocklist_rules' => "ook"],           ['block_rule' => "ook"],               true,                                                        $success],
            [['title' => "Ook!", 'crawler' => true], ['title' => "Ook!", 'scrape' => true], true,                                                        $success],
            [['feed_url' => "http://example.com"],   ['url' => "http://example.com"],       true,                                                        $success],
            [['username' => "ook"],                  ['username' => "ook"],                 true,                                                        $success],
            [['password' => "ook"],                  ['password' => "ook"],                 true,                                                        $success],
            [['user_agent' => "ook"],                ['user_agent' => "ook"],               true,                                                        $success],
            [['user_agent' => ""],                   ['user_agent' => null],                true,                                                        $success],
            [['username' => "ook:eek"],              ['username' => "ook:eek"],             new ExceptionInput("invalidValue", ['field' => "username"]), V1::respError(["InvalidInputValue", 'field' => "username"], 422)],
        ];
    }

    public function testModifyAFeedWithNoBody(): void {
        $this->h = \Phake::partialMock(V1::class);
        $feedOut = self::feedsOut()[0];
        \Phake::when($this->h)->getFeed->thenReturn(HTTP::respJson($feedOut));
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn(true);
        $this->assertMessage(HTTP::respJson($feedOut, 201), $this->req("PUT", "/feeds/2112", ""));
        \Phake::verify(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 2112, []);
    }

    public function testDeleteAFeed(): void {
        \Phake::when(Arsse::$db)->subscriptionRemove->thenReturn(true);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("DELETE", "/feeds/2112"));
        \Phake::verify(Arsse::$db)->subscriptionRemove(Arsse::$user->id, 2112);
    }

    public function testDeleteAMissingFeed(): void {
        \Phake::when(Arsse::$db)->subscriptionRemove->thenThrow(new ExceptionInput("subjectMissing"));
        $this->assertMessage(V1::respError("404", 404), $this->req("DELETE", "/feeds/2112"));
        \Phake::verify(Arsse::$db)->subscriptionRemove(Arsse::$user->id, 2112);
    }

    #[DataProvider("provideIcons")]
    public function testGetTheIconOfASubscription($out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionIcon->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->subscriptionIcon->thenReturn(self::v($out));
        }
        $this->assertMessage($exp, $this->req("GET", "/feeds/2112/icon"));
        \Phake::verify(Arsse::$db)->subscriptionIcon(Arsse::$user->id, 2112);
    }

    public static function provideIcons(): iterable {
        self::clearData();
        return [
            [['id' => 44, 'type' => "image/svg+xml", 'data' => "<svg/>"], HTTP::respJson(['id' => 44, 'mime_type' => "image/svg+xml", 'data' => "image/svg+xml;base64,PHN2Zy8+"])],
            [['id' => 47, 'type' => "",              'data' => "<svg/>"], V1::respError("404", 404)],
            [['id' => 47, 'type' => null,            'data' => "<svg/>"], V1::respError("404", 404)],
            [['id' => 47, 'type' => null,            'data' => null],     V1::respError("404", 404)],
            [null,                                                        V1::respError("404", 404)],
            [new ExceptionInput("subjectMissing"),                        V1::respError("404", 404)],
        ];
    }

    #[DataProvider("provideEntryQueries")]
    public function testGetEntries(string $url, ?RootContext $c, ?array $order, $out, bool $count, ResponseInterface $exp): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v(self::FEEDS)));
        \Phake::when(Arsse::$db)->articleCount->thenReturn(2112);
        \Phake::when(Arsse::$db)->articleCategoriesGet->thenReturnCallback(function($user, $id) {
            return self::TAGS[$id] ?? [];
        });
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleList->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->articleList->thenReturn(new Result(self::v($out)));
        }
        $this->assertMessage($exp, $this->req("GET", $url));
        if ($c) {
            \Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, $this->equalTo($c), array_keys(self::ENTRIES[0]), $order);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleList(\Phake::anyParameters());
        }
        if ($out && !$out instanceof \Exception) {
            \Phake::verify(Arsse::$db)->subscriptionList(Arsse::$user->id);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionList(\Phake::anyParameters());
        }
        if ($count) {
            \Phake::verify(Arsse::$db)->articleCount(Arsse::$user->id, $this->equalTo((clone $c)->limit(0)->offset(0)));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleCount(\Phake::anyParameters());
        }
    }

    public static function provideEntryQueries(): iterable {
        self::clearData();
        $entriesOut = array_map(function($v) {
            $v['feed'] = self::feedsOut()[$v['feed']];
            return $v;
        }, self::ENTRIES_OUT);
        $c = (new Context)->limit(100);
        $o = ["modified_date"]; // the default sort order
        return [
            ["/entries?after=A",                                   null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "after"], 400)],
            ["/entries?before=B",                                  null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "before"], 400)],
            ["/entries?published_after=A",                         null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "published_after"], 400)],
            ["/entries?published_before=B",                        null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "published_before"], 400)],
            ["/entries?changed_after=A",                           null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "changed_after"], 400)],
            ["/entries?changed_before=B",                          null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "changed_before"], 400)],
            ["/entries?category_id=0",                             null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "category_id"], 400)],
            ["/entries?after_entry_id=0",                          null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "after_entry_id"], 400)],
            ["/entries?before_entry_id=0",                         null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "before_entry_id"], 400)],
            ["/entries?limit=-1",                                  null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "limit"], 400)],
            ["/entries?offset=-1",                                 null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "offset"], 400)],
            ["/entries?direction=sideways",                        null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "direction"], 400)],
            ["/entries?order=false",                               null,                                                                  null,                      [],                              false, V1::respError(["InvalidInputValue", 'field' => "order"], 400)],
            ["/entries?starred=true&starred=true",                 null,                                                                  null,                      [],                              false, V1::respError(["DuplicateInputValue", 'field' => "starred"], 400)],
            ["/entries?after&after=0",                             null,                                                                  null,                      [],                              false, V1::respError(["DuplicateInputValue", 'field' => "after"], 400)],
            ["/entries?published_after&published_after=0",         null,                                                                  null,                      [],                              false, V1::respError(["DuplicateInputValue", 'field' => "published_after"], 400)],
            ["/entries?changed_after&changed_after=0",             null,                                                                  null,                      [],                              false, V1::respError(["DuplicateInputValue", 'field' => "changed_after"], 400)],
            ["/entries",                                           $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?category_id=47",                            (clone $c)->folder(46),                                                $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?category_id=1",                             (clone $c)->folderShallow(0),                                          $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=unread",                             (clone $c)->unread(true)->hidden(false),                               $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=read",                               (clone $c)->unread(false)->hidden(false),                              $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=removed",                            (clone $c)->hidden(true),                                              $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=unread&status=read",                 (clone $c)->hidden(false),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=unread&status=removed",              new UnionContext((clone $c)->unread(true), (clone $c)->hidden(true)),  $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=removed&status=read",                new UnionContext((clone $c)->unread(false), (clone $c)->hidden(true)), $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=removed&status=read&status=removed", new UnionContext((clone $c)->unread(false), (clone $c)->hidden(true)), $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?status=removed&status=read&status=unread",  $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?starred=true",                              (clone $c)->starred(true),                                             $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?starred=false",                             (clone $c)->starred(false),                                            $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?after=0",                                   $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?before=0",                                  $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?before=1",                                  (clone $c)->addedRange(null, 1),                                       $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?before=1&after=0",                          (clone $c)->addedRange(null, 1),                                       $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?published_after=0",                         $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?published_before=0",                        $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?published_before=1",                        (clone $c)->publishedRange(null, 1),                                   $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?published_before=1&published_after=0",      (clone $c)->publishedRange(null, 1),                                   $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?changed_after=0",                           $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?changed_before=0",                          $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?changed_before=1",                          (clone $c)->modifiedRange(null, 1),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?changed_before=1&changed_after=0",          (clone $c)->modifiedRange(null, 1),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?after_entry_id=42",                         (clone $c)->articleRange(43, null),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?before_entry_id=47",                        (clone $c)->articleRange(null, 46),                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?search=alpha%20beta",                       (clone $c)->searchTerms(["alpha", "beta"]),                            $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?limit=4",                                   (clone $c)->limit(4),                                                  $o,                        self::ENTRIES,                   true,  HTTP::respJson(['total' => 2112, 'entries' => $entriesOut])],
            ["/entries?offset=20",                                 (clone $c)->offset(20),                                                $o,                        [],                              true,  HTTP::respJson(['total' => 2112, 'entries' => []])],
            ["/entries?direction=asc",                             $c,                                                                    $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=id",                                  $c,                                                                    ["id"],                    self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=published_at",                        $c,                                                                    ["modified_date"],         self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=category_id",                         $c,                                                                    ["top_folder"],            self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=title",                               $c,                                                                    ["title"],                 self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=author",                              $c,                                                                    ["author"],                self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=category_title",                      $c,                                                                    ["top_folder_name"],       self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=status",                              $c,                                                                    ["hidden", "unread desc"], self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?direction=desc",                            $c,                                                                    ["modified_date desc"],    self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=id&direction=desc",                   $c,                                                                    ["id desc"],               self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=published_at&direction=desc",         $c,                                                                    ["modified_date desc"],    self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=category_id&direction=desc",          $c,                                                                    ["top_folder desc"],       self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=title&direction=desc",                $c,                                                                    ["title desc"],            self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=author&direction=desc",               $c,                                                                    ["author desc"],           self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=category_title&direction=desc",       $c,                                                                    ["top_folder_name desc"],  self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?order=status&direction=desc",               $c,                                                                    ["hidden desc", "unread"], self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/entries?category_id=2112",                          (clone $c)->folder(2111),                                              $o,                        new ExceptionInput("idMissing"), false, V1::respError("MissingCategory")],
            ["/feeds/42/entries",                                  (clone $c)->subscription(42),                                          $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/feeds/42/entries?category_id=47",                   (clone $c)->subscription(42)->folder(46),                              $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/feeds/2112/entries",                                (clone $c)->subscription(2112),                                        $o,                        new ExceptionInput("idMissing"), false, V1::respError("404", 404)],
            ["/categories/42/entries",                             (clone $c)->folder(41),                                                $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/categories/42/entries?category_id=47",              (clone $c)->folder(41),                                                $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/categories/42/entries?starred=true",                (clone $c)->folder(41)->starred(true),                                 $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/categories/1/entries",                              (clone $c)->folderShallow(0),                                          $o,                        self::ENTRIES,                   false, HTTP::respJson(['total' => sizeof($entriesOut), 'entries' => $entriesOut])],
            ["/categories/2112/entries",                           (clone $c)->folder(2111),                                              $o,                        new ExceptionInput("idMissing"), false, V1::respError("404", 404)],
        ];
    }

    #[DataProvider("provideSingleEntryQueries")]
    public function testGetASingleEntry(string $url, Context $c, $out, ResponseInterface $exp): void {
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn(self::v(self::FEEDS[1]));
        \Phake::when(Arsse::$db)->articleCategoriesGet->thenReturnCallback(function($user, $id) {
            return self::TAGS[$id] ?? [];
        });
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleList->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->articleList->thenReturn(new Result(self::v($out)));
        }
        $this->assertMessage($exp, $this->req("GET", $url));
        if ($c) {
            \Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, $this->equalTo($c), array_keys(self::ENTRIES[0]));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleList(\Phake::anyParameters());
        }
        if ($out && is_array($out)) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 55);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionList(\Phake::anyParameters());
        }
    }

    public static function provideSingleEntryQueries(): iterable {
        self::clearData();
        $entryOut = self::ENTRIES_OUT[0];
        $entryOut['feed'] = self::feedsOut()[$entryOut['feed']];
        $c = new Context;
        return [
            ["/entries/42",                 (clone $c)->article(42),                     [self::ENTRIES[0]],                   HTTP::respJson($entryOut)],
            ["/entries/2112",               (clone $c)->article(2112),                   new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            ["/feeds/47/entries/42",        (clone $c)->subscription(47)->article(42),   [self::ENTRIES[0]],                   HTTP::respJson($entryOut)],
            ["/feeds/47/entries/44",        (clone $c)->subscription(47)->article(44),   [],                                   V1::respError("404", 404)],
            ["/feeds/47/entries/2112",      (clone $c)->subscription(47)->article(2112), new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            ["/feeds/2112/entries/47",      (clone $c)->subscription(2112)->article(47), new ExceptionInput("idMissing"),      V1::respError("404", 404)],
            ["/categories/47/entries/42",   (clone $c)->folder(46)->article(42),         [self::ENTRIES[0]],                   HTTP::respJson($entryOut)],
            ["/categories/47/entries/44",   (clone $c)->folder(46)->article(44),         [],                                   V1::respError("404", 404)],
            ["/categories/47/entries/2112", (clone $c)->folder(46)->article(2112),       new ExceptionInput("subjectMissing"), V1::respError("404", 404)],
            ["/categories/2112/entries/47", (clone $c)->folder(2111)->article(47),       new ExceptionInput("idMissing"),      V1::respError("404", 404)],
            ["/categories/1/entries/42",    (clone $c)->folderShallow(0)->article(42),   [self::ENTRIES[0]],                   HTTP::respJson($entryOut)],
        ];
    }

    public function testModifyAnEntry(): void {
        // NOTE: This is a no-op
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn(self::v(self::FEEDS[1]));
        \Phake::when(Arsse::$db)->articleCategoriesGet->thenReturnCallback(function($user, $id) {
            return self::TAGS[$id] ?? [];
        });
        \Phake::when(Arsse::$db)->articleList->thenReturn(new Result(self::v([self::ENTRIES[0]])));
        $exp = self::ENTRIES_OUT[0];
        $exp['feed'] = self::feedsOut()[$exp['feed']];
        $exp = HTTP::respJson($exp);
        $this->assertMessage($exp, $this->req("PUT", "/entries/42"));
    }

    #[DataProvider("provideEntryMarkings")]
    public function testMarkEntries(array $in, ?array $data, ResponseInterface $exp): void {
        \Phake::when(Arsse::$db)->articleMark->thenReturn(0);
        $this->assertMessage($exp, $this->req("PUT", "/entries", $in));
        if ($data) {
            \Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $data, (new Context)->articles($in['entry_ids']));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideEntryMarkings(): iterable {
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

    #[DataProvider("provideMassMarkings")]
    public function testMassMarkEntries(string $url, Context $c, $out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleMark->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->articleMark->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("PUT", $url));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, ['read' => true], $this->equalTo($c));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideMassMarkings(): iterable {
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

    #[DataProvider("provideBookmarkTogglings")]
    public function testToggleABookmark($before, ?bool $after, ResponseInterface $exp): void {
        $c = (new Context)->article(2112);
        \Phake::when(Arsse::$db)->articleMark->thenReturn(1);
        if ($before instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleCount->thenThrow($before);
        } else {
            \Phake::when(Arsse::$db)->articleCount->thenReturn($before);
        }
        $this->assertMessage($exp, $this->req("PUT", "/entries/2112/bookmark"));
        if ($after !== null) {
            \Phake::inOrder(
                \Phake::verify(Arsse::$db)->begin(),
                \Phake::verify(Arsse::$db)->articleCount(Arsse::$user->id, (clone $c)->starred(false)),
                \Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, ['starred' => $after], $c),
                \Phake::verify($this->transaction)->commit()
            );
        } else {
            \Phake::inOrder(
                \Phake::verify(Arsse::$db)->begin(),
                \Phake::verify(Arsse::$db)->articleCount(Arsse::$user->id, (clone $c)->starred(false))
            );
            \Phake::verify($this->transaction, \Phake::never())->commit(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideBookmarkTogglings(): iterable {
        self::clearData();
        return [
            [1,                                    true,  HTTP::respEmpty(204)],
            [0,                                    false, HTTP::respEmpty(204)],
            [new ExceptionInput("subjectMissing"), null,  V1::respError("404", 404)],
        ];
    }

    public function testRefreshAFeed(): void {
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn([]);
        $this->assertMessage(HTTP::respEmpty(204), $this->req("PUT", "/feeds/47/refresh"));
        \Phake::verify(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 47);
    }

    public function testRefreshAMissingFeed(): void {
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenThrow(new ExceptionInput("subjectMissing"));
        $this->assertMessage(V1::respError("404", 404), $this->req("PUT", "/feeds/2112/refresh"));
        \Phake::verify(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 2112);
    }

    public function testRefreshAllFeeds(): void {
        $this->assertMessage(HTTP::respEmpty(204), $this->req("PUT", "/feeds/refresh"));
    }

    #[DataProvider("provideImports")]
    public function testImport($out, ResponseInterface $exp): void {
        $opml = \Phake::mock(OPML::class);
        \Phake::when(Arsse::$obj)->get(OPML::class)->thenReturn($opml);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when($opml)->import->$action($out);
        $this->assertMessage($exp, $this->req("POST", "/import", "IMPORT DATA"));
        \Phake::verify($opml)->import(Arsse::$user->id, "IMPORT DATA");
    }

    public static function provideImports(): iterable {
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
        $opml = \Phake::mock(OPML::class);
        \Phake::when(Arsse::$obj)->get(OPML::class)->thenReturn($opml);
        \Phake::when($opml)->export->thenReturn("<EXPORT_DATA/>");
        $this->assertMessage(HTTP::respText("<EXPORT_DATA/>", 200, ['Content-Type' => "application/xml"]), $this->req("GET", "/export"));
        \Phake::verify($opml)->export(Arsse::$user->id);
    }

    #[DataProvider("provideScrapings")]
    public function testScrapeAnEntry($entry, array $sub, ResponseInterface $exp): void {
        if ($entry instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleList->thenThrow($entry);
        } else {
            \Phake::when(Arsse::$db)->articleList->thenReturn(new Result(self::v($entry)));
        }
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn($sub);
        $this->assertMessage($exp, $this->req("GET", "/entries/2112/fetch-content"));
        \Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, $this->equalTo((new Context)->article(2112)), ["url", "subscription"]);
        if (!$entry instanceof \Exception) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, (int) $entry[0]['subscription']);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesGet(\Phake::anyParameters());
        }
    }

    public static function provideScrapings(): iterable {
        $base = "http://localhost:8000/Feed";
        $basePW = "http://user:pass@localhost:8000/Feed";
        return [
            [new ExceptionInput("subjectMissing"),                           [],                                                                           V1::respError("404", 404)],
            [[['url' => "$base/Scraping/Document", 'subscription' => 4400]], ['url' => "$base/Scraping/Feed",     'user_agent' => null, 'cookie' => null], HTTP::respJson(['content'=> "<p>Partial content, followed by more content</p>"])],
            [[['url' => "$base/Scraping/DocumentPW", 'subscription' => 1]],  ['url' => "$basePW/Scraping/FeedPW", 'user_agent' => null, 'cookie' => null], HTTP::respJson(['content'=> "<p>Partial content, followed by more content</p>"])],
            [[['url' => "$base/Scraping/DocumentPW", 'subscription' => 1]],  ['url' => "$base/Scraping/FeedPW",   'user_agent' => null, 'cookie' => null], V1::respError("Fetch401", 502)],
        ];
    }

    public function testSaveAnArticle(): void {
        $this->assertMessage(V1::respError("NoIntegrations", 400), $this->req("POST", "/entries/2112/save"));
    }

    public function testGetVersion(): void {
        $exp = HTTP::respJson([
            'version'       => V1::VERSION,
            'commit'        => V1::COMMIT,
            'build_date'    => V1::BUILD_DATE,
            'go_version'    => V1::GO_VERSION,
            'compiler'      => "gc",
            'arch'          => php_uname("m"),
            'os'            => php_uname("s"),
            'arsse_version' => Arsse::VERSION,
        ]);
        $this->assertMessage($exp, $this->req("GET", "/version"));
    }

    public function testFlushHistory(): void {
        $this->assertMessage(HTTP::respEmpty(202), $this->req("PUT", "/flush-history"));
        $this->assertMessage(HTTP::respEmpty(202), $this->req("DELETE", "/flush-history"));
    }

    #[DataProvider("provideIconsById")]
    public function testGetAnIcon(int $id, $out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->iconPropertiesGet->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->iconPropertiesGet->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("GET", "/icons/$id"));
        \Phake::verify(Arsse::$db)->iconPropertiesGet(Arsse::$user->id, $id);
    }

    public static function provideIconsById(): iterable {
        self::clearData();
        return [
            [4400, ['id' => 4400,   'type' => 'image/gif', 'data' => "OOK"], HTTP::respJson(['id' => 4400, 'mime_type' => "image/gif",                'data' => "image/gif;base64,".base64_encode("OOK")])],
            [2112, ['id' => "2112", 'type' => null, 'data' => "OOK"],        HTTP::respJson(['id' => 2112, 'mime_type' => "application/octet-stream", 'data' => "application/octet-stream;base64,".base64_encode("OOK")])],
            [1701, ['id' => 1701,   'type' => null, 'data' => null],         V1::respError("404", 404)],
            [1234, new ExceptionInput("subjectMissing"),                     V1::respError("404", 404)],
        ];
    }

    public function testRetrieveCounters(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result($this->v(self::FEEDS)));
        $exp = HTTP::respJson([
            'reads' => [
                1  => 5,
                55 => 2112,
            ],
            'unreads' => [
                1  => 42,
                55 => 0,
            ],
        ]);
        $this->assertMessage($exp, $this->req("GET", "/feeds/counters"));
    }

    public function testRetrieveCountersWithNoFeeds(): void {
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result([]));
        $exp = HTTP::respJson([
            'reads' => new \stdClass,
            'unreads' => new \stdClass,
        ]);
        $this->assertMessage($exp, $this->req("GET", "/feeds/counters"));
    }

    public function testGetIntegrationStatus(): void {
        $exp = HTTP::respJson(['has_integrations' => false]);
        $this->assertMessage($exp, $this->req("GET", "/integrations/status"));
    }

    #[DataProvider("provideEnclosures")]
    public function testGetAnEnclosure(int $id, $out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleList->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->articleList->thenReturn(new Result($this->v([$out])));
        }
        $this->assertMessage($exp, $this->req("GET", "/enclosures/$id"));
        \Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->article($id), ["id", "media_url", "media_type"]);
    }

    public static function provideEnclosures(): iterable {
        self::clearData();
        return [
            [1,    new ExceptionInput("subjectMissing"),                                                 V1::respError("404", 404)],
            [2112, ['id' => 2112, 'media_url' => null, 'media_type' => null],                            V1::respError("404", 404)],
            [4400, ['id' => 4400, 'media_url' => "http://example.com/ook", 'media_type' => "audio/ogg"], HTTP::respJson(['id' => 4400, 'user_id' => 42, 'entry_id' => 4400, 'url' => "http://example.com/ook", 'mime_type' => "audio/ogg",                'size' => 0, 'media_progression' => 0], 200)],
            [4400, ['id' => 4400, 'media_url' => "http://example.com/ook", 'media_type' => null],        HTTP::respJson(['id' => 4400, 'user_id' => 42, 'entry_id' => 4400, 'url' => "http://example.com/ook", 'mime_type' => "application/octet-stream", 'size' => 0, 'media_progression' => 0], 200)],
        ];
    }
}
