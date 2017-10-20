<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\REST\Request;
use JKingWeb\Arsse\REST\Response;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use Phake;

/** @covers \JKingWeb\Arsse\REST\NextCloudNews\V1_2<extended> */
class TestNCNV1_2 extends Test\AbstractTest {
    protected $h;
    protected $feeds = [ // expected sample output of a feed list from the database, and the resultant expected transformation by the REST handler
        'db' => [
            [
                'id' => 2112,
                'url' => 'http://example.com/news.atom',
                'favicon' => 'http://example.com/favicon.png',
                'source' => 'http://example.com/',
                'folder' => null,
                'top_folder' => null,
                'pinned' => 0,
                'err_count' => 0,
                'err_msg' => '',
                'order_type' => 0,
                'added' => '2017-05-20 13:35:54',
                'title' => 'First example feed',
                'unread' => 50048,
            ],
            [
                'id' => 42,
                'url' => 'http://example.org/news.atom',
                'favicon' => 'http://example.org/favicon.png',
                'source' => 'http://example.org/',
                'folder' => 12,
                'top_folder' => 8,
                'pinned' => 1,
                'err_count' => 0,
                'err_msg' => '',
                'order_type' => 2,
                'added' => '2017-05-20 13:35:54',
                'title' => 'Second example feed',
                'unread' => 23,
            ],
            [
                'id' => 47,
                'url' => 'http://example.net/news.atom',
                'favicon' => 'http://example.net/favicon.png',
                'source' => 'http://example.net/',
                'folder' => null,
                'top_folder' => null,
                'pinned' => 0,
                'err_count' => 0,
                'err_msg' => '',
                'order_type' => 1,
                'added' => '2017-05-20 13:35:54',
                'title' => 'Third example feed',
                'unread' => 0,
            ],
        ],
        'rest' => [
            [
                'id' => 2112,
                'url' => 'http://example.com/news.atom',
                'faviconLink' => 'http://example.com/favicon.png',
                'link' => 'http://example.com/',
                'folderId' => 0,
                'pinned' => false,
                'updateErrorCount' => 0,
                'lastUpdateError' => '',
                'ordering' => 0,
                'added' => 1495287354,
                'title' => 'First example feed',
                'unreadCount' => 50048,
            ],
            [
                'id' => 42,
                'url' => 'http://example.org/news.atom',
                'faviconLink' => 'http://example.org/favicon.png',
                'link' => 'http://example.org/',
                'folderId' => 8,
                'pinned' => true,
                'updateErrorCount' => 0,
                'lastUpdateError' => '',
                'ordering' => 2,
                'added' => 1495287354,
                'title' => 'Second example feed',
                'unreadCount' => 23,
            ],
            [
                'id' => 47,
                'url' => 'http://example.net/news.atom',
                'faviconLink' => 'http://example.net/favicon.png',
                'link' => 'http://example.net/',
                'folderId' => 0,
                'pinned' => false,
                'updateErrorCount' => 0,
                'lastUpdateError' => '',
                'ordering' => 1,
                'added' => 1495287354,
                'title' => 'Third example feed',
                'unreadCount' => 0,
            ],
        ],
    ];
    protected $articles = [
        'db' => [
            [
                'id' => 101,
                'url' => 'http://example.com/1',
                'title' => 'Article title 1',
                'author' => '',
                'content' => '<p>Article content 1</p>',
                'guid' => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
                'published_date' => '2000-01-01 00:00:00',
                'edited_date' => '2000-01-01 00:00:01',
                'modified_date' => '2000-01-01 01:00:00',
                'unread' => 1,
                'starred' => 0,
                'edition' => 101,
                'subscription' => 8,
                'fingerprint' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
                'media_url' => null,
                'media_type' => null,
            ],
            [
                'id' => 102,
                'url' => 'http://example.com/2',
                'title' => 'Article title 2',
                'author' => '',
                'content' => '<p>Article content 2</p>',
                'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'published_date' => '2000-01-02 00:00:00',
                'edited_date' => '2000-01-02 00:00:02',
                'modified_date' => '2000-01-02 02:00:00',
                'unread' => 0,
                'starred' => 0,
                'edition' => 202,
                'subscription' => 8,
                'fingerprint' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
                'media_url' => "http://example.com/text",
                'media_type' => "text/plain",
            ],
            [
                'id' => 103,
                'url' => 'http://example.com/3',
                'title' => 'Article title 3',
                'author' => '',
                'content' => '<p>Article content 3</p>',
                'guid' => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
                'published_date' => '2000-01-03 00:00:00',
                'edited_date' => '2000-01-03 00:00:03',
                'modified_date' => '2000-01-03 03:00:00',
                'unread' => 1,
                'starred' => 1,
                'edition' => 203,
                'subscription' => 9,
                'fingerprint' => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
                'media_url' => "http://example.com/video",
                'media_type' => "video/webm",
            ],
            [
                'id' => 104,
                'url' => 'http://example.com/4',
                'title' => 'Article title 4',
                'author' => '',
                'content' => '<p>Article content 4</p>',
                'guid' => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'published_date' => '2000-01-04 00:00:00',
                'edited_date' => '2000-01-04 00:00:04',
                'modified_date' => '2000-01-04 04:00:00',
                'unread' => 0,
                'starred' => 1,
                'edition' => 204,
                'subscription' => 9,
                'fingerprint' => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
                'media_url' => "http://example.com/image",
                'media_type' => "image/svg+xml",
            ],
            [
                'id' => 105,
                'url' => 'http://example.com/5',
                'title' => 'Article title 5',
                'author' => '',
                'content' => '<p>Article content 5</p>',
                'guid' => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'published_date' => '2000-01-05 00:00:00',
                'edited_date' => '2000-01-05 00:00:05',
                'modified_date' => '2000-01-05 05:00:00',
                'unread' => 1,
                'starred' => 0,
                'edition' => 305,
                'subscription' => 10,
                'fingerprint' => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
                'media_url' => "http://example.com/audio",
                'media_type' => "audio/ogg",
            ],
        ],
        'rest' => [
            [
                'guidHash' => 101,
                'url' => 'http://example.com/1',
                'title' => 'Article title 1',
                'author' => '',
                'body' => '<p>Article content 1</p>',
                'guid' => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
                'pubDate' => 946684801,
                'lastModified' => 946688400,
                'unread' => true,
                'starred' => false,
                'id' => 101,
                'feedId' => 8,
                'fingerprint' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
                'enclosureLink' => null,
                'enclosureMime' => null,
            ],
            [
                'guidHash' => 102,
                'url' => 'http://example.com/2',
                'title' => 'Article title 2',
                'author' => '',
                'body' => '<p>Article content 2</p>',
                'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'pubDate' => 946771202,
                'lastModified' => 946778400,
                'unread' => false,
                'starred' => false,
                'id' => 202,
                'feedId' => 8,
                'fingerprint' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
                'enclosureLink' => "http://example.com/text",
                'enclosureMime' => "text/plain",
            ],
            [
                'guidHash' => 103,
                'url' => 'http://example.com/3',
                'title' => 'Article title 3',
                'author' => '',
                'body' => '<p>Article content 3</p>',
                'guid' => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
                'pubDate' => 946857603,
                'lastModified' => 946868400,
                'unread' => true,
                'starred' => true,
                'id' => 203,
                'feedId' => 9,
                'fingerprint' => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
                'enclosureLink' => "http://example.com/video",
                'enclosureMime' => "video/webm",
            ],
            [
                'guidHash' => 104,
                'url' => 'http://example.com/4',
                'title' => 'Article title 4',
                'author' => '',
                'body' => '<p>Article content 4</p>',
                'guid' => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'pubDate' => 946944004,
                'lastModified' => 946958400,
                'unread' => false,
                'starred' => true,
                'id' => 204,
                'feedId' => 9,
                'fingerprint' => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
                'enclosureLink' => "http://example.com/image",
                'enclosureMime' => "image/svg+xml",
            ],
            [
                'guidHash' => 105,
                'url' => 'http://example.com/5',
                'title' => 'Article title 5',
                'author' => '',
                'body' => '<p>Article content 5</p>',
                'guid' => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'pubDate' => 947030405,
                'lastModified' => 947048400,
                'unread' => true,
                'starred' => false,
                'id' => 305,
                'feedId' => 10,
                'fingerprint' => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
                'enclosureLink' => "http://example.com/audio",
                'enclosureMime' => "audio/ogg",
            ],
        ],
    ];

    public function setUp() {
        $this->clearData();
        Arsse::$conf = new Conf();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        Phake::when(Arsse::$user)->authHTTP->thenReturn(true);
        Phake::when(Arsse::$user)->rightsGet->thenReturn(100);
        Arsse::$user->id = "john.doe@example.com";
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        Phake::when(Arsse::$db)->begin->thenReturn(Phake::mock(Transaction::class));
        $this->h = new REST\NextCloudNews\V1_2();
    }

    public function tearDown() {
        $this->clearData();
    }

    public function testRespondToInvalidPaths() {
        $errs = [
            501 => [
                ['GET',    "/"],
                ['PUT',    "/"],
                ['POST',   "/"],
                ['DELETE', "/"],
                ['GET',    "/folders/1/invalid"],
                ['PUT',    "/folders/1/invalid"],
                ['POST',   "/folders/1/invalid"],
                ['DELETE', "/folders/1/invalid"],
                ['GET',    "/version/invalid"],
                ['PUT',    "/version/invalid"],
                ['POST',   "/version/invalid"],
                ['DELETE', "/version/invalid"],
            ],
            405 => [
                'GET' => [
                    ['PUT',    "/version"],
                    ['POST',   "/version"],
                    ['DELETE', "/version"],
                ],
                'GET, POST' => [
                    ['PUT',    "/folders"],
                    ['DELETE', "/folders"],
                ],
                'PUT, DELETE' => [
                    ['GET',    "/folders/1"],
                    ['POST',   "/folders/1"],
                ],
            ],
        ];
        foreach ($errs[501] as $req) {
            $exp = new Response(501);
            list($method, $path) = $req;
            $this->assertEquals($exp, $this->h->dispatch(new Request($method, $path)), "$method call to $path did not return 501.");
        }
        foreach ($errs[405] as $allow => $cases) {
            $exp = new Response(405, "", "", ['Allow: '.$allow]);
            foreach ($cases as $req) {
                list($method, $path) = $req;
                $this->assertEquals($exp, $this->h->dispatch(new Request($method, $path)), "$method call to $path did not return 405.");
            }
        }
    }

    public function testRespondToInvalidInputTypes() {
        $exp = new Response(415, "", "", ['Accept: application/json']);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", '<data/>', 'application/xml')));
        $exp = new Response(400);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", '<data/>', 'application/json')));
    }

    public function testSendAuthenticationChallenge() {
        Phake::when(Arsse::$user)->authHTTP->thenReturn(false);
        $exp = new Response(401, "", "", ['WWW-Authenticate: Basic realm="'.REST\NextCloudNews\V1_2::REALM.'"']);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/")));
    }

    public function testListFolders() {
        $list = [
            ['id' => 1,  'name' => "Software", 'parent' => null],
            ['id' => 12, 'name' => "Hardware", 'parent' => null],
        ];
        Phake::when(Arsse::$db)->folderList(Arsse::$user->id, null, false)->thenReturn(new Result([]))->thenReturn(new Result($list));
        $exp = new Response(200, ['folders' => []]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/folders")));
        $exp = new Response(200, ['folders' => $list]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/folders")));
    }

    public function testAddAFolder() {
        $in = [
            ["name" => "Software"],
            ["name" => "Hardware"],
        ];
        $out = [
            ['id' => 1, 'name' => "Software", 'parent' => null],
            ['id' => 2, 'name' => "Hardware", 'parent' => null],
        ];
        // set of various mocks for testing
        Phake::when(Arsse::$db)->folderAdd($this->anything(), $this->anything())->thenThrow(new \Exception);
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $in[0])->thenReturn(1)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $in[1])->thenReturn(2)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 1)->thenReturn($out[0]);
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 2)->thenReturn($out[1]);
        // set up mocks that produce errors
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, [])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => ""])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => " "])->thenThrow(new ExceptionInput("whitespace"));
        // correctly add two folders, using different means
        $exp = new Response(200, ['folders' => [$out[0]]]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[0]), 'application/json')));
        $exp = new Response(200, ['folders' => [$out[1]]]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Hardware")));
        Phake::verify(Arsse::$db)->folderAdd(Arsse::$user->id, $in[0]);
        Phake::verify(Arsse::$db)->folderAdd(Arsse::$user->id, $in[1]);
        Phake::verify(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 1);
        Phake::verify(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 2);
        // test bad folder names
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":""}', 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":" "}', 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":{}}', 'application/json')));
        // try adding the same two folders again
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Software")));
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[1]), 'application/json')));
    }

    public function testRemoveAFolder() {
        Phake::when(Arsse::$db)->folderRemove(Arsse::$user->id, 1)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/1")));
        // fail on the second invocation because it no longer exists
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/1")));
        Phake::verify(Arsse::$db, Phake::times(2))->folderRemove(Arsse::$user->id, 1);
    }

    public function testRenameAFolder() {
        $in = [
            ["name" => "Software"],
            ["name" => "Software"],
            ["name" => ""],
            ["name" => " "],
            [],
        ];
        Phake::when(Arsse::$db)->folderPropertiesSet(Arsse::$user->id, 1, $in[0])->thenReturn(true);
        Phake::when(Arsse::$db)->folderPropertiesSet(Arsse::$user->id, 2, $in[1])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(Arsse::$user->id, 1, $in[2])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderPropertiesSet(Arsse::$user->id, 1, $in[3])->thenThrow(new ExceptionInput("whitespace"));
        Phake::when(Arsse::$db)->folderPropertiesSet(Arsse::$user->id, 1, $in[4])->thenReturn(true); // this should be stopped by the handler before the request gets to the database
        Phake::when(Arsse::$db)->folderPropertiesSet(Arsse::$user->id, 3, $this->anything())->thenThrow(new ExceptionInput("subjectMissing")); // folder ID 3 does not exist
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[0]), 'application/json')));
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/2", json_encode($in[1]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[2]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[3]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1", json_encode($in[4]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/3", json_encode($in[0]), 'application/json')));
    }

    public function testRetrieveServerVersion() {
        $exp = new Response(200, [
            'arsse_version' => Arsse::VERSION,
            'version' => REST\NextCloudNews\V1_2::VERSION,
            ]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/version")));
    }

    public function testListSubscriptions() {
        $exp1 = [
            'feeds' => [],
            'starredCount' => 0,
        ];
        $exp2 = [
            'feeds'        => $this->feeds['rest'],
            'starredCount' => 5,
            'newestItemId' => 4758915,
        ];
        Phake::when(Arsse::$db)->subscriptionList(Arsse::$user->id)->thenReturn(new Result([]))->thenReturn(new Result($this->feeds['db']));
        Phake::when(Arsse::$db)->articleStarred(Arsse::$user->id)->thenReturn(['total' => 0])->thenReturn(['total' => 5]);
        Phake::when(Arsse::$db)->editionLatest(Arsse::$user->id)->thenReturn(0)->thenReturn(4758915);
        $exp = new Response(200, $exp1);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds")));
        $exp = new Response(200, $exp2);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds")));
    }

    public function testAddASubscription() {
        $in = [
            ['url' => "http://example.com/news.atom", 'folderId' => 3],
            ['url' => "http://example.org/news.atom", 'folderId' => 8],
            ['url' => "http://example.net/news.atom", 'folderId' => 8],
            ['url' => "http://example.net/news.atom", 'folderId' => -1],
            [],
        ];
        $out = [
            ['feeds' => [$this->feeds['rest'][0]]],
            ['feeds' => [$this->feeds['rest'][1]], 'newestItemId' => 4758915],
            ['feeds' => [$this->feeds['rest'][2]], 'newestItemId' => 2112],
        ];
        // set up the necessary mocks
        Phake::when(Arsse::$db)->subscriptionAdd(Arsse::$user->id, "http://example.com/news.atom")->thenReturn(2112)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->subscriptionAdd(Arsse::$user->id, "http://example.org/news.atom")->thenReturn(42)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->subscriptionAdd(Arsse::$user->id, "")->thenThrow(new \JKingWeb\Arsse\Feed\Exception("", new \PicoFeed\Reader\SubscriptionNotFoundException));
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 2112)->thenReturn($this->feeds['db'][0]);
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 42)->thenReturn($this->feeds['db'][1]);
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 47)->thenReturn($this->feeds['db'][2]);
        Phake::when(Arsse::$db)->editionLatest(Arsse::$user->id, (new Context)->subscription(2112))->thenReturn(0);
        Phake::when(Arsse::$db)->editionLatest(Arsse::$user->id, (new Context)->subscription(42))->thenReturn(4758915);
        Phake::when(Arsse::$db)->editionLatest(Arsse::$user->id, (new Context)->subscription(47))->thenReturn(2112);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 2112, ['folder' =>  3])->thenThrow(new ExceptionInput("idMissing")); // folder ID 3 does not exist
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 42, ['folder' =>  8])->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 47, ['folder' => -1])->thenThrow(new ExceptionInput("typeViolation")); // folder ID -1 is invalid
        // set up a mock for a bad feed which succeeds the second time
        Phake::when(Arsse::$db)->subscriptionAdd(Arsse::$user->id, "http://example.net/news.atom")->thenThrow(new \JKingWeb\Arsse\Feed\Exception("http://example.net/news.atom", new \PicoFeed\Client\InvalidUrlException()))->thenReturn(47);
        // add the subscriptions
        $exp = new Response(200, $out[0]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[0]), 'application/json')));
        $exp = new Response(200, $out[1]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[1]), 'application/json')));
        // try to add them a second time
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[0]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[1]), 'application/json')));
        // try to add a bad feed
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[2]), 'application/json')));
        // try again (this will succeed), with an invalid folder ID
        $exp = new Response(200, $out[2]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[3]), 'application/json')));
        // try to add no feed
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[4]), 'application/json')));
    }

    public function testRemoveASubscription() {
        Phake::when(Arsse::$db)->subscriptionRemove(Arsse::$user->id, 1)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/feeds/1")));
        // fail on the second invocation because it no longer exists
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/feeds/1")));
        Phake::verify(Arsse::$db, Phake::times(2))->subscriptionRemove(Arsse::$user->id, 1);
    }

    public function testMoveASubscription() {
        $in = [
            ['folderId' =>    0],
            ['folderId' =>   42],
            ['folderId' => 2112],
            ['folderId' =>   42],
            ['folderId' =>   -1],
            [],
        ];
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, ['folder' =>   42])->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, ['folder' => null])->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, ['folder' => 2112])->thenThrow(new ExceptionInput("idMissing")); // folder does not exist
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, ['folder' =>   -1])->thenThrow(new ExceptionInput("typeViolation")); // folder is invalid
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 42, $this->anything())->thenThrow(new ExceptionInput("subjectMissing")); // subscription does not exist
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[0]), 'application/json')));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[1]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[2]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/42/move", json_encode($in[3]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[4]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[5]), 'application/json')));
    }

    public function testRenameASubscription() {
        $in = [
            ['feedTitle' => null],
            ['feedTitle' => "Ook"],
            ['feedTitle' => "   "],
            ['feedTitle' => ""],
            ['feedTitle' => false],
            ['feedTitle' => "Feed does not exist"],
            [],
        ];
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, $this->identicalTo(['title' =>  null]))->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, $this->identicalTo(['title' => "Ook"]))->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, $this->identicalTo(['title' => "   "]))->thenThrow(new ExceptionInput("whitespace"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, $this->identicalTo(['title' =>    ""]))->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 1, $this->identicalTo(['title' => false]))->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 42, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[0]), 'application/json')));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[1]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[2]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[3]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/42/rename", json_encode($in[4]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[6]), 'application/json')));
    }

    public function testListStaleFeeds() {
        $out = [
            [
                'id' => 42,
                'userId' => "",
            ],
            [
                'id' => 2112,
                'userId' => "",
            ],
        ];
        Phake::when(Arsse::$db)->feedListStale->thenReturn(array_column($out, "id"));
        $exp = new Response(200, ['feeds' => $out]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/all")));
        // retrieving the list when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new Response(403);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/all")));
    }

    public function testUpdateAFeed() {
        $in = [
            ['feedId' =>    42], // valid
            ['feedId' =>  2112], // feed does not exist
            ['feedId' => "ook"], // invalid ID
            ['feedId' =>    -1], // invalid ID
            ['feed'   =>    42], // invalid input
        ];
        Phake::when(Arsse::$db)->feedUpdate(42)->thenReturn(true);
        Phake::when(Arsse::$db)->feedUpdate(2112)->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->feedUpdate($this->lessThan(1))->thenThrow(new ExceptionInput("typeViolation"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[0]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[1]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[2]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[3]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[4]), 'application/json')));
        // updating a feed when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new Response(403);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[0]), 'application/json')));
    }

    public function testListArticles() {
        $res = new Result($this->articles['db']);
        $t = new \DateTime;
        $in = [
            ['type' => 0, 'id' => 42],   // type=0 => subscription/feed
            ['type' => 1, 'id' => 2112], // type=1 => folder
            ['type' => 0, 'id' => -1],   // type=0 => subscription/feed; invalid ID
            ['type' => 1, 'id' => -1],   // type=1 => folder; invalid ID
            ['type' => 2, 'id' => 0],    // type=2 => starred
            ['type' => 3, 'id' => 0],    // type=3 => all (default); base context
            ['oldestFirst' => true, 'batchSize' => 10, 'offset' => 5],
            ['oldestFirst' => false, 'batchSize' => 5, 'offset' => 5],
            ['getRead' => true], // base context
            ['getRead' => false],
            ['lastModified' => $t->getTimestamp()],
            ['oldestFirst' => false, 'batchSize' => 5, 'offset' => 0], // offset=0 should not set the latestEdition context
        ];
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, $this->anything())->thenReturn($res);
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(42))->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(2112))->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(-1))->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(-1))->thenThrow(new ExceptionInput("typeViolation"));
        $exp = new Response(200, ['items' => $this->articles['rest']]);
        // check the contents of the response
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/items"))); // first instance of base context
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/items/updated"))); // second instance of base context
        // check error conditions
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/items", json_encode($in[0]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/items", json_encode($in[1]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/items", json_encode($in[2]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/items", json_encode($in[3]), 'application/json')));
        // simply run through the remainder of the input for later method verification
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[4]), 'application/json'));
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[5]), 'application/json')); // third instance of base context
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[6]), 'application/json'));
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[7]), 'application/json'));
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[8]), 'application/json')); // fourth instance of base context
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[9]), 'application/json'));
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[10]), 'application/json'));
        $this->h->dispatch(new Request("GET", "/items", json_encode($in[11]), 'application/json'));
        // perform method verifications
        Phake::verify(Arsse::$db, Phake::times(4))->articleList(Arsse::$user->id, (new Context)->reverse(true));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(42));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(2112));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(-1));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(-1));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->starred(true));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(false)->limit(10)->oldestEdition(6)); // offset is one more than specified
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->limit(5)->latestEdition(4));   // offset is one less than specified
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->unread(true));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->modifiedSince($t));
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->limit(5));
    }

    public function testMarkAFolderRead() {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->folder(1)->latestEdition(2112))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->folder(42)->latestEdition(2112))->thenThrow(new ExceptionInput("idMissing")); // folder doesn't exist
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1/read", $in, 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1/read?newestItemId=2112")));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1/read")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/1/read?newestItemId=ook")));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/folders/42/read", $in, 'application/json')));
    }

    public function testMarkASubscriptionRead() {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->subscription(1)->latestEdition(2112))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->subscription(42)->latestEdition(2112))->thenThrow(new ExceptionInput("idMissing")); // subscription doesn't exist
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/read", $in, 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/read?newestItemId=2112")));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/read")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/read?newestItemId=ook")));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/42/read", $in, 'application/json')));
    }

    public function testMarkAllItemsRead() {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->latestEdition(2112))->thenReturn(42);
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read", $in, 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read?newestItemId=2112")));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read?newestItemId=ook")));
    }

    public function testChangeMarksOfASingleArticle() {
        $read = ['read' => true];
        $unread = ['read' => false];
        $star = ['starred' => true];
        $unstar = ['starred' => false];
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->edition(1))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->edition(42))->thenThrow(new ExceptionInput("subjectMissing")); // edition doesn't exist doesn't exist
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $unread, (new Context)->edition(2))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $unread, (new Context)->edition(47))->thenThrow(new ExceptionInput("subjectMissing")); // edition doesn't exist doesn't exist
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $star, (new Context)->article(3))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $star, (new Context)->article(2112))->thenThrow(new ExceptionInput("subjectMissing")); // article doesn't exist doesn't exist
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $unstar, (new Context)->article(4))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $unstar, (new Context)->article(1337))->thenThrow(new ExceptionInput("subjectMissing")); // article doesn't exist doesn't exist
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/1/read")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/2/unread")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/1/3/star")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/4400/4/unstar")));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/42/read")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/47/unread")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/1/2112/star")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/4400/1337/unstar")));
        Phake::verify(Arsse::$db, Phake::times(8))->articleMark(Arsse::$user->id, $this->anything(), $this->anything());
    }

    public function testChangeMarksOfMultipleArticles() {
        $read = ['read' => true];
        $unread = ['read' => false];
        $star = ['starred' => true];
        $unstar = ['starred' => false];
        $in = [
            ["ook","eek","ack"],
            range(100, 199),
            range(100, 149),
            range(150, 199),
        ];
        $inStar = $in;
        for ($a = 0; $a < sizeof($inStar); $a++) {
            for ($b = 0; $b < sizeof($inStar[$a]); $b++) {
                $inStar[$a][$b] = ['feedId' => 2112, 'guidHash' => $inStar[$a][$b]];
            }
        }
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), $this->anything())->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), (new Context)->editions([]))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), (new Context)->editions($in[1]))->thenThrow(new ExceptionInput("tooLong")); // data model function limited to 50 items for multiples
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), (new Context)->articles([]))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), (new Context)->articles($in[1]))->thenThrow(new ExceptionInput("tooLong")); // data model function limited to 50 items for multiples
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read/multiple")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unread/multiple")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/star/multiple")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unstar/multiple")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read/multiple", json_encode(['items' => "ook"]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unread/multiple", json_encode(['items' => "ook"]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/star/multiple", json_encode(['items' => "ook"]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unstar/multiple", json_encode(['items' => "ook"]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read/multiple", json_encode(['items' => []]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unread/multiple", json_encode(['items' => []]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read/multiple", json_encode(['items' => $in[0]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unread/multiple", json_encode(['items' => $in[0]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/read/multiple", json_encode(['items' => $in[1]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unread/multiple", json_encode(['items' => $in[1]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/star/multiple", json_encode(['items' => []]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unstar/multiple", json_encode(['items' => []]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/star/multiple", json_encode(['items' => $inStar[0]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unstar/multiple", json_encode(['items' => $inStar[0]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/star/multiple", json_encode(['items' => $inStar[1]]), 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/items/unstar/multiple", json_encode(['items' => $inStar[1]]), 'application/json')));
        // ensure the data model was queried appropriately for read/unread
        Phake::verify(Arsse::$db, Phake::times(2))->articleMark(Arsse::$user->id, $read, (new Context)->editions([]));
        Phake::verify(Arsse::$db, Phake::times(2))->articleMark(Arsse::$user->id, $read, (new Context)->editions($in[0]));
        Phake::verify(Arsse::$db, Phake::times(0))->articleMark(Arsse::$user->id, $read, (new Context)->editions($in[1]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->editions($in[2]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->editions($in[3]));
        Phake::verify(Arsse::$db, Phake::times(2))->articleMark(Arsse::$user->id, $unread, (new Context)->editions([]));
        Phake::verify(Arsse::$db, Phake::times(2))->articleMark(Arsse::$user->id, $unread, (new Context)->editions($in[0]));
        Phake::verify(Arsse::$db, Phake::times(0))->articleMark(Arsse::$user->id, $unread, (new Context)->editions($in[1]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $unread, (new Context)->editions($in[2]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $unread, (new Context)->editions($in[3]));
        // ensure the data model was queried appropriately for star/unstar
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $star, (new Context)->articles([]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $star, (new Context)->articles($in[0]));
        Phake::verify(Arsse::$db, Phake::times(0))->articleMark(Arsse::$user->id, $star, (new Context)->articles($in[1]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $star, (new Context)->articles($in[2]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $star, (new Context)->articles($in[3]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $unstar, (new Context)->articles([]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $unstar, (new Context)->articles($in[0]));
        Phake::verify(Arsse::$db, Phake::times(0))->articleMark(Arsse::$user->id, $unstar, (new Context)->articles($in[1]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $unstar, (new Context)->articles($in[2]));
        Phake::verify(Arsse::$db)->articleMark(Arsse::$user->id, $unstar, (new Context)->articles($in[3]));
    }

    public function testQueryTheServerStatus() {
        $interval = Service::interval();
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        $arr1 = $arr2 = [
            'version' => REST\NextCloudNews\V1_2::VERSION,
            'arsse_version' => Arsse::VERSION,
            'warnings' => [
                'improperlyConfiguredCron' => false,
            ]
        ];
        $arr2['warnings']['improperlyConfiguredCron'] = true;
        $exp = new Response(200, $arr1);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/status")));
    }

    public function testCleanUpBeforeUpdate() {
        Phake::when(Arsse::$db)->feedCleanup()->thenReturn(true);
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/cleanup/before-update")));
        Phake::verify(Arsse::$db)->feedCleanup();
        // performing a cleanup when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new Response(403);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/cleanup/before-update")));
    }
    
    public function testCleanUpAfterUpdate() {
        Phake::when(Arsse::$db)->articleCleanup()->thenReturn(true);
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/cleanup/after-update")));
        Phake::verify(Arsse::$db)->articleCleanup();
        // performing a cleanup when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new Response(403);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/cleanup/after-update")));
    }
}
