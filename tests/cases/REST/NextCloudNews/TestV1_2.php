<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\NextCloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\NextCloudNews\V1_2;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;
use Phake;

/** @covers \JKingWeb\Arsse\REST\NextCloudNews\V1_2<extended> */
class TestV1_2 extends \JKingWeb\Arsse\Test\AbstractTest {
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
                'err_msg' => null,
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
                'title' => 'First example feed',
                'added' => 1495287354,
                'pinned' => false,
                'link' => 'http://example.com/',
                'faviconLink' => 'http://example.com/favicon.png',
                'folderId' => 0,
                'unreadCount' => 50048,
                'ordering' => 0,
                'updateErrorCount' => 0,
                'lastUpdateError' => '',
            ],
            [
                'id' => 42,
                'url' => 'http://example.org/news.atom',
                'title' => 'Second example feed',
                'added' => 1495287354,
                'pinned' => true,
                'link' => 'http://example.org/',
                'faviconLink' => 'http://example.org/favicon.png',
                'folderId' => 8,
                'unreadCount' => 23,
                'ordering' => 2,
                'updateErrorCount' => 0,
                'lastUpdateError' => '',
            ],
            [
                'id' => 47,
                'url' => 'http://example.net/news.atom',
                'title' => 'Third example feed',
                'added' => 1495287354,
                'pinned' => false,
                'link' => 'http://example.net/',
                'faviconLink' => 'http://example.net/favicon.png',
                'folderId' => 0,
                'unreadCount' => 0,
                'ordering' => 1,
                'updateErrorCount' => 0,
                'lastUpdateError' => '',
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
                'id' => 101,
                'guid' => 'e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda',
                'guidHash' => "101",
                'url' => 'http://example.com/1',
                'title' => 'Article title 1',
                'author' => '',
                'pubDate' => 946684801,
                'body' => '<p>Article content 1</p>',
                'enclosureMime' => "",
                'enclosureLink' => "",
                'feedId' => 8,
                'unread' => true,
                'starred' => false,
                'lastModified' => 946688400,
                'fingerprint' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
            ],
            [
                'id' => 202,
                'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'guidHash' => "102",
                'url' => 'http://example.com/2',
                'title' => 'Article title 2',
                'author' => '',
                'pubDate' => 946771202,
                'body' => '<p>Article content 2</p>',
                'enclosureMime' => "text/plain",
                'enclosureLink' => "http://example.com/text",
                'feedId' => 8,
                'unread' => false,
                'starred' => false,
                'lastModified' => 946778400,
                'fingerprint' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
            ],
            [
                'id' => 203,
                'guid' => '31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92',
                'guidHash' => "103",
                'url' => 'http://example.com/3',
                'title' => 'Article title 3',
                'author' => '',
                'pubDate' => 946857603,
                'body' => '<p>Article content 3</p>',
                'enclosureMime' => "video/webm",
                'enclosureLink' => "http://example.com/video",
                'feedId' => 9,
                'unread' => true,
                'starred' => true,
                'lastModified' => 946868400,
                'fingerprint' => 'f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b:b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406:ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b',
            ],
            [
                'id' => 204,
                'guid' => '804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180',
                'guidHash' => "104",
                'url' => 'http://example.com/4',
                'title' => 'Article title 4',
                'author' => '',
                'pubDate' => 946944004,
                'body' => '<p>Article content 4</p>',
                'enclosureMime' => "image/svg+xml",
                'enclosureLink' => "http://example.com/image",
                'feedId' => 9,
                'unread' => false,
                'starred' => true,
                'lastModified' => 946958400,
                'fingerprint' => 'f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8:f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3:ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9',
            ],
            [
                'id' => 305,
                'guid' => 'db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41',
                'guidHash' => "105",
                'url' => 'http://example.com/5',
                'title' => 'Article title 5',
                'author' => '',
                'pubDate' => 947030405,
                'body' => '<p>Article content 5</p>',
                'enclosureMime' => "audio/ogg",
                'enclosureLink' => "http://example.com/audio",
                'feedId' => 10,
                'unread' => true,
                'starred' => false,
                'lastModified' => 947048400,
                'fingerprint' => 'd40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022:834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900:43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba',
            ],
        ],
    ];

    protected function req(string $method, string $target, string $data = "", array $headers = []): ResponseInterface {
        $url = "/index.php/apps/news/api/v1-2".$target;
        $server = [
            'REQUEST_METHOD'    => $method,
            'REQUEST_URI'       => $url,
            'PHP_AUTH_USER'     => "john.doe@example.com",
            'PHP_AUTH_PW'       => "secret",
            'REMOTE_USER'       => "john.doe@example.com",
        ];
        if (strlen($data)) {
            $server['HTTP_CONTENT_TYPE'] = "application/json";
        }
        $req = new ServerRequest($server, [], $url, $method, "php://memory");
        foreach($headers as $key => $value) {
            if (!is_null($value)) {
                $req = $req->withHeader($key, $value);
            } else {
                $req = $req->withoutHeader($key);
            }
        }
        if (strlen($data)) {
            $body = $req->getBody();
            $body->write($data);
            $req = $req->withBody($body);
        }
        $q = $req->getUri()->getQuery();
        if (strlen($q)) {
            parse_str($q, $q);
            $req = $req->withQueryParams($q);
        }
        $req = $req->withRequestTarget($target);
        return $this->h->dispatch($req);
    }

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
        $this->h = new V1_2();
    }

    public function tearDown() {
        $this->clearData();
    }

    public function testSendAuthenticationChallenge() {
        Phake::when(Arsse::$user)->authHTTP->thenReturn(false);
        $exp = new EmptyResponse(401, ['WWW-Authenticate' => 'Basic realm="'.V1_2::REALM.'"']);
        $this->assertResponse($exp, $this->req("GET", "/"));
    }

    public function testRespondToInvalidPaths() {
        $errs = [
            404 => [
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
        foreach ($errs[404] as $req) {
            $exp = new EmptyResponse(404);
            list($method, $path) = $req;
            $this->assertResponse($exp, $this->req($method, $path), "$method call to $path did not return 404.");
        }
        foreach ($errs[405] as $allow => $cases) {
            $exp = new EmptyResponse(405, ['Allow' => $allow]);
            foreach ($cases as $req) {
                list($method, $path) = $req;
                $this->assertResponse($exp, $this->req($method, $path), "$method call to $path did not return 405.");
            }
        }
    }

    public function testRespondToInvalidInputTypes() {
        $exp = new EmptyResponse(415, ['Accept' => "application/json"]);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", '<data/>', ['Content-Type' => "application/xml"]));
        $exp = new EmptyResponse(400);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", '<data/>'));
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", '<data/>', ['Content-Type' => null]));
    }

    public function testRespondToOptionsRequests() {
        $exp = new EmptyResponse(204, [
            'Allow'  => "HEAD,GET,POST",
            'Accept' => "application/json",
        ]);
        $this->assertResponse($exp, $this->req("OPTIONS", "/feeds"));
        $exp = new EmptyResponse(204, [
            'Allow'  => "DELETE",
            'Accept' => "application/json",
        ]);
        $this->assertResponse($exp, $this->req("OPTIONS", "/feeds/2112"));
        $exp = new EmptyResponse(204, [
            'Allow'  => "HEAD,GET",
            'Accept' => "application/json",
        ]);
        $this->assertResponse($exp, $this->req("OPTIONS", "/user"));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("OPTIONS", "/invalid/path"));
    }

    public function testListFolders() {
        $list = [
            ['id' => 1,  'name' => "Software", 'parent' => null],
            ['id' => "12", 'name' => "Hardware", 'parent' => null],
        ];
        $out = [
            ['id' => 1,  'name' => "Software"],
            ['id' => 12, 'name' => "Hardware"],
        ];
        Phake::when(Arsse::$db)->folderList(Arsse::$user->id, null, false)->thenReturn(new Result([]))->thenReturn(new Result($list));
        $exp = new Response(['folders' => []]);
        $this->assertResponse($exp, $this->req("GET", "/folders"));
        $exp = new Response(['folders' => $out]);
        $this->assertResponse($exp, $this->req("GET", "/folders"));
    }

    public function testAddAFolder() {
        $in = [
            ["name" => "Software"],
            ["name" => "Hardware"],
        ];
        $db = [
            ['id' => 1,   'name' => "Software", 'parent' => null],
            ['id' => "2", 'name' => "Hardware", 'parent' => null],
        ];
        $out = [
            ['id' => 1, 'name' => "Software"],
            ['id' => 2, 'name' => "Hardware"],
        ];
        // set of various mocks for testing
        Phake::when(Arsse::$db)->folderAdd($this->anything(), $this->anything())->thenThrow(new \Exception);
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $in[0])->thenReturn(1)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $in[1])->thenReturn(2)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 1)->thenReturn($db[0]);
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 2)->thenReturn($db[1]);
        // set up mocks that produce errors
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, [])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => ""])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => " "])->thenThrow(new ExceptionInput("whitespace"));
        // correctly add two folders, using different means
        $exp = new Response(['folders' => [$out[0]]]);
        $this->assertResponse($exp, $this->req("POST", "/folders", json_encode($in[0])));
        $exp = new Response(['folders' => [$out[1]]]);
        $this->assertResponse($exp, $this->req("POST", "/folders?name=Hardware"));
        Phake::verify(Arsse::$db)->folderAdd(Arsse::$user->id, $in[0]);
        Phake::verify(Arsse::$db)->folderAdd(Arsse::$user->id, $in[1]);
        Phake::verify(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 1);
        Phake::verify(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 2);
        // test bad folder names
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("POST", "/folders"));
        $this->assertResponse($exp, $this->req("POST", "/folders", '{"name":""}'));
        $this->assertResponse($exp, $this->req("POST", "/folders", '{"name":" "}'));
        $this->assertResponse($exp, $this->req("POST", "/folders", '{"name":{}}'));
        // try adding the same two folders again
        $exp = new EmptyResponse(409);
        $this->assertResponse($exp, $this->req("POST", "/folders?name=Software"));
        $exp = new EmptyResponse(409);
        $this->assertResponse($exp, $this->req("POST", "/folders", json_encode($in[1])));
    }

    public function testRemoveAFolder() {
        Phake::when(Arsse::$db)->folderRemove(Arsse::$user->id, 1)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("DELETE", "/folders/1"));
        // fail on the second invocation because it no longer exists
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("DELETE", "/folders/1"));
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
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", json_encode($in[0])));
        $exp = new EmptyResponse(409);
        $this->assertResponse($exp, $this->req("PUT", "/folders/2", json_encode($in[1])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", json_encode($in[2])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", json_encode($in[3])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1", json_encode($in[4])));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("PUT", "/folders/3", json_encode($in[0])));
    }

    public function testRetrieveServerVersion() {
        $exp = new Response([
            'version' => V1_2::VERSION,
            'arsse_version' => Arsse::VERSION,
            ]);
        $this->assertResponse($exp, $this->req("GET", "/version"));
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
        $exp = new Response($exp1);
        $this->assertResponse($exp, $this->req("GET", "/feeds"));
        $exp = new Response($exp2);
        $this->assertResponse($exp, $this->req("GET", "/feeds"));
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
        $exp = new Response($out[0]);
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[0])));
        $exp = new Response($out[1]);
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[1])));
        // try to add them a second time
        $exp = new EmptyResponse(409);
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[0])));
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[1])));
        // try to add a bad feed
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[2])));
        // try again (this will succeed), with an invalid folder ID
        $exp = new Response($out[2]);
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[3])));
        // try to add no feed
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("POST", "/feeds", json_encode($in[4])));
    }

    public function testRemoveASubscription() {
        Phake::when(Arsse::$db)->subscriptionRemove(Arsse::$user->id, 1)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("DELETE", "/feeds/1"));
        // fail on the second invocation because it no longer exists
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("DELETE", "/feeds/1"));
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
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/move", json_encode($in[0])));
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/move", json_encode($in[1])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/move", json_encode($in[2])));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/42/move", json_encode($in[3])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/move", json_encode($in[4])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/move", json_encode($in[5])));
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
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/rename", json_encode($in[0])));
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/rename", json_encode($in[1])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/rename", json_encode($in[2])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/rename", json_encode($in[3])));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/42/rename", json_encode($in[4])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/rename", json_encode($in[6])));
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
        $exp = new Response(['feeds' => $out]);
        $this->assertResponse($exp, $this->req("GET", "/feeds/all"));
        // retrieving the list when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new EmptyResponse(403);
        $this->assertResponse($exp, $this->req("GET", "/feeds/all"));
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
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("GET", "/feeds/update", json_encode($in[0])));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("GET", "/feeds/update", json_encode($in[1])));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("GET", "/feeds/update", json_encode($in[2])));
        $this->assertResponse($exp, $this->req("GET", "/feeds/update", json_encode($in[3])));
        $this->assertResponse($exp, $this->req("GET", "/feeds/update", json_encode($in[4])));
        // updating a feed when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new EmptyResponse(403);
        $this->assertResponse($exp, $this->req("GET", "/feeds/update", json_encode($in[0])));
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
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, $this->anything(), Database::LIST_TYPICAL)->thenReturn($res);
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(42), Database::LIST_TYPICAL)->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(2112), Database::LIST_TYPICAL)->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(-1), Database::LIST_TYPICAL)->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(-1), Database::LIST_TYPICAL)->thenThrow(new ExceptionInput("typeViolation"));
        $exp = new Response(['items' => $this->articles['rest']]);
        // check the contents of the response
        $this->assertResponse($exp, $this->req("GET", "/items")); // first instance of base context
        $this->assertResponse($exp, $this->req("GET", "/items/updated")); // second instance of base context
        // check error conditions
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("GET", "/items", json_encode($in[0])));
        $this->assertResponse($exp, $this->req("GET", "/items", json_encode($in[1])));
        $this->assertResponse($exp, $this->req("GET", "/items", json_encode($in[2])));
        $this->assertResponse($exp, $this->req("GET", "/items", json_encode($in[3])));
        // simply run through the remainder of the input for later method verification
        $this->req("GET", "/items", json_encode($in[4]));
        $this->req("GET", "/items", json_encode($in[5])); // third instance of base context
        $this->req("GET", "/items", json_encode($in[6]));
        $this->req("GET", "/items", json_encode($in[7]));
        $this->req("GET", "/items", json_encode($in[8])); // fourth instance of base context
        $this->req("GET", "/items", json_encode($in[9]));
        $this->req("GET", "/items", json_encode($in[10]));
        $this->req("GET", "/items", json_encode($in[11]));
        // perform method verifications
        Phake::verify(Arsse::$db, Phake::times(4))->articleList(Arsse::$user->id, (new Context)->reverse(true), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(42), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(2112), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->subscription(-1), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->folder(-1), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->starred(true), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(false)->limit(10)->oldestEdition(6), Database::LIST_TYPICAL); // offset is one more than specified
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->limit(5)->latestEdition(4), Database::LIST_TYPICAL);   // offset is one less than specified
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->unread(true), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->markedSince($t), Database::LIST_TYPICAL);
        Phake::verify(Arsse::$db)->articleList(Arsse::$user->id, (new Context)->reverse(true)->limit(5), Database::LIST_TYPICAL);
    }

    public function testMarkAFolderRead() {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->folder(1)->latestEdition(2112))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->folder(42)->latestEdition(2112))->thenThrow(new ExceptionInput("idMissing")); // folder doesn't exist
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1/read", $in));
        $this->assertResponse($exp, $this->req("PUT", "/folders/1/read?newestItemId=2112"));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/folders/1/read"));
        $this->assertResponse($exp, $this->req("PUT", "/folders/1/read?newestItemId=ook"));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("PUT", "/folders/42/read", $in));
    }

    public function testMarkASubscriptionRead() {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->subscription(1)->latestEdition(2112))->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->subscription(42)->latestEdition(2112))->thenThrow(new ExceptionInput("idMissing")); // subscription doesn't exist
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/read", $in));
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/read?newestItemId=2112"));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/read"));
        $this->assertResponse($exp, $this->req("PUT", "/feeds/1/read?newestItemId=ook"));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("PUT", "/feeds/42/read", $in));
    }

    public function testMarkAllItemsRead() {
        $read = ['read' => true];
        $in = json_encode(['newestItemId' => 2112]);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $read, (new Context)->latestEdition(2112))->thenReturn(42);
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/items/read", $in));
        $this->assertResponse($exp, $this->req("PUT", "/items/read?newestItemId=2112"));
        $exp = new EmptyResponse(422);
        $this->assertResponse($exp, $this->req("PUT", "/items/read"));
        $this->assertResponse($exp, $this->req("PUT", "/items/read?newestItemId=ook"));
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
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/items/1/read"));
        $this->assertResponse($exp, $this->req("PUT", "/items/2/unread"));
        $this->assertResponse($exp, $this->req("PUT", "/items/1/3/star"));
        $this->assertResponse($exp, $this->req("PUT", "/items/4400/4/unstar"));
        $exp = new EmptyResponse(404);
        $this->assertResponse($exp, $this->req("PUT", "/items/42/read"));
        $this->assertResponse($exp, $this->req("PUT", "/items/47/unread"));
        $this->assertResponse($exp, $this->req("PUT", "/items/1/2112/star"));
        $this->assertResponse($exp, $this->req("PUT", "/items/4400/1337/unstar"));
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
        ];
        $inStar = $in;
        for ($a = 0; $a < sizeof($inStar); $a++) {
            for ($b = 0; $b < sizeof($inStar[$a]); $b++) {
                $inStar[$a][$b] = ['feedId' => 2112, 'guidHash' => $inStar[$a][$b]];
            }
        }
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), $this->anything())->thenReturn(42);
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), (new Context)->editions([]))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        Phake::when(Arsse::$db)->articleMark(Arsse::$user->id, $this->anything(), (new Context)->articles([]))->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("PUT", "/items/read/multiple"));
        $this->assertResponse($exp, $this->req("PUT", "/items/unread/multiple"));
        $this->assertResponse($exp, $this->req("PUT", "/items/star/multiple"));
        $this->assertResponse($exp, $this->req("PUT", "/items/unstar/multiple"));
        $this->assertResponse($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => "ook"])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => "ook"])));
        $this->assertResponse($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => "ook"])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => "ook"])));
        $this->assertResponse($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => []])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => []])));
        $this->assertResponse($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => $in[0]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => $in[0]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/read/multiple", json_encode(['items' => $in[1]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unread/multiple", json_encode(['items' => $in[1]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => []])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => []])));
        $this->assertResponse($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => $inStar[0]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => $inStar[0]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/star/multiple", json_encode(['items' => $inStar[1]])));
        $this->assertResponse($exp, $this->req("PUT", "/items/unstar/multiple", json_encode(['items' => $inStar[1]])));
        // ensure the data model was queried appropriately for read/unread
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $read, (new Context)->editions([]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $read, (new Context)->editions($in[0]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $read, (new Context)->editions($in[1]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $unread, (new Context)->editions([]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $unread, (new Context)->editions($in[0]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $unread, (new Context)->editions($in[1]));
        // ensure the data model was queried appropriately for star/unstar
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $star, (new Context)->articles([]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $star, (new Context)->articles($in[0]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $star, (new Context)->articles($in[1]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $unstar, (new Context)->articles([]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $unstar, (new Context)->articles($in[0]));
        Phake::verify(Arsse::$db, Phake::atLeast(1))->articleMark(Arsse::$user->id, $unstar, (new Context)->articles($in[1]));
    }

    public function testQueryTheServerStatus() {
        $interval = Service::interval();
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        Phake::when(Arsse::$db)->driverCharsetAcceptable->thenReturn(true)->thenReturn(false);
        $arr1 = $arr2 = [
            'version' => V1_2::VERSION,
            'arsse_version' => Arsse::VERSION,
            'warnings' => [
                'improperlyConfiguredCron' => false,
                'incorrectDbCharset' => false,
            ]
        ];
        $arr2['warnings']['improperlyConfiguredCron'] = true;
        $arr2['warnings']['incorrectDbCharset'] = true;
        $exp = new Response($arr1);
        $this->assertResponse($exp, $this->req("GET", "/status"));
    }

    public function testCleanUpBeforeUpdate() {
        Phake::when(Arsse::$db)->feedCleanup()->thenReturn(true);
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("GET", "/cleanup/before-update"));
        Phake::verify(Arsse::$db)->feedCleanup();
        // performing a cleanup when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new EmptyResponse(403);
        $this->assertResponse($exp, $this->req("GET", "/cleanup/before-update"));
    }
    
    public function testCleanUpAfterUpdate() {
        Phake::when(Arsse::$db)->articleCleanup()->thenReturn(true);
        $exp = new EmptyResponse(204);
        $this->assertResponse($exp, $this->req("GET", "/cleanup/after-update"));
        Phake::verify(Arsse::$db)->articleCleanup();
        // performing a cleanup when not an admin fails
        Phake::when(Arsse::$user)->rightsGet->thenReturn(0);
        $exp = new EmptyResponse(403);
        $this->assertResponse($exp, $this->req("GET", "/cleanup/after-update"));
    }
}
