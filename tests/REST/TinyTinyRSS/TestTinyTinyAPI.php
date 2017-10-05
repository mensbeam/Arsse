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

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\API<extended> 
 *  @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Exception */
class TestTinyTinyAPI extends Test\AbstractTest {
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
        ]
    ];

    protected function respGood($content = null, $seq = 0): Response {
        return new Response(200, [
            'seq' => $seq,
            'status' => 0,
            'content' => $content,
        ]);
    }

    protected function respErr(string $msg, $content = [], $seq = 0): Response {
        $err = ['error' => $msg];
        return new Response(200, [
            'seq' => $seq,
            'status' => 1,
            'content' => array_merge($err, $content, $err),
        ]);
    }

    public function setUp() {
        $this->clearData();
        Arsse::$conf = new Conf();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        Phake::when(Arsse::$user)->auth->thenReturn(true);
        Phake::when(Arsse::$user)->rightsGet->thenReturn(100);
        Arsse::$user->id = "john.doe@example.com";
        // create a mock database interface
        Arsse::$db = Phake::mock(Database::class);
        Phake::when(Arsse::$db)->begin->thenReturn(Phake::mock(Transaction::class));
        Phake::when(Arsse::$db)->sessionResume->thenThrow(new \JKingWeb\Arsse\User\ExceptionSession("invalid"));
        Phake::when(Arsse::$db)->sessionResume("PriestsOfSyrinx")->thenReturn([
            'id' => "PriestsOfSyrinx",
            'created' => "2000-01-01 00:00:00",
            'expires' => "2112-12-21 21:12:00",
            'user'    => Arsse::$user->id,
        ]);
        $this->h = new REST\TinyTinyRSS\API();
    }

    public function tearDown() {
        $this->clearData();
    }

    public function testLogIn() {
        Phake::when(Arsse::$user)->auth(Arsse::$user->id, "superman")->thenReturn(false);
        Phake::when(Arsse::$db)->sessionCreate->thenReturn("PriestsOfSyrinx")->thenReturn("SolarFederation");
        $data = [
            'op'       => "login",
            'user'     => Arsse::$user->id,
            'password' => "secret",
        ];
        $exp = $this->respGood(['session_id' => "PriestsOfSyrinx", 'api_level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
        $exp = $this->respGood(['session_id' => "SolarFederation", 'api_level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
        // test a failed log-in
        $data['password'] = "superman";
        $exp = $this->respErr("LOGIN_ERROR");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
        // logging in should never try to resume a session
        Phake::verify(Arsse::$db, Phake::times(0))->sessionResume($this->anything());
    }

    public function testLogOut() {
        Phake::when(Arsse::$db)->sessionDestroy->thenReturn(true);
        $data = [
            'op'       => "logout",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood(['status' => "OK"]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
        Phake::verify(Arsse::$db)->sessionDestroy(Arsse::$user->id, "PriestsOfSyrinx");
    }

    public function testValidateASession() {
        $data = [
            'op'       => "isLoggedIn",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood(['status' => true]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
        $data['sid'] = "SolarFederation";
        $exp = $this->respErr("NOT_LOGGED_IN");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
    }

    public function testRetrieveServerVersion() {
        $data = [
            'op'       => "getVersion",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood([
            'version' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::VERSION,
            'arsse_version' => Arsse::VERSION,
        ]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
    }

    public function testRetrieveProtocolLevel() {
        $data = [
            'op'       => "getApiLevel",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood(['level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($data))));
    }

    public function testAddACategory() {
        $in = [
            ['op' => "addCategory", 'sid' => "PriestsOfSyrinx", 'caption' => "Software"],
            ['op' => "addCategory", 'sid' => "PriestsOfSyrinx", 'caption' => "Hardware", 'parent_id' => 1],
            ['op' => "addCategory", 'sid' => "PriestsOfSyrinx", 'caption' => "Hardware", 'parent_id' => 2112],
            ['op' => "addCategory", 'sid' => "PriestsOfSyrinx"],
            ['op' => "addCategory", 'sid' => "PriestsOfSyrinx", 'caption' => ""],
            ['op' => "addCategory", 'sid' => "PriestsOfSyrinx", 'caption' => "   "],
        ];
        $db = [
            ['name' => "Software", 'parent' => null],
            ['name' => "Hardware", 'parent' => 1],
            ['name' => "Hardware", 'parent' => 2112],
        ];
        $out = [
            ['id' => 2, 'name' => "Software", 'parent' => null],
            ['id' => 3, 'name' => "Hardware", 'parent' => 1],
            ['id' => 1, 'name' => "Politics", 'parent' => null],
        ];
        // set of various mocks for testing
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $db[0])->thenReturn(2)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $db[1])->thenReturn(3)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->folderList(Arsse::$user->id, null, false)->thenReturn(new Result([$out[0], $out[2]]));
        Phake::when(Arsse::$db)->folderList(Arsse::$user->id, 1, false)->thenReturn(new Result([$out[1]]));
        // set up mocks that produce errors
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $db[2])->thenThrow(new ExceptionInput("idMissing")); // parent folder does not exist
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, [])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => "",    'parent' => null])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => "   ", 'parent' => null])->thenThrow(new ExceptionInput("whitespace"));
        // correctly add two folders
        $exp = $this->respGood(2);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = $this->respGood(3);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // attempt to add the two folders again
        $exp = $this->respGood(2);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = $this->respGood(3);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        Phake::verify(Arsse::$db)->folderList(Arsse::$user->id, null, false);
        Phake::verify(Arsse::$db)->folderList(Arsse::$user->id, 1, false);
        // add a folder to a missing parent (silently fails)
        $exp = $this->respGood(false);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        // add some invalid folders
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
    }

    public function testRemoveACategory() {
        $in = [
            ['op' => "removeCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42],
            ['op' => "removeCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 2112],
            ['op' => "removeCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => -1],
        ];
        Phake::when(Arsse::$db)->folderRemove(Arsse::$user->id, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->folderRemove(Arsse::$user->id, 42)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        // succefully delete a folder
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // try deleting it again (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // delete a folder which does not exist (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // delete an invalid folder (causes an error)
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        Phake::verify(Arsse::$db, Phake::times(3))->folderRemove(Arsse::$user->id, $this->anything());
    }

    public function testMoveACategory() {
        $in = [
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'parent_id' => 1],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 2112, 'parent_id' => 2],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'parent_id' => 0],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'parent_id' => 47],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => -1, 'parent_id' => 1],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'parent_id' => -1],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx", 'parent_id' => -1],
            ['op' => "moveCategory", 'sid' => "PriestsOfSyrinx"],
        ];
        $db = [
            [Arsse::$user->id, 42, ['parent' => 1]],
            [Arsse::$user->id, 2112, ['parent' => 2]],
            [Arsse::$user->id, 42, ['parent' => 0]],
            [Arsse::$user->id, 42, ['parent' => 47]],
        ];
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[3])->thenThrow(new ExceptionInput("idMissing"));
        // succefully move a folder
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // move a folder which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // move a folder causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[7]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[8]))));
        Phake::verify(Arsse::$db, Phake::times(4))->folderPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything());
    }

    public function testRenameACategory() {
        $in = [
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'caption' => "Ook"],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 2112, 'caption' => "Eek"],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'caption' => "Eek"],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'caption' => ""],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42, 'caption' => " "],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => -1, 'caption' => "Ook"],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'category_id' => 42],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx", 'caption' => "Ook"],
            ['op' => "renameCategory", 'sid' => "PriestsOfSyrinx"],
        ];
        $db = [
            [Arsse::$user->id, 42, ['name' => "Ook"]],
            [Arsse::$user->id, 2112, ['name' => "Eek"]],
            [Arsse::$user->id, 42, ['name' => "Eek"]],
        ];
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        // succefully rename a folder
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // rename a folder which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // rename a folder causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[7]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[8]))));
        Phake::verify(Arsse::$db, Phake::times(3))->folderPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything());
    }

    public function testAddASubscription() {
        $in = [
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/0"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/1", 'category_id' => 42],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/2", 'category_id' => 2112],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/3"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://localhost:8000/Feed/Discovery/Valid"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://localhost:8000/Feed/Discovery/Invalid"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/6"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/7"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/8", 'category_id' => 47],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/9", 'category_id' => 1],
            // these don't even query the database as the input is syntactically invalid
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx"],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/", 'login' => []],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/", 'login' => "", 'password' => []],
            ['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx", 'feed_url' => "http://example.com/", 'category_id' => -1],
        ];
        $db = [
            [Arsse::$user->id, "http://example.com/0", "", ""],
            [Arsse::$user->id, "http://example.com/1", "", ""],
            [Arsse::$user->id, "http://example.com/2", "", ""],
            [Arsse::$user->id, "http://example.com/3", "", ""],
            [Arsse::$user->id, "http://localhost:8000/Feed/Discovery/Valid", "", ""],
            [Arsse::$user->id, "http://localhost:8000/Feed/Discovery/Invalid", "", ""],
            [Arsse::$user->id, "http://example.com/6", "", ""],
            [Arsse::$user->id, "http://example.com/7", "", ""],
            [Arsse::$user->id, "http://example.com/8", "", ""],
            [Arsse::$user->id, "http://example.com/9", "", ""],
        ];
        $out = [
            ['code' => 1, 'feed_id' => 2],
            ['code' => 5, 'message' => (new \JKingWeb\Arsse\Feed\Exception("http://example.com/1", new \PicoFeed\Client\UnauthorizedException()))->getMessage()],
            ['code' => 1, 'feed_id' => 0],
            ['code' => 0, 'feed_id' => 3],
            ['code' => 0, 'feed_id' => 1],
            ['code' => 3, 'message' => (new \JKingWeb\Arsse\Feed\Exception("http://localhost:8000/Feed/Discovery/Invalid", new \PicoFeed\Reader\SubscriptionNotFoundException()))->getMessage()],
            ['code' => 2, 'message' => (new \JKingWeb\Arsse\Feed\Exception("http://example.com/6", new \PicoFeed\Client\InvalidUrlException()))->getMessage()],
            ['code' => 6, 'message' => (new \JKingWeb\Arsse\Feed\Exception("http://example.com/7", new \PicoFeed\Parser\MalformedXmlException()))->getMessage()],
            ['code' => 1, 'feed_id' => 4],
            ['code' => 0, 'feed_id' => 4],
        ];
        $list = [
            ['id' => 1, 'url' => "http://localhost:8000/Feed/Discovery/Feed"],
            ['id' => 2, 'url' => "http://example.com/0"],
            ['id' => 3, 'url' => "http://example.com/3"],
            ['id' => 4, 'url' => "http://example.com/9"],
        ];
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[0])->thenReturn(2);
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[1])->thenThrow(new \JKingWeb\Arsse\Feed\Exception("http://example.com/1", new \PicoFeed\Client\UnauthorizedException()));
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[2])->thenReturn(2);
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[3])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[4])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[5])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[6])->thenThrow(new \JKingWeb\Arsse\Feed\Exception("http://example.com/6", new \PicoFeed\Client\InvalidUrlException()));
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[7])->thenThrow(new \JKingWeb\Arsse\Feed\Exception("http://example.com/7", new \PicoFeed\Parser\MalformedXmlException()));
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[8])->thenReturn(4);
        Phake::when(Arsse::$db)->subscriptionAdd(...$db[9])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 42)->thenReturn(['id' => 42]);
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 47)->thenReturn(['id' => 47]);
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 2112)->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything())->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 4, $this->anything())->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->subscriptionList(Arsse::$user->id)->thenReturn(new Result($list));
        for ($a = 0; $a < (sizeof($in) - 4); $a++) {
            $exp = $this->respGood($out[$a]);
            $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[$a]))), "Failed test $a");
        }
        $exp = $this->respErr("INCORRECT_USAGE");
        for ($a = (sizeof($in) - 4); $a < sizeof($in); $a++) {
            $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[$a]))), "Failed test $a");
        }
        Phake::verify(Arsse::$db, Phake::times(0))->subscriptionPropertiesSet(Arsse::$user->id, 4, ['folder' => 1]);
    }

    public function testRemoveASubscription() {
        $in = [
            ['op' => "unsubscribeFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42],
            ['op' => "unsubscribeFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112],
            ['op' => "unsubscribeFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1],
            ['op' => "unsubscribeFeed", 'sid' => "PriestsOfSyrinx"],
        ];
        Phake::when(Arsse::$db)->subscriptionRemove(Arsse::$user->id, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionRemove(Arsse::$user->id, 42)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        // succefully delete a folder
        $exp = $this->respGood(['status' => "OK"]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // try deleting it again (this should noisily fail, as should everything else)
        $exp = $this->respErr("FEED_NOT_FOUND");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        Phake::verify(Arsse::$db, Phake::times(3))->subscriptionRemove(Arsse::$user->id, $this->anything());
    }

    public function testMoveASubscription() {
        $in = [
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'category_id' => 1],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112, 'category_id' => 2],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'category_id' => 0],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'category_id' => 47],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1, 'category_id' => 1],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'category_id' => -1],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx", 'category_id' => -1],
            ['op' => "moveFeed", 'sid' => "PriestsOfSyrinx"],
        ];
        $db = [
            [Arsse::$user->id, 42, ['folder' => 1]],
            [Arsse::$user->id, 2112, ['folder' => 2]],
            [Arsse::$user->id, 42, ['folder' => 0]],
            [Arsse::$user->id, 42, ['folder' => 47]],
        ];
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[3])->thenThrow(new ExceptionInput("constraintViolation"));
        // succefully move a subscription
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // move a subscription which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // move a subscription causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[7]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[8]))));
        Phake::verify(Arsse::$db, Phake::times(4))->subscriptionPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything());
    }

    public function testRenameASubscription() {
        $in = [
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'caption' => "Ook"],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112, 'caption' => "Eek"],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'caption' => "Eek"],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'caption' => ""],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'caption' => " "],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1, 'caption' => "Ook"],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx", 'caption' => "Ook"],
            ['op' => "renameFeed", 'sid' => "PriestsOfSyrinx"],
        ];
        $db = [
            [Arsse::$user->id, 42, ['name' => "Ook"]],
            [Arsse::$user->id, 2112, ['name' => "Eek"]],
            [Arsse::$user->id, 42, ['name' => "Eek"]],
        ];
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        // succefully rename a subscription
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // rename a subscription which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // rename a subscription causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[7]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[8]))));
        Phake::verify(Arsse::$db, Phake::times(3))->subscriptionPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything());
    }

    public function testRetrieveTheGlobalUnreadCount() {
        $in = ['op' => "getUnread", 'sid' => "PriestsOfSyrinx"];
        Phake::when(Arsse::$db)->subscriptionList(Arsse::$user->id)->thenReturn(new Result([
            ['id' => 1, 'unread' => 2112],
            ['id' => 2, 'unread' => 42],
            ['id' => 3, 'unread' => 47],
        ]));
        $exp = $this->respGood(['unread' => 2112 + 42 + 47]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in))));
    }

    public function testRetrieveTheServerConfiguration () {
        $in = ['op' => "getConfig", 'sid' => "PriestsOfSyrinx"];
        $interval = Service::interval();
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        Phake::when(Arsse::$db)->subscriptionCount(Arsse::$user->id)->thenReturn(12)->thenReturn(2);
        $exp = [
            ['icons_dir' => "feed-icons", 'icons_url' => "feed-icons", 'daemon_is_running' => true, 'num_feeds' => 12],
            ['icons_dir' => "feed-icons", 'icons_url' => "feed-icons", 'daemon_is_running' => false, 'num_feeds' => 2],
        ];
        $this->assertEquals($this->respGood($exp[0]), $this->h->dispatch(new Request("POST", "", json_encode($in))));
        $this->assertEquals($this->respGood($exp[1]), $this->h->dispatch(new Request("POST", "", json_encode($in))));
    }

    public function testUpdateAFeed() {
        $in = [
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 1],
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2],
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1],
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx"],
        ];
        Phake::when(Arsse::$db)->feedUpdate(11)->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 1)->thenReturn(['id' => 1, 'feed' => 11]);
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 2)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = $this->respGood(['status' => "OK"]);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        Phake::verify(Arsse::$db)->feedUpdate(11);
        $exp = $this->respErr("FEED_NOT_FOUND");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
    }

    public function testAddALabel() {
        $in = [
            ['op' => "addLabel", 'sid' => "PriestsOfSyrinx", 'caption' => "Software"],
            ['op' => "addLabel", 'sid' => "PriestsOfSyrinx", 'caption' => "Hardware",],
            ['op' => "addLabel", 'sid' => "PriestsOfSyrinx"],
            ['op' => "addLabel", 'sid' => "PriestsOfSyrinx", 'caption' => ""],
            ['op' => "addLabel", 'sid' => "PriestsOfSyrinx", 'caption' => "   "],
        ];
        $db = [
            ['name' => "Software"],
            ['name' => "Hardware"],
        ];
        $out = [
            ['id' => 2, 'name' => "Software"],
            ['id' => 3, 'name' => "Hardware"],
            ['id' => 1, 'name' => "Politics"],
        ];
        // set of various mocks for testing
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, $db[0])->thenReturn(2)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, $db[1])->thenReturn(3)->thenThrow(new ExceptionInput("constraintViolation")); // error on the second call
        Phake::when(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Software", true)->thenReturn($out[0]);
        Phake::when(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Hardware", true)->thenReturn($out[1]);
        // set up mocks that produce errors
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, [])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, ['name' => ""])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, ['name' => "   "])->thenThrow(new ExceptionInput("whitespace"));
        // correctly add two labels
        $exp = $this->respGood(-12);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = $this->respGood(-13);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // attempt to add the two labels again
        $exp = $this->respGood(-12);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = $this->respGood(-13);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        Phake::verify(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Software", true);
        Phake::verify(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Hardware", true);
        // add some invalid labels
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
    }

    public function testRemoveALabel() {
        $in = [
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => 1],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => 0],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -10],
        ];
        Phake::when(Arsse::$db)->labelRemove(Arsse::$user->id, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->labelRemove(Arsse::$user->id, 32)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
        // succefully delete a label
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // try deleting it again (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // delete a label which does not exist (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // delete some invalid labels (causes an error)
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        Phake::verify(Arsse::$db, Phake::times(2))->labelRemove(Arsse::$user->id, 32);
        Phake::verify(Arsse::$db)->labelRemove(Arsse::$user->id, 2102);
    }

    public function testRenameALabel() {
        $in = [
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42, 'caption' => "Ook"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112, 'caption' => "Eek"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42, 'caption' => "Eek"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42, 'caption' => ""],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42, 'caption' => " "],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1, 'caption' => "Ook"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'caption' => "Ook"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx"],
        ];
        $db = [
            [Arsse::$user->id, 32, ['name' => "Ook"]],
            [Arsse::$user->id, 2102, ['name' => "Eek"]],
            [Arsse::$user->id, 32, ['name' => "Eek"]],
            [Arsse::$user->id, 32, ['name' => ""]],
            [Arsse::$user->id, 32, ['name' => " "]],
            [Arsse::$user->id, 32, ['name' => ""]],
        ];
        Phake::when(Arsse::$db)->labelPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->labelPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->labelPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->labelPropertiesSet(...$db[3])->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->labelPropertiesSet(...$db[4])->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->labelPropertiesSet(...$db[5])->thenThrow(new ExceptionInput("typeViolation"));
        // succefully rename a label
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        // rename a label which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // rename a label causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[7]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[8]))));
        Phake::verify(Arsse::$db, Phake::times(6))->labelPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything());
    }
}
