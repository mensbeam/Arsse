<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use JKingWeb\Arsse\REST\Request;
use JKingWeb\Arsse\REST\Response;
use JKingWeb\Arsse\Test\Result;
use Phake;


class TestNCNV1_2 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

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
                'added' => 1495287354,
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
                'added' => 1495287354,
                'title' => 'Second example feed',
                'unread' => 23,
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
        ],
    ];

    function setUp() {
        $this->clearData();
        // create a mock user manager
        Data::$user = Phake::mock(User::class);
        Phake::when(Data::$user)->authHTTP->thenReturn(true);
        Phake::when(Data::$user)->rightsGet->thenReturn(100);
        Data::$user->id = "john.doe@example.com";
        // create a mock database interface
        Data::$db = Phake::mock(Database::Class);
        $this->h = new REST\NextCloudNews\V1_2();
    }

    function tearDown() {
        $this->clearData();
    }

    function testRespondToInvalidPaths() {
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
        foreach($errs[501] as $req) {
            $exp = new Response(501);
            list($method, $path) = $req;
            $this->assertEquals($exp, $this->h->dispatch(new Request($method, $path)), "$method call to $path did not return 501.");
        }
        foreach($errs[405] as $allow => $cases) {
            $exp = new Response(405, "", "", ['Allow: '.$allow]);
            foreach($cases as $req) {
                list($method, $path) = $req;
                $this->assertEquals($exp, $this->h->dispatch(new Request($method, $path)), "$method call to $path did not return 405.");
            }
        }
    }

    function testReceiveAuthenticationChallenge() {
        Phake::when(Data::$user)->authHTTP->thenReturn(false);
        $exp = new Response(401, "", "", ['WWW-Authenticate: Basic realm="'.REST\NextCloudNews\V1_2::REALM.'"']);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/")));
    }

    function testListFolders() {
        $list = [
            ['id' => 1,  'name' => "Software", 'parent' => null],
            ['id' => 12, 'name' => "Hardware", 'parent' => null],
        ];
        Phake::when(Data::$db)->folderList(Data::$user->id, null, false)->thenReturn(new Result([]))->thenReturn(new Result($list));
        $exp = new Response(200, ['folders' => []]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/folders")));
        $exp = new Response(200, ['folders' => $list]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/folders")));
    }

    function testAddAFolder() {
        $in = [
            ["name" => "Software"],
            ["name" => "Hardware"],
        ];
        $out = [
            ['id' => 1, 'name' => "Software", 'parent' => null],
            ['id' => 2, 'name' => "Hardware", 'parent' => null],
        ];
        // set of various mocks for testing
        Phake::when(Data::$db)->folderAdd(Data::$user->id, $in[0])->thenReturn(1)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Data::$db)->folderAdd(Data::$user->id, $in[1])->thenReturn(2)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Data::$db)->folderPropertiesGet(Data::$user->id, 1)->thenReturn($out[0]);
        Phake::when(Data::$db)->folderPropertiesGet(Data::$user->id, 2)->thenReturn($out[1]);
        // set up mocks that produce errors
        Phake::when(Data::$db)->folderAdd(Data::$user->id, [])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
        Phake::when(Data::$db)->folderAdd(Data::$user->id, ['name' => ""])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
        Phake::when(Data::$db)->folderAdd(Data::$user->id, ['name' => " "])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("whitespace"));
        // correctly add two folders, using different means
        $exp = new Response(200, ['folders' => [$out[0]]]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[0]), 'application/json')));
        $exp = new Response(200, ['folders' => [$out[1]]]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Hardware")));
        Phake::verify(Data::$db)->folderAdd(Data::$user->id, $in[0]);
        Phake::verify(Data::$db)->folderAdd(Data::$user->id, $in[1]);
        Phake::verify(Data::$db)->folderPropertiesGet(Data::$user->id, 1);
        Phake::verify(Data::$db)->folderPropertiesGet(Data::$user->id, 2);
        // test bad folder names
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders")));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":""}', 'application/json')));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", '{"name":" "}', 'application/json')));
        // try adding the same two folders again
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders?name=Software")));
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/folders", json_encode($in[1]), 'application/json')));
    }

    function testRemoveAFolder() {
        Phake::when(Data::$db)->folderRemove(Data::$user->id, 1)->thenReturn(true)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/1")));
        // fail on the second invocation because it no longer exists
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/folders/1")));
        Phake::verify(Data::$db, Phake::times(2))->folderRemove(Data::$user->id, 1);
    }

    function testRenameAFolder() {
        $in = [
            ["name" => "Software"],
            ["name" => "Software"],
            ["name" => ""],
            ["name" => " "],
            [],
        ];
        Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[0])->thenReturn(true);
        Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 2, $in[1])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation"));
        Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[2])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
        Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[3])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("whitespace"));
        Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 1, $in[4])->thenReturn(true); // this should be stopped by the handler before the request gets to the database
        Phake::when(Data::$db)->folderPropertiesSet(Data::$user->id, 3, $this->anything())->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing")); // folder ID 3 does not exist
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

    function testRetrieveServerVersion() {
        $exp = new Response(200, ['version' => \JKingWeb\Arsse\VERSION]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/version")));
    }

    function testListSubscriptions() {
        $exp1 = [
            'feeds' => [],
            'starredCount' => 0,
        ];
        $exp2 = [
            'feeds'        => $this->feeds['rest'],
            'starredCount' => 5,
            'newestItemId' => 4758915,
        ];
        Phake::when(Data::$db)->subscriptionList(Data::$user->id)->thenReturn(new Result([]))->thenReturn(new Result($this->feeds['db']));
        Phake::when(Data::$db)->articleStarredCount(Data::$user->id)->thenReturn(0)->thenReturn(5);
        Phake::when(Data::$db)->editionLatest(Data::$user->id)->thenReturn(0)->thenReturn(4758915);
        $exp = new Response(200, $exp1);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds")));
        $exp = new Response(200, $exp2);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds")));
        // make sure the correct date format is actually requested
        Phake::verify(Data::$db, Phake::atLeast(1))->dateFormatDefault("unix");
    }

    function testAddASubscription() {
        $in = [
            ['url' => "http://example.com/news.atom", 'folderId' => 3],
            ['url' => "http://example.org/news.atom", 'folderId' => 8],
            ['url' => "http://example.net/news.atom", 'folderId' => 0],
        ];
        $out = [
            ['feeds' => [$this->feeds['rest'][0]]],
            ['feeds' => [$this->feeds['rest'][1]], 'newestItemId' => 4758915],
            [],
        ];
        // set up the necessary mocks
        Phake::when(Data::$db)->subscriptionAdd(Data::$user->id, "http://example.com/news.atom")->thenReturn(2112)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Data::$db)->subscriptionAdd(Data::$user->id, "http://example.org/news.atom")->thenReturn( 42 )->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Data::$db)->subscriptionPropertiesGet(Data::$user->id, 2112)->thenReturn($this->feeds['db'][0]);
        Phake::when(Data::$db)->subscriptionPropertiesGet(Data::$user->id,   42)->thenReturn($this->feeds['db'][1]);
        Phake::when(Data::$db)->editionLatest(Data::$user->id, ['subscription' => 2112])->thenReturn(0);
        Phake::when(Data::$db)->editionLatest(Data::$user->id, ['subscription' =>   42])->thenReturn(4758915);
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 2112, ['folder' => 3])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("idMissing")); // folder ID 3 does not exist
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id,   42, ['folder' => 8])->thenReturn(true);
        // set up a mock for a bad feed
        Phake::when(Data::$db)->subscriptionAdd(Data::$user->id, "http://example.net/news.atom")->thenThrow(new \JKingWeb\Arsse\Feed\Exception("http://example.net/news.atom", new \PicoFeed\Client\InvalidUrlException()));
        // add the subscriptions
        $exp = new Response(200, $out[0]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[0]), 'application/json')));
        $exp = new Response(200, $out[1]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[1]), 'application/json')));
        // try to add them a second time
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[0]), 'application/json')));
        $exp = new Response(409);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[1]), 'application/json')));
        // try to add a bad feed
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "/feeds", json_encode($in[2]), 'application/json')));
        // make sure the correct date format is actually requested
        Phake::verify(Data::$db, Phake::atLeast(1))->dateFormatDefault("unix");
    }

    function testRemoveASubscription() {
        Phake::when(Data::$db)->subscriptionRemove(Data::$user->id, 1)->thenReturn(true)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/feeds/1")));
        // fail on the second invocation because it no longer exists
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("DELETE", "/feeds/1")));
        Phake::verify(Data::$db, Phake::times(2))->subscriptionRemove(Data::$user->id, 1);
    }

    function testMoveASubscription() {
        $in = [
            ['folderId' =>    0],
            ['folderId' =>   42],
            ['folderId' => 2112],
            ['folderId' =>   42],
        ];
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, ['folder' => 42])->thenReturn(true);
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, ['folder' => null])->thenReturn(true);
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, ['folder' => 2112])->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("idMissing")); // folder does not exist
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 42, $this->anything())->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing")); // subscription does not exist
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[0]), 'application/json')));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[1]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/move", json_encode($in[2]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/42/move", json_encode($in[3]), 'application/json')));
    }

    function testRenameASubscription() {
        $in = [
            ['feedTitle' => null],
            ['feedTitle' => "Ook"],
            ['feedTitle' => "   "],
            ['feedTitle' => ""],
            ['feedTitle' => false],
            ['feedTitle' => "Feed does not exist"],
        ];
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, $this->identicalTo(['title' =>  null]))->thenReturn(true);
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, $this->identicalTo(['title' => "Ook"]))->thenReturn(true);
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, $this->identicalTo(['title' => "   "]))->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("whitespace"));
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, $this->identicalTo(['title' =>    ""]))->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 1, $this->identicalTo(['title' => false]))->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("missing"));
        Phake::when(Data::$db)->subscriptionPropertiesSet(Data::$user->id, 42, $this->anything())->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[0]), 'application/json')));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[1]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[2]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/1/rename", json_encode($in[3]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("PUT", "/feeds/42/rename", json_encode($in[4]), 'application/json')));
    }

    function testListStaleFeeds() {
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
        Phake::when(Data::$db)->feedListStale->thenReturn(array_column($out,"id"));
        $exp = new Response(200, ['feeds' => $out]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/all")));
        // retrieving the list when not an admin fails
        Phake::when(Data::$user)->rightsGet->thenReturn(0);
        $exp = new Response(403);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/all")));
    }

    function testUpdateAFeed() {
        $in = [
            ['feedId' =>    42], // valid
            ['feedId' =>  2112], // feed does not exist
            ['feedId' => "ook"], // invalid ID
            ['feed'   =>    42], // invalid input
        ];
        Phake::when(Data::$db)->feedUpdate(  42)->thenReturn(true);
        Phake::when(Data::$db)->feedUpdate(2112)->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = new Response(204);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[0]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[1]), 'application/json')));
        $exp = new Response(404);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[2]), 'application/json')));
        $exp = new Response(422);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[3]), 'application/json')));
        // retrieving the list when not an admin fails
        Phake::when(Data::$user)->rightsGet->thenReturn(0);
        $exp = new Response(403);
        $this->assertEquals($exp, $this->h->dispatch(new Request("GET", "/feeds/update", json_encode($in[0]), 'application/json')));
    }
}