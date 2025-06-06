<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\NextcloudNews\Common;
use JKingWeb\Arsse\REST\NextcloudNews\V1_2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\NextcloudNews\V1_2::class)]
class TestV1_2 extends \JKingWeb\Arsse\Test\AbstractTest {
    use Common;

    protected $h;
    protected $transaction;
    protected $userId;
    protected $prefix = "/index.php/apps/news/api/v1-2";
    protected static $feeds = [ // expected sample output of a feed list from the database, and the resultant expected transformation by the REST handler
        'db' => [
            [
                'id'         => 2112,
                'url'        => 'http://example.com/news.atom',
                'icon_url'   => 'http://example.com/favicon.png',
                'source'     => 'http://example.com/',
                'folder'     => null,
                'top_folder' => null,
                'pinned'     => 0,
                'err_count'  => 0,
                'err_msg'    => '',
                'order_type' => 0,
                'added'      => '2017-05-20 13:35:54',
                'next_fetch' => '2017-05-20 14:35:54',
                'title'      => 'First example feed',
                'unread'     => 50048,
            ],
            [
                'id'         => 42,
                'url'        => 'http://example.org/news.atom',
                'icon_url'   => 'http://example.org/favicon.png',
                'source'     => 'http://example.org/',
                'folder'     => 12,
                'top_folder' => 8,
                'pinned'     => 1,
                'err_count'  => 0,
                'err_msg'    => '',
                'order_type' => 2,
                'added'      => '2017-05-20 13:35:54',
                'next_fetch' => '2017-05-20 14:35:54',
                'title'      => 'Second example feed',
                'unread'     => 23,
            ],
            [
                'id'         => 47,
                'url'        => 'http://example.net/news.atom',
                'icon_url'   => 'http://example.net/favicon.png',
                'source'     => 'http://example.net/',
                'folder'     => null,
                'top_folder' => null,
                'pinned'     => 0,
                'err_count'  => 0,
                'err_msg'    => null,
                'order_type' => 1,
                'added'      => '2017-05-20 13:35:54',
                'next_fetch' => '2017-05-20 14:35:54',
                'title'      => 'Third example feed',
                'unread'     => 0,
            ],
        ],
        'rest' => [
            [
                'id'               => 2112,
                'url'              => 'http://example.com/news.atom',
                'title'            => 'First example feed',
                'added'            => 1495287354,
                'pinned'           => false,
                'link'             => 'http://example.com/',
                'faviconLink'      => 'http://example.com/favicon.png',
                'folderId'         => 0,
                'unreadCount'      => 50048,
                'ordering'         => 0,
                'updateErrorCount' => 0,
                'lastUpdateError'  => '',
                'nextUpdateTime'   => 1495290954,
            ],
            [
                'id'               => 42,
                'url'              => 'http://example.org/news.atom',
                'title'            => 'Second example feed',
                'added'            => 1495287354,
                'pinned'           => true,
                'link'             => 'http://example.org/',
                'faviconLink'      => 'http://example.org/favicon.png',
                'folderId'         => 8,
                'unreadCount'      => 23,
                'ordering'         => 2,
                'updateErrorCount' => 0,
                'lastUpdateError'  => '',
                'nextUpdateTime'   => 1495290954,
            ],
            [
                'id'               => 47,
                'url'              => 'http://example.net/news.atom',
                'title'            => 'Third example feed',
                'added'            => 1495287354,
                'pinned'           => false,
                'link'             => 'http://example.net/',
                'faviconLink'      => 'http://example.net/favicon.png',
                'folderId'         => 0,
                'unreadCount'      => 0,
                'ordering'         => 1,
                'updateErrorCount' => 0,
                'lastUpdateError'  => '',
                'nextUpdateTime'   => 1495290954,
            ],
        ],
    ];
    protected static $articles = [
        'db' => [
            [
                'id'             => 101,
                'url'            => 'http://example.com/1',
                'title'          => 'Article title 1',
                'author'         => '',
                'content'        => '<p>Article content 1</p>',
                'guid'           => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
                'published_date' => '2000-01-01 00:00:00',
                'edited_date'    => '2000-01-01 00:00:01',
                'modified_date'  => '2000-01-01 01:00:00',
                'unread'         => 1,
                'starred'        => 0,
                'edition'        => 101,
                'subscription'   => 8,
                'fingerprint'    => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
                'media_url'      => null,
                'media_type'     => null,
            ],
            [
                'id'             => 102,
                'url'            => 'http://example.com/2',
                'title'          => 'Article title 2',
                'author'         => '',
                'content'        => '<p>Article content 2</p>',
                'guid'           => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'published_date' => '2000-01-02 00:00:00',
                'edited_date'    => '2000-01-02 00:00:02',
                'modified_date'  => '2000-01-02 02:00:00',
                'unread'         => 0,
                'starred'        => 0,
                'edition'        => 202,
                'subscription'   => 8,
                'fingerprint'    => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
                'media_url'      => "http://example.com/text",
                'media_type'     => "text/plain",
            ],
            [
                'id'             => 103,
                'url'            => 'http://example.com/3',
                'title'          => 'Article title 3',
                'author'         => '',
                'content'        => '<p>Article content 3</p>',
                'guid'           => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
                'published_date' => '2000-01-03 00:00:00',
                'edited_date'    => '2000-01-03 00:00:03',
                'modified_date'  => '2000-01-03 03:00:00',
                'unread'         => 1,
                'starred'        => 1,
                'edition'        => 203,
                'subscription'   => 9,
                'fingerprint'    => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
                'media_url'      => "http://example.com/video",
                'media_type'     => "video/webm",
            ],
            [
                'id'             => 104,
                'url'            => 'http://example.com/4',
                'title'          => 'Article title 4',
                'author'         => '',
                'content'        => '<p>Article content 4</p>',
                'guid'           => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'published_date' => '2000-01-04 00:00:00',
                'edited_date'    => '2000-01-04 00:00:04',
                'modified_date'  => '2000-01-04 04:00:00',
                'unread'         => 0,
                'starred'        => 1,
                'edition'        => 204,
                'subscription'   => 9,
                'fingerprint'    => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
                'media_url'      => "http://example.com/image",
                'media_type'     => "image/svg+xml",
            ],
            [
                'id'             => 105,
                'url'            => 'http://example.com/5',
                'title'          => 'Article title 5',
                'author'         => '',
                'content'        => '<p>Article content 5</p>',
                'guid'           => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'published_date' => '2000-01-05 00:00:00',
                'edited_date'    => '2000-01-05 00:00:05',
                'modified_date'  => '2000-01-05 05:00:00',
                'unread'         => 1,
                'starred'        => 0,
                'edition'        => 305,
                'subscription'   => 10,
                'fingerprint'    => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
                'media_url'      => "http://example.com/audio",
                'media_type'     => "audio/ogg",
            ],
        ],
        'rest' => [
            [
                'id'            => 101,
                'guid'          => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
                'guidHash'      => "101",
                'url'           => 'http://example.com/1',
                'title'         => 'Article title 1',
                'author'        => '',
                'pubDate'       => 946684801,
                'body'          => '<p>Article content 1</p>',
                'enclosureMime' => "",
                'enclosureLink' => "",
                'feedId'        => 8,
                'unread'        => true,
                'starred'       => false,
                'lastModified'  => 946688400,
                'fingerprint'   => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
            ],
            [
                'id'            => 202,
                'guid'          => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'guidHash'      => "102",
                'url'           => 'http://example.com/2',
                'title'         => 'Article title 2',
                'author'        => '',
                'pubDate'       => 946771202,
                'body'          => '<p>Article content 2</p>',
                'enclosureMime' => "text/plain",
                'enclosureLink' => "http://example.com/text",
                'feedId'        => 8,
                'unread'        => false,
                'starred'       => false,
                'lastModified'  => 946778400,
                'fingerprint'   => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
            ],
            [
                'id'            => 203,
                'guid'          => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
                'guidHash'      => "103",
                'url'           => 'http://example.com/3',
                'title'         => 'Article title 3',
                'author'        => '',
                'pubDate'       => 946857603,
                'body'          => '<p>Article content 3</p>',
                'enclosureMime' => "video/webm",
                'enclosureLink' => "http://example.com/video",
                'feedId'        => 9,
                'unread'        => true,
                'starred'       => true,
                'lastModified'  => 946868400,
                'fingerprint'   => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
            ],
            [
                'id'            => 204,
                'guid'          => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'guidHash'      => "104",
                'url'           => 'http://example.com/4',
                'title'         => 'Article title 4',
                'author'        => '',
                'pubDate'       => 946944004,
                'body'          => '<p>Article content 4</p>',
                'enclosureMime' => "image/svg+xml",
                'enclosureLink' => "http://example.com/image",
                'feedId'        => 9,
                'unread'        => false,
                'starred'       => true,
                'lastModified'  => 946958400,
                'fingerprint'   => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
            ],
            [
                'id'            => 305,
                'guid'          => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'guidHash'      => "105",
                'url'           => 'http://example.com/5',
                'title'         => 'Article title 5',
                'author'        => '',
                'pubDate'       => 947030405,
                'body'          => '<p>Article content 5</p>',
                'enclosureMime' => "audio/ogg",
                'enclosureLink' => "http://example.com/audio",
                'feedId'        => 10,
                'unread'        => true,
                'starred'       => false,
                'lastModified'  => 947048400,
                'fingerprint'   => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
            ],
        ],
    ];

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock user manager
        $this->userId = "john.doe@example.com";
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => true]);
        Arsse::$user->id = $this->userId;
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        //initialize a handler
        $this->h = new V1_2;
    }

    protected static function v($value) {
        return $value;
    }

    protected function req(string $method, string $target, $data = "", array $headers = [], bool $authenticated = true, bool $body = true): ResponseInterface {
        $url = $this->prefix.$target;
        if ($body) {
            $params = [];
        } else {
            $params = $data;
            $data = [];
        }
        $req = $this->serverRequest($method, $url, $this->prefix, $headers, [], $data, "application/json", $params, $authenticated ? $this->userId : "");
        return $this->h->dispatch($req);
    }

    protected function reqText(string $method, string $target, string $data, string $type, array $headers = [], bool $authenticated = true): ResponseInterface {
        $url = $this->prefix.$target;
        $req = $this->serverRequest($method, $url, $this->prefix, $headers, [], $data, $type, [], $authenticated ? $this->userId : "");
        return $this->h->dispatch($req);
    }

    public function testSendAuthenticationChallenge(): void {
        $exp = self::error(401, "401");
        $this->assertMessage($exp, $this->req("GET", "/", "", [], false));
    }

    #[DataProvider("provideInvalidPaths")]
    public function testRespondToInvalidPaths($path, $method, $code, $allow = null): void {
        $exp = self::error($code, ["$code", $method], $allow ? ['Allow' => $allow] : []);
        $this->assertMessage($exp, $this->req($method, $path));
    }

    public static function provideInvalidPaths(): array {
        return [
            ["/",                  "GET",     404],
            ["/",                  "POST",    404],
            ["/",                  "PUT",     404],
            ["/",                  "DELETE",  404],
            ["/",                  "OPTIONS", 404],
            ["/version/invalid",   "GET",     404],
            ["/version/invalid",   "POST",    404],
            ["/version/invalid",   "PUT",     404],
            ["/version/invalid",   "DELETE",  404],
            ["/version/invalid",   "OPTIONS", 404],
            ["/folders/1/invalid", "GET",     404],
            ["/folders/1/invalid", "POST",    404],
            ["/folders/1/invalid", "PUT",     404],
            ["/folders/1/invalid", "DELETE",  404],
            ["/folders/1/invalid", "OPTIONS", 404],
            ["/version",           "POST",    405, "GET"],
            ["/version",           "PUT",     405, "GET"],
            ["/version",           "DELETE",  405, "GET"],
            ["/folders",           "PUT",     405, "GET, POST"],
            ["/folders",           "DELETE",  405, "GET, POST"],
            ["/folders/1",         "GET",     405, "PUT, DELETE"],
            ["/folders/1",         "POST",    405, "PUT, DELETE"],
        ];
    }

    public function testRespondToInvalidInputTypes(): void {
        $exp = self::error(415, ["415", "application/xml"], ['Accept' => "application/json"]);
        $this->assertMessage($exp, $this->req("PUT", "/folders/1", '<data/>', ['Content-Type' => "application/xml"]));
        $exp = self::error(400, "ParseError");
        $this->assertMessage($exp, $this->req("PUT", "/folders/1", '<data/>'));
        $this->assertMessage($exp, $this->req("PUT", "/folders/1", '<data/>', ['Content-Type' => null]));
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
            ["/feeds",      "HEAD,GET,POST", "application/json"],
            ["/feeds/2112", "DELETE",        "application/json"],
            ["/user",       "HEAD,GET",      "application/json"],
        ];
    }

    public function testListFolders(): void {
        $list = [
            ['id' => 1,  'name' => "Software", 'parent' => null],
            ['id' => 12, 'name' => "Hardware", 'parent' => null],
        ];
        $out = [
            ['id' => 1,  'name' => "Software"],
            ['id' => 12, 'name' => "Hardware"],
        ];
        \Phake::when(Arsse::$db)->folderList($this->userId, null, false)->thenReturn(new Result(self::v($list)));
        $exp = HTTP::respJson(['folders' => $out]);
        $this->assertMessage($exp, $this->req("GET", "/folders"));
    }

    #[DataProvider("provideFolderCreations")]
    public function testAddAFolder(array $input, bool $body, $output, ResponseInterface $exp): void {
        if ($output instanceof ExceptionInput) {
            \Phake::when(Arsse::$db)->folderAdd->thenThrow($output);
        } else {
            \Phake::when(Arsse::$db)->folderAdd->thenReturn($output);
            \Phake::when(Arsse::$db)->folderPropertiesGet->thenReturn(self::v(['id' => $output, 'name' => $input['name'], 'parent' => null]));
        }
        $act = $this->req("POST", "/folders", $input, [], true, $body);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db)->folderAdd($this->userId, $input);
        if ($output instanceof ExceptionInput) {
            \Phake::verify(Arsse::$db, \Phake::never())->folderPropertiesGet(\Phake::anyParameters());
        } else {
            \Phake::verify(Arsse::$db)->folderPropertiesGet($this->userId, $this->equalTo($output));
        }
    }

    public static function provideFolderCreations(): array {
        return [
            [['name' => "Software"], true,  1,                                         HTTP::respJson(['folders' => [['id' => 1, 'name' => "Software"]]])],
            [['name' => "Software"], false, 1,                                         HTTP::respJson(['folders' => [['id' => 1, 'name' => "Software"]]])],
            [['name' => "Hardware"], true,  "2",                                       HTTP::respJson(['folders' => [['id' => 2, 'name' => "Hardware"]]])],
            [['name' => "Hardware"], false, "2",                                       HTTP::respJson(['folders' => [['id' => 2, 'name' => "Hardware"]]])],
            [['name' => "Software"], true,  new ExceptionInput("constraintViolation"), self::error(409, new ExceptionInput("constraintViolation"))],
            [['name' => ""],         true,  new ExceptionInput("whitespace"),          self::error(422, new ExceptionInput("whitespace"))],
            [['name' => " "],        true,  new ExceptionInput("whitespace"),          self::error(422, new ExceptionInput("whitespace"))],
            [['name' => null],       true,  new ExceptionInput("missing"),             self::error(422, new ExceptionInput("missing"))],
        ];
    }

    public function testRemoveAFolder(): void {
        \Phake::when(Arsse::$db)->folderRemove($this->userId, 1)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("DELETE", "/folders/1"));
        // fail on the second invocation because it no longer exists
        $exp = self::error(404, new ExceptionInput("subjectMissing"));
        $this->assertMessage($exp, $this->req("DELETE", "/folders/1"));
        \Phake::verify(Arsse::$db, \Phake::times(2))->folderRemove($this->userId, 1);
    }

    #[DataProvider("provideFolderRenamings")]
    public function testRenameAFolder(array $input, int $id, $output, ResponseInterface $exp): void {
        if ($output instanceof ExceptionInput) {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenThrow($output);
        } else {
            \Phake::when(Arsse::$db)->folderPropertiesSet->thenReturn($output);
        }
        $act = $this->req("PUT", "/folders/$id", $input);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db)->folderPropertiesSet($this->userId, $id, $input);
    }

    public static function provideFolderRenamings(): array {
        return [
            [['name' => "Software"], 1, true,                                      HTTP::respEmpty(204)],
            [['name' => "Software"], 2, new ExceptionInput("constraintViolation"), self::error(409, new ExceptionInput("constraintViolation"))],
            [['name' => "Software"], 3, new ExceptionInput("subjectMissing"),      self::error(404, new ExceptionInput("subjectMissing"))],
            [['name' => ""],         2, new ExceptionInput("whitespace"),          self::error(422, new ExceptionInput("whitespace"))],
            [['name' => " "],        2, new ExceptionInput("whitespace"),          self::error(422, new ExceptionInput("whitespace"))],
            [['name' => null],       2, new ExceptionInput("missing"),             self::error(422, new ExceptionInput("missing"))],
        ];
    }

    public function testRetrieveServerVersion(): void {
        $exp = HTTP::respJson([
            'version'       => V1_2::VERSION,
            'arsse_version' => Arsse::VERSION,
            ]);
        $this->assertMessage($exp, $this->req("GET", "/version"));
    }

    public function testListSubscriptions(): void {
        $exp1 = [
            'feeds'        => [],
            'starredCount' => 0,
        ];
        $exp2 = [
            'feeds'        => self::$feeds['rest'],
            'starredCount' => 5,
            'newestItemId' => 4758915,
        ];
        \Phake::when(Arsse::$db)->subscriptionList($this->userId)->thenReturn(new Result([]))->thenReturn(new Result(self::v(self::$feeds['db'])));
        \Phake::when(Arsse::$db)->articleStarred($this->userId)->thenReturn(self::v(['total' => 0]))->thenReturn(self::v(['total' => 5]));
        \Phake::when(Arsse::$db)->editionLatest($this->userId)->thenReturn(0)->thenReturn(4758915);
        $exp = HTTP::respJson($exp1);
        $this->assertMessage($exp, $this->req("GET", "/feeds"));
        $exp = HTTP::respJson($exp2);
        $this->assertMessage($exp, $this->req("GET", "/feeds"));
    }

    #[DataProvider("provideNewSubscriptions")]
    public function testAddASubscription(array $input, $id, int $latestEdition, array $output, $moveOutcome, ResponseInterface $exp): void {
        if ($id instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionAdd->thenThrow($id);
        } else {
            \Phake::when(Arsse::$db)->subscriptionAdd->thenReturn($id);
        }
        if ($moveOutcome instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenThrow($moveOutcome);
        } else {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn($moveOutcome);
        }
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn(self::v($output));
        \Phake::when(Arsse::$db)->editionLatest->thenReturn($latestEdition);
        $act = $this->req("POST", "/feeds", $input);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db)->subscriptionAdd($this->userId, $input['url'] ?? "");
        if ($id instanceof \Exception) {
            \Phake::verify(Arsse::$db, \Phake::times(0))->subscriptionPropertiesSet(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::times(0))->subscriptionPropertiesGet(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::times(0))->editionLatest(\Phake::anyParameters());
        } else {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesGet($this->userId, $id);
            \Phake::verify(Arsse::$db)->editionLatest($this->userId, $this->equalTo((new Context)->subscription($id)->hidden(false)));
            if ($input['folderId'] ?? 0) {
                \Phake::verify(Arsse::$db)->subscriptionPropertiesSet($this->userId, $id, ['folder' => (int) $input['folderId']]);
            } else {
                \Phake::verify(Arsse::$db)->subscriptionPropertiesSet(\Phake::anyParameters());
            }
        }
    }

    public static function provideNewSubscriptions(): array {
        $feedException = new \JKingWeb\Arsse\Feed\Exception("", [], new \PicoFeed\Reader\SubscriptionNotFoundException);
        return [
            [['url' => "http://example.com/news.atom", 'folderId' => 3],  2112,                                      0,       self::$feeds['db'][0], new ExceptionInput("idMissing"),     HTTP::respJson(['feeds' => [self::$feeds['rest'][0]]])],
            [['url' => "http://example.org/news.atom", 'folderId' => 8],  42,                                        4758915, self::$feeds['db'][1], true,                                HTTP::respJson(['feeds' => [self::$feeds['rest'][1]], 'newestItemId' => 4758915])],
            [['url' => "http://example.com/news.atom", 'folderId' => 3],  new ExceptionInput("constraintViolation"), 0,       self::$feeds['db'][0], new ExceptionInput("idMissing"),     self::error(409, new ExceptionInput("constraintViolation"))],
            [['url' => "http://example.org/news.atom", 'folderId' => 8],  new ExceptionInput("constraintViolation"), 4758915, self::$feeds['db'][1], true,                                self::error(409, new ExceptionInput("constraintViolation"))],
            [['url' => "http://example.com/bad"],                         $feedException,                            0,       [],                    false,                               self::error(422, $feedException)],
            [['url' => "relative"],                                       new ExceptionInput("invalidValue"),        0,       [],                    false,                               self::error(422, new ExceptionInput("invalidValue"))],
            [['url' => "http://example.net/news.atom", 'folderId' => -1], 47,                                        2112,    self::$feeds['db'][2], new ExceptionInput("typeViolation"), HTTP::respJson(['feeds' => [self::$feeds['rest'][2]], 'newestItemId' => 2112])],
        ];
    }

    #[DataProvider("provideNewSubscriptionsWithType")]
    public function testAddSubscriptionsWithDifferentMediaTypes(string $input, string $type, ResponseInterface $exp): void {
        \Phake::when(Arsse::$db)->subscriptionAdd->thenReturn(42);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet->thenReturn(self::v(self::$feeds['db'][1]));
        \Phake::when(Arsse::$db)->editionLatest->thenReturn(4758915);
        $act = $this->reqText("POST", "/feeds", $input, $type);
        $this->assertMessage($exp, $act);
    }

    public static function provideNewSubscriptionsWithType(): iterable {
        self::clearData();
        $success = HTTP::respJson(['feeds' => [self::$feeds['rest'][1]], 'newestItemId' => 4758915]);
        return [
            ['{"url":"http://example.org/news.atom","folderId":8}', "application/json",                  $success],
            ['{"url":"http://example.org/news.atom","folderId":8}', "text/json",                         $success],
            ['{"url":"http://example.org/news.atom","folderId":8}', "",                                  $success],
            ['{"url":"http://example.org/news.atom","folderId":8}', "/",                                 $success],
            ['{"url":"http://example.org/news.atom","folderId":8}', "application/x-www-form-urlencoded", $success],
            ['{"url":"http://example.org/news.atom","folderId":8}', "application/octet-stream",          $success],
            ['url=http://example.org/news.atom&folderId=8',         "application/x-www-form-urlencoded", $success],
            ['url=http://example.org/news.atom&folderId=8',         "",                                  $success],
            ['{"url":',                                             "application/json",                  self::error(400, "ParseError")],
            ['{"url":',                                             "text/json",                         self::error(400, "ParseError")],
            ['{"url":',                                             "",                                  self::error(400, "ParseError")],
            ['{"url":',                                             "application/x-www-form-urlencoded", self::error(400, "ParseError")],
            ['{"url":',                                             "application/octet-stream",          self::error(415, ["415", "application/octet-stream"], ['Accept' => "application/json"])],
            ['null',                                                "application/json",                  self::error(400, "ParseError")],
            ['null',                                                "text/json",                         self::error(400, "ParseError")],
        ];
    }

    public function testRemoveASubscription(): void {
        \Phake::when(Arsse::$db)->subscriptionRemove($this->userId, 1)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("DELETE", "/feeds/1"));
        // fail on the second invocation because it no longer exists
        $exp = self::error(404, new ExceptionInput("subjectMissing"));
        $this->assertMessage($exp, $this->req("DELETE", "/feeds/1"));
        \Phake::verify(Arsse::$db, \Phake::times(2))->subscriptionRemove($this->userId, 1);
    }

    #[DataProvider("provideSubscriptionMoves")]
    public function testMoveASubscription(string $url, array $data, $moveOut, ResponseInterface $exp): void {
        $subject = (int) explode("/", $url)[2];
        if ($moveOut instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenThrow($moveOut);
        } else {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn($moveOut);
        }
        $this->assertMessage($exp, $this->req("PUT", $url, json_encode($data)));
        if (isset($data['folderId'])) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesSet($this->userId, $subject, $this->identicalTo(['folder' => $data['folderId']]));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideSubscriptionMoves(): iterable {
        return [
            ["/feeds/1/move",  ['folderId' => 0],    true,                                 HTTP::respEmpty(204)],
            ["/feeds/1/move",  ['folderId' => 42],   true,                                 HTTP::respEmpty(204)],
            ["/feeds/1/move",  ['folderId' => 2112], new ExceptionInput("idMissing"),      self::error(422, new ExceptionInput("idMissing"))],
            ["/feeds/42/move", ['folderId' => 42],   new ExceptionInput("subjectMissing"), self::error(404, new ExceptionInput("subjectMissing"))],
            ["/feeds/1/move",  ['folderId' => -1],   new ExceptionInput("typeViolation"),  self::error(422, new ExceptionInput("typeViolation"))],
            ["/feeds/1/move",  [],                   true,                                 self::error(422, new ExceptionInput("typeViolation", ["action" => "subscriptionPropertiesSet", "field" => "folderId", 'type' => "int > 0"]))],
        ];
    }

    #[DataProvider("provideSubscriptionRenamings")]
    public function testRenameASubscription(string $url, array $data, $renameOut, ResponseInterface $exp): void {
        $subject = (int) explode("/", $url)[2];
        if ($renameOut instanceof \Exception) {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenThrow($renameOut);
        } else {
            \Phake::when(Arsse::$db)->subscriptionPropertiesSet->thenReturn($renameOut);
        }
        $this->assertMessage($exp, $this->req("PUT", $url, json_encode($data)));
        \Phake::verify(Arsse::$db)->subscriptionPropertiesSet($this->userId, $subject, $this->identicalTo(['title' => (string) ($data['feedTitle'] ?? "")]));
    }

    public static function provideSubscriptionRenamings(): iterable {
        return [
            ["/feeds/1/rename",  ['feedTitle' => null],  new ExceptionInput("missing"),        self::error(422, new ExceptionInput("missing"))],
            ["/feeds/1/rename",  ['feedTitle' => "Ook"], true,                                 HTTP::respEmpty(204)],
            ["/feeds/1/rename",  ['feedTitle' => "   "], new ExceptionInput("whitespace"),     self::error(422, new ExceptionInput("whitespace"))],
            ["/feeds/1/rename",  ['feedTitle' => ""],    new ExceptionInput("missing"),        self::error(422, new ExceptionInput("missing"))],
            ["/feeds/1/rename",  ['feedTitle' => false], new ExceptionInput("missing"),        self::error(422, new ExceptionInput("missing"))],
            ["/feeds/42/rename", ['feedTitle' => "!!!"], new ExceptionInput("subjectMissing"), self::error(404, new ExceptionInput("subjectMissing"))],
            ["/feeds/1/rename",  [],                     new ExceptionInput("missing"),        self::error(422, new ExceptionInput("missing"))],
        ];
    }

    public function testListStaleFeeds(): void {
        $out = [
            [
                'id'     => 42,
                'userId' => "",
            ],
            [
                'id'     => 2112,
                'userId' => "",
            ],
        ];
        \Phake::when(Arsse::$db)->feedListStale->thenReturn(self::v(array_column($out, "id")));
        $exp = HTTP::respJson(['feeds' => $out]);
        $this->assertMessage($exp, $this->req("GET", "/feeds/all"));
    }

    public function testListStaleFeedsWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => false]);
        $exp = self::error(403, "403");
        $this->assertMessage($exp, $this->req("GET", "/feeds/all"));
        \Phake::verify(Arsse::$db, \Phake::never())->feedListStale(\Phake::anyParameters());
    }

    #[DataProvider("provideFeedUpdates")]
    public function testUpdateAFeed(array $in, ResponseInterface $exp): void {
        \Phake::when(Arsse::$db)->subscriptionUpdate("ook", 42)->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionUpdate("eek", 2112)->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionUpdate(null, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionUpdate($this->anything(), $this->lessThan(1))->thenThrow(new ExceptionInput("typeViolation"));
        $this->assertMessage($exp, $this->req("GET", "/feeds/update", json_encode($in)));
    }

    public static function provideFeedUpdates(): iterable {
        return [
            'Valid input'  => [['userId' => "ook", 'feedId' => 42],    HTTP::respEmpty(204)],
            'Missing feed' => [['userId' => "eek", 'feedId' => 2112],  self::error(404, new ExceptionInput("subjectMissing"))],
            'String ID'    => [['userId' => "ook", 'feedId' => "ook"], self::error(422, new ExceptionInput("typeViolation"))],
            'Negative ID'  => [['userId' => "ook", 'feedId' => -1],    self::error(422, new ExceptionInput("typeViolation"))],
            'Bad input 1'  => [['userId' => "ook", 'feed'   => 42],    self::error(422, new ExceptionInput("typeViolation"))],
            'Bad input 2'  => [['user'   => "ook", 'feedId' => 42],    self::error(404, new ExceptionInput("subjectMissing"))],
        ];
    }

    public function testUpdateAFeedWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => false]);
        $exp = self::error(403, "403");
        $this->assertMessage($exp, $this->req("GET", "/feeds/update", ['feedId' => 42]));
        \Phake::verify(Arsse::$db, \Phake::never())->subscriptionUpdate(\Phake::anyParameters());
    }

    #[DataProvider("provideArticleQueries")]
    public function testListArticles(string $url, array $in, Context $c, $out, ResponseInterface $exp): void {
        if ($out instanceof \Exception) {
            \Phake::when(Arsse::$db)->articleList->thenThrow($out);
        } else {
            \Phake::when(Arsse::$db)->articleList->thenReturn($out);
        }
        $this->assertMessage($exp, $this->req("GET", $url, $in));
        $columns = ["edition", "guid", "id", "url", "title", "author", "edited_date", "content", "media_type", "media_url", "subscription", "unread", "starred", "modified_date", "fingerprint"];
        $order = ($in['oldestFirst'] ?? false) ? "edition" : "edition desc";
        \Phake::verify(Arsse::$db)->articleList($this->userId, $this->equalTo($c), $columns, [$order]);
    }

    public static function provideArticleQueries(): iterable {
        $c = (new Context)->hidden(false);
        $t = Date::normalize(time());
        $out = new Result(self::v(self::$articles['db']));
        $r200 = HTTP::respJson(['items' => self::$articles['rest']]);
        return [
            ["/items",         [],                                                         clone $c,                                     $out,                                $r200],
            ["/items",         ['type' => 0, 'id' => 42],                                  (clone $c)->subscription(42),                 new ExceptionInput("idMissing"),     self::error(422, new ExceptionInput("idMissing"))],
            ["/items",         ['type' => 1, 'id' => 2112],                                (clone $c)->folder(2112),                     new ExceptionInput("idMissing"),     self::error(422, new ExceptionInput("idMissing"))],
            ["/items",         ['type' => 0, 'id' => -1],                                  (clone $c)->subscription(-1),                 new ExceptionInput("typeViolation"), self::error(422, new ExceptionInput("typeViolation"))],
            ["/items",         ['type' => 1, 'id' => -1],                                  (clone $c)->folder(-1),                       new ExceptionInput("typeViolation"), self::error(422, new ExceptionInput("typeViolation"))],
            ["/items",         ['type' => 2, 'id' => 0],                                   (clone $c)->starred(true),                    $out,                                $r200],
            ["/items",         ['type' => 3, 'id' => 0],                                   clone $c,                                     $out,                                $r200],
            ["/items",         ['getRead' => true],                                        clone $c,                                     $out,                                $r200],
            ["/items",         ['getRead' => false],                                       (clone $c)->unread(true),                     $out,                                $r200],
            ["/items",         ['lastModified' => $t->getTimestamp()],                     (clone $c)->markedRange($t, null),            $out,                                $r200],
            ["/items",         ['oldestFirst' => true,  'batchSize' => 10, 'offset' => 5], (clone $c)->editionRange(6, null)->limit(10), $out,                                $r200],
            ["/items",         ['oldestFirst' => false, 'batchSize' => 5,  'offset' => 5], (clone $c)->editionRange(null, 4)->limit(5),  $out,                                $r200],
            ["/items",         ['oldestFirst' => false, 'batchSize' => 5,  'offset' => 0], (clone $c)->limit(5),                         $out,                                $r200],
            ["/items/updated", [],                                                         clone $c,                                     $out,                                $r200],
            ["/items/updated", ['type' => 0, 'id' => 42],                                  (clone $c)->subscription(42),                 new ExceptionInput("idMissing"),     self::error(422, new ExceptionInput("idMissing"))],
            ["/items/updated", ['type' => 1, 'id' => 2112],                                (clone $c)->folder(2112),                     new ExceptionInput("idMissing"),     self::error(422, new ExceptionInput("idMissing"))],
            ["/items/updated", ['type' => 0, 'id' => -1],                                  (clone $c)->subscription(-1),                 new ExceptionInput("typeViolation"), self::error(422, new ExceptionInput("typeViolation"))],
            ["/items/updated", ['type' => 1, 'id' => -1],                                  (clone $c)->folder(-1),                       new ExceptionInput("typeViolation"), self::error(422, new ExceptionInput("typeViolation"))],
            ["/items/updated", ['type' => 2, 'id' => 0],                                   (clone $c)->starred(true),                    $out,                                $r200],
            ["/items/updated", ['type' => 3, 'id' => 0],                                   clone $c,                                     $out,                                $r200],
            ["/items/updated", ['getRead' => true],                                        clone $c,                                     $out,                                $r200],
            ["/items/updated", ['getRead' => false],                                       (clone $c)->unread(true),                     $out,                                $r200],
            ["/items/updated", ['lastModified' => $t->getTimestamp()],                     (clone $c)->markedRange($t, null),            $out,                                $r200],
            ["/items/updated", ['oldestFirst' => true,  'batchSize' => 10, 'offset' => 5], (clone $c)->editionRange(6, null)->limit(10), $out,                                $r200],
            ["/items/updated", ['oldestFirst' => false, 'batchSize' => 5,  'offset' => 5], (clone $c)->editionRange(null, 4)->limit(5),  $out,                                $r200],
            ["/items/updated", ['oldestFirst' => false, 'batchSize' => 5,  'offset' => 0], (clone $c)->limit(5),                         $out,                                $r200],
        ];
    }

    public function testMarkAFolderRead(): void {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->folder(1)->editionRange(null, 2112)->hidden(false)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->folder(42)->editionRange(null, 2112)->hidden(false)))->thenThrow(new ExceptionInput("idMissing")); // folder doesn't exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("PUT", "/folders/1/read", $in));
        $this->assertMessage($exp, $this->req("PUT", "/folders/1/read?newestItemId=2112"));
        $exp = self::error(422, new ExceptionInput("typeViolation", ["action" => "articleMark", "field" => "newestItemId", 'type' => "int > 0"]));
        $this->assertMessage($exp, $this->req("PUT", "/folders/1/read"));
        $this->assertMessage($exp, $this->req("PUT", "/folders/1/read?newestItemId=ook"));
        $exp = self::error(404, new ExceptionInput("idMissing"));
        $this->assertMessage($exp, $this->req("PUT", "/folders/42/read", $in));
    }

    public function testMarkASubscriptionRead(): void {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->subscription(1)->editionRange(null, 2112)->hidden(false)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->subscription(42)->editionRange(null, 2112)->hidden(false)))->thenThrow(new ExceptionInput("idMissing")); // subscription doesn't exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("PUT", "/feeds/1/read", $in));
        $this->assertMessage($exp, $this->req("PUT", "/feeds/1/read?newestItemId=2112"));
        $exp = self::error(422, new ExceptionInput("typeViolation", ["action" => "articleMark", "field" => "newestItemId", 'type' => "int > 0"]));
        $this->assertMessage($exp, $this->req("PUT", "/feeds/1/read"));
        $this->assertMessage($exp, $this->req("PUT", "/feeds/1/read?newestItemId=ook"));
        $exp = self::error(404, new ExceptionInput("idMissing"));
        $this->assertMessage($exp, $this->req("PUT", "/feeds/42/read", $in));
    }

    public function testMarkAllItemsRead(): void {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->editionRange(null, 2112)))->thenReturn(42);
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("PUT", "/items/read", $in));
        $this->assertMessage($exp, $this->req("PUT", "/items/read?newestItemId=2112"));
        $exp = self::error(422, new ExceptionInput("typeViolation", ["action" => "articleMark", "field" => "newestItemId", 'type' => "int > 0"]));
        $this->assertMessage($exp, $this->req("PUT", "/items/read"));
        $this->assertMessage($exp, $this->req("PUT", "/items/read?newestItemId=ook"));
    }

    public function testChangeMarksOfASingleArticle(): void {
        $read = ['read' => true];
        $unread = ['read' => false];
        $star = ['starred' => true];
        $unstar = ['starred' => false];
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->edition(1)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $read, $this->equalTo((new Context)->edition(42)))->thenThrow(new ExceptionInput("subjectMissing")); // edition doesn't exist doesn't exist
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unread, $this->equalTo((new Context)->edition(2)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unread, $this->equalTo((new Context)->edition(47)))->thenThrow(new ExceptionInput("subjectMissing")); // edition doesn't exist doesn't exist
        \Phake::when(Arsse::$db)->articleMark($this->userId, $star, $this->equalTo((new Context)->article(3)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $star, $this->equalTo((new Context)->article(2112)))->thenThrow(new ExceptionInput("subjectMissing")); // article doesn't exist doesn't exist
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unstar, $this->equalTo((new Context)->article(4)))->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $unstar, $this->equalTo((new Context)->article(1337)))->thenThrow(new ExceptionInput("subjectMissing")); // article doesn't exist doesn't exist
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("PUT", "/items/1/read"));
        $this->assertMessage($exp, $this->req("PUT", "/items/2/unread"));
        $this->assertMessage($exp, $this->req("PUT", "/items/1/3/star"));
        $this->assertMessage($exp, $this->req("PUT", "/items/4400/4/unstar"));
        $exp = self::error(404, new ExceptionInput("subjectMissing"));
        $this->assertMessage($exp, $this->req("PUT", "/items/42/read"));
        $this->assertMessage($exp, $this->req("PUT", "/items/47/unread"));
        $this->assertMessage($exp, $this->req("PUT", "/items/1/2112/star"));
        $this->assertMessage($exp, $this->req("PUT", "/items/4400/1337/unstar"));
        \Phake::verify(Arsse::$db, \Phake::times(8))->articleMark($this->userId, \Phake::ignoreRemaining());
    }

    public function testChangeMarksOfMultipleArticles(): void {
        $read = ['read' => true];
        $unread = ['read' => false];
        $star = ['starred' => true];
        $unstar = ['starred' => false];
        $in = [
            ["ook","eek","ack"],
            range(100, 199),
        ];
        $inStar = $in;
        for ($a = 0; $a < sizeof($inStar); $a++) {
            for ($b = 0; $b < sizeof($inStar[$a]); $b++) {
                $inStar[$a][$b] = ['feedId' => 2112, 'guidHash' => $inStar[$a][$b]];
            }
        }
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), $this->anything())->thenReturn(42);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), $this->equalTo((new Context)->editions([])))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), $this->equalTo((new Context)->articles([])))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("PUT", "/items/read/multiple"));
        $this->assertMessage($exp, $this->req("PUT", "/items/unread/multiple"));
        $this->assertMessage($exp, $this->req("PUT", "/items/star/multiple"));
        $this->assertMessage($exp, $this->req("PUT", "/items/unstar/multiple"));
        $this->assertMessage($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => "ook"])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => "ook"])));
        $this->assertMessage($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => "ook"])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => "ook"])));
        $this->assertMessage($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => []])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => []])));
        $this->assertMessage($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => $in[0]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => $in[0]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => $in[1]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => $in[1]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => []])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => []])));
        $this->assertMessage($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => $inStar[0]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => $inStar[0]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => $inStar[1]])));
        $this->assertMessage($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => $inStar[1]])));
        // ensure the data model was queried appropriately for read/unread
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $read, $this->equalTo((new Context)->editions([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $read, $this->equalTo((new Context)->editions($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $read, $this->equalTo((new Context)->editions($in[1])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unread, $this->equalTo((new Context)->editions([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unread, $this->equalTo((new Context)->editions($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unread, $this->equalTo((new Context)->editions($in[1])));
        // ensure the data model was queried appropriately for star/unstar
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $star, $this->equalTo((new Context)->articles([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $star, $this->equalTo((new Context)->articles($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $star, $this->equalTo((new Context)->articles($in[1])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unstar, $this->equalTo((new Context)->articles([])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unstar, $this->equalTo((new Context)->articles($in[0])));
        \Phake::verify(Arsse::$db, \Phake::atLeast(1))->articleMark($this->userId, $unstar, $this->equalTo((new Context)->articles($in[1])));
    }

    public function testQueryTheServerStatus(): void {
        $interval = Arsse::$conf->serviceFrequency;
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        \Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        \Phake::when(Arsse::$db)->driverCharsetAcceptable->thenReturn(true)->thenReturn(false);
        $arr1 = $arr2 = [
            'version'       => V1_2::VERSION,
            'arsse_version' => Arsse::VERSION,
            'warnings'      => [
                'improperlyConfiguredCron' => false,
                'incorrectDbCharset'       => false,
            ],
        ];
        $arr2['warnings']['improperlyConfiguredCron'] = true;
        $arr2['warnings']['incorrectDbCharset'] = true;
        $exp = HTTP::respJson($arr1);
        $this->assertMessage($exp, $this->req("GET", "/status"));
    }

    public function testCleanUpBeforeUpdate(): void {
        \Phake::when(Arsse::$db)->subscriptionCleanup()->thenReturn(true);
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("GET", "/cleanup/before-update"));
        \Phake::verify(Arsse::$db)->subscriptionCleanup();
    }

    public function testCleanUpBeforeUpdateWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => false]);
        $exp = self::error(403, "403");
        $this->assertMessage($exp, $this->req("GET", "/cleanup/before-update"));
        \Phake::verify(Arsse::$db, \Phake::never())->subscriptionCleanup(\Phake::anyParameters());
    }

    public function testCleanUpAfterUpdate(): void {
        \Phake::when(Arsse::$db)->articleCleanup()->thenReturn(true);
        $exp = HTTP::respEmpty(204);
        $this->assertMessage($exp, $this->req("GET", "/cleanup/after-update"));
        \Phake::verify(Arsse::$db)->articleCleanup();
    }

    public function testCleanUpAfterUpdateWithoutAuthority(): void {
        \Phake::when(Arsse::$user)->propertiesGet->thenReturn(['admin' => false]);
        $exp = self::error(403, "403");
        $this->assertMessage($exp, $this->req("GET", "/cleanup/after-update"));
        \Phake::verify(Arsse::$db, \Phake::never())->subscriptionCleanup(\Phake::anyParameters());
    }

    public function testQueryTheUserStatus(): void {
        $act = $this->req("GET", "/user");
        $exp = HTTP::respJson([
            'userId'             => $this->userId,
            'displayName'        => $this->userId,
            'lastLoginTimestamp' => $this->approximateTime(json_decode((string) $act->getBody(), true)['lastLoginTimestamp'], new \DateTimeImmutable),
            'avatar'             => null,
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testPreferJsonOverQueryParameters(): void {
        $in = ['name' => "Software"];
        $url = "/folders?name=Hardware";
        $out1 = ['id' => 1, 'name' => "Software"];
        $out2 = ['id' => 2, 'name' => "Hardware"];
        \Phake::when(Arsse::$db)->folderAdd($this->anything(), $this->anything())->thenReturn(2);
        \Phake::when(Arsse::$db)->folderAdd($this->anything(), $in)->thenReturn(1);
        \Phake::when(Arsse::$db)->folderPropertiesGet($this->userId, 1)->thenReturn(self::v($out1));
        \Phake::when(Arsse::$db)->folderPropertiesGet($this->userId, 2)->thenReturn(self::v($out2));
        $exp = HTTP::respJson(['folders' => [$out1]]);
        $this->assertMessage($exp, $this->req("POST", $url, json_encode($in)));
    }

    public function testMeldJsonAndQueryParameters(): void {
        $in = ['oldestFirst' => true];
        $url = "/items?type=2";
        \Phake::when(Arsse::$db)->articleList->thenReturn(new Result([]));
        $this->req("GET", $url, json_encode($in));
        \Phake::verify(Arsse::$db)->articleList($this->userId, $this->equalTo((new Context)->starred(true)->hidden(false)), $this->anything(), ["edition"]);
    }
}
