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

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\API<extended> */
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

    protected function respGood(array $content, $seq = 0): Response {
        return new Response(200, [
            'seq' => $seq,
            'status' => 0,
            'content' => $content,
        ]);
    }

    protected function respErr(string $msg, array $content = [], $seq = 0): Response {
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
            'arsse_version' => \JKingWeb\Arsse\VERSION,
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
}