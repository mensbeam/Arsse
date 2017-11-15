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
use JKingWeb\Arsse\REST\TinyTinyRSS\API;
use Phake;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\API<extended>
 *  @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Exception */
class TestTinyTinyAPI extends Test\AbstractTest {
    protected $h;
    protected $folders = [
        ['id' => 5, 'parent' => 3,    'children' => 0, 'feeds' => 1, 'name' => "Local"],
        ['id' => 6, 'parent' => 3,    'children' => 0, 'feeds' => 2, 'name' => "National"],
        ['id' => 4, 'parent' => null, 'children' => 0, 'feeds' => 0, 'name' => "Photography"],
        ['id' => 3, 'parent' => null, 'children' => 2, 'feeds' => 0, 'name' => "Politics"],
        ['id' => 2, 'parent' => 1,    'children' => 0, 'feeds' => 1, 'name' => "Rocketry"],
        ['id' => 1, 'parent' => null, 'children' => 1, 'feeds' => 1, 'name' => "Science"],
    ];
    protected $topFolders = [
        ['id' => 4, 'parent' => null, 'children' => 0, 'feeds' => 0, 'name' => "Photography"],
        ['id' => 3, 'parent' => null, 'children' => 2, 'feeds' => 0, 'name' => "Politics"],
        ['id' => 1, 'parent' => null, 'children' => 1, 'feeds' => 1, 'name' => "Science"],
    ];
    protected $subscriptions = [
        ['id' => 3, 'folder' => 1,    'top_folder' => 1,    'unread' => 2,  'updated' => "2016-05-23 06:40:02", 'err_msg' => 'argh', 'title' => 'Ars Technica',   'url' => " http://example.com/3", 'favicon' => 'http://example.com/3.png'],
        ['id' => 4, 'folder' => 6,    'top_folder' => 3,    'unread' => 6,  'updated' => "2017-10-09 15:58:34", 'err_msg' => '',     'title' => 'CBC News',       'url' => " http://example.com/4", 'favicon' => 'http://example.com/4.png'],
        ['id' => 6, 'folder' => null, 'top_folder' => null, 'unread' => 0,  'updated' => "2010-02-12 20:08:47", 'err_msg' => '',     'title' => 'Eurogamer',      'url' => " http://example.com/6", 'favicon' => 'http://example.com/6.png'],
        ['id' => 1, 'folder' => 2,    'top_folder' => 1,    'unread' => 5,  'updated' => "2017-09-15 22:54:16", 'err_msg' => '',     'title' => 'NASA JPL',       'url' => " http://example.com/1", 'favicon' => null],
        ['id' => 5, 'folder' => 6,    'top_folder' => 3,    'unread' => 12, 'updated' => "2017-07-07 17:07:17", 'err_msg' => '',     'title' => 'Ottawa Citizen', 'url' => " http://example.com/5", 'favicon' => ''],
        ['id' => 2, 'folder' => 5,    'top_folder' => 3,    'unread' => 10, 'updated' => "2011-11-11 11:11:11", 'err_msg' => 'oops', 'title' => 'Toronto Star',   'url' => " http://example.com/2", 'favicon' => 'http://example.com/2.png'],
    ];
    protected $labels = [
        ['id' => 3, 'articles' => 100, 'read' => 94, 'unread' => 6, 'name' => "Fascinating"],
        ['id' => 5, 'articles' => 0,   'read' => 0,  'unread' => 0, 'name' => "Interesting"],
        ['id' => 1, 'articles' => 2,   'read' => 2,  'unread' => 0, 'name' => "Logical"],
    ];
    protected $usedLabels = [
        ['id' => 3, 'articles' => 100, 'read' => 94, 'unread' => 6, 'name' => "Fascinating"],
        ['id' => 1, 'articles' => 2,   'read' => 2,  'unread' => 0, 'name' => "Logical"],
    ];
    protected $starred = ['total' => 10, 'unread' => 4, 'read' => 6];
    protected $articles = [
        [
            'id' => 101,
            'url' => 'http://example.com/1',
            'title' => 'Article title 1',
            'subscription_title' => "Feed 11",
            'author' => '',
            'content' => '<p>Article content 1</p>',
            'guid' => '',
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
            'note' => "",
        ],
        [
            'id' => 102,
            'url' => 'http://example.com/2',
            'title' => 'Article title 2',
            'subscription_title' => "Feed 11",
            'author' => 'J. King',
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
            'note' => "Note 2",
        ],
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

    protected function assertResponse(Response $exp, Response $act, string $text = null) {
        if ($exp->payload['status']) {
            // if the expectation is an error response, do a straight object comparison
            $this->assertEquals($exp, $act, $text);
        } else {
            // otherwise just compare their content
            foreach ($act->payload['content'] as $record) {
                $this->assertContains($record, $exp->payload['content'], $text);
            }
            $this->assertCount(sizeof($exp->payload['content']), $act->payload['content'], $text);
        }
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
            [Arsse::$user->id, -1, ['parent' => 1]],
            [Arsse::$user->id, 42, ['parent' => -1]],
            [Arsse::$user->id, 42, ['parent' => 0]],
            [Arsse::$user->id, 0, ['parent' => -1]],
            [Arsse::$user->id, 0, ['parent' => 0]],
        ];
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[3])->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[4])->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[5])->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[6])->thenThrow(new ExceptionInput("constraintViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[7])->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->folderPropertiesSet(...$db[8])->thenThrow(new ExceptionInput("typeViolation"));
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
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[7]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[8]))));
        Phake::verify(Arsse::$db, Phake::times(5))->folderPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything());
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
        Phake::when(Arsse::$db)->subscriptionRemove(Arsse::$user->id, $this->anything())->thenThrow(new ExceptionInput("typeViolation"));
        Phake::when(Arsse::$db)->subscriptionRemove(Arsse::$user->id, 2112)->thenThrow(new ExceptionInput("subjectMissing"));
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
        Phake::verify(Arsse::$db, Phake::times(5))->subscriptionRemove(Arsse::$user->id, $this->anything());
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

    public function testRetrieveTheServerConfiguration() {
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
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 2);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 3);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        // attempt to add the two labels again
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 2);
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 3);
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
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1042],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => 1],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => 0],
            ['op' => "removeLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -10],
        ];
        Phake::when(Arsse::$db)->labelRemove(Arsse::$user->id, $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->labelRemove(Arsse::$user->id, 18)->thenReturn(true)->thenThrow(new ExceptionInput("subjectMissing"));
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
        Phake::verify(Arsse::$db, Phake::times(2))->labelRemove(Arsse::$user->id, 18);
        Phake::verify(Arsse::$db)->labelRemove(Arsse::$user->id, 1088);
    }

    public function testRenameALabel() {
        $in = [
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1042, 'caption' => "Ook"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112, 'caption' => "Eek"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1042, 'caption' => "Eek"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1042, 'caption' => ""],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1042, 'caption' => " "],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1042],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -1, 'caption' => "Ook"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx", 'caption' => "Ook"],
            ['op' => "renameLabel", 'sid' => "PriestsOfSyrinx"],
        ];
        $db = [
            [Arsse::$user->id, 18, ['name' => "Ook"]],
            [Arsse::$user->id, 1088, ['name' => "Eek"]],
            [Arsse::$user->id, 18, ['name' => "Eek"]],
            [Arsse::$user->id, 18, ['name' => ""]],
            [Arsse::$user->id, 18, ['name' => " "]],
            [Arsse::$user->id, 18, ['name' => ""]],
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

    public function testRetrieveCategoryLists() {
        $in = [
            ['op' => "getCategories", 'sid' => "PriestsOfSyrinx", 'include_empty' => true],
            ['op' => "getCategories", 'sid' => "PriestsOfSyrinx"],
            ['op' => "getCategories", 'sid' => "PriestsOfSyrinx", 'unread_only' => true],
            ['op' => "getCategories", 'sid' => "PriestsOfSyrinx", 'enable_nested' => true, 'include_empty' => true],
            ['op' => "getCategories", 'sid' => "PriestsOfSyrinx", 'enable_nested' => true],
            ['op' => "getCategories", 'sid' => "PriestsOfSyrinx", 'enable_nested' => true, 'unread_only' => true],
        ];
        Phake::when(Arsse::$db)->folderList($this->anything(), null, true)->thenReturn(new Result($this->folders));
        Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result($this->topFolders));
        Phake::when(Arsse::$db)->subscriptionList($this->anything())->thenReturn(new Result($this->subscriptions));
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->labels));
        Phake::when(Arsse::$db)->articleCount($this->anything(), $this->anything())->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->starred);
        $exp = [
            [
                ['id' => 5,  'title' => "Local",         'unread' => 10, 'order_id' => 1],
                ['id' => 6,  'title' => "National",      'unread' => 18, 'order_id' => 2],
                ['id' => 4,  'title' => "Photography",   'unread' => 0,  'order_id' => 3],
                ['id' => 3,  'title' => "Politics",      'unread' => 0,  'order_id' => 4],
                ['id' => 2,  'title' => "Rocketry",      'unread' => 5,  'order_id' => 5],
                ['id' => 1,  'title' => "Science",       'unread' => 2,  'order_id' => 6],
                ['id' => 0,  'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1, 'title' => "Special",       'unread' => 11],
                ['id' => -2, 'title' => "Labels",        'unread' => 6],
            ],
            [
                ['id' => 5,  'title' => "Local",         'unread' => 10, 'order_id' => 1],
                ['id' => 6,  'title' => "National",      'unread' => 18, 'order_id' => 2],
                ['id' => 3,  'title' => "Politics",      'unread' => 0,  'order_id' => 4],
                ['id' => 2,  'title' => "Rocketry",      'unread' => 5,  'order_id' => 5],
                ['id' => 1,  'title' => "Science",       'unread' => 2,  'order_id' => 6],
                ['id' => 0,  'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1, 'title' => "Special",       'unread' => 11],
                ['id' => -2, 'title' => "Labels",        'unread' => 6],
            ],
            [
                ['id' => 5,  'title' => "Local",         'unread' => 10, 'order_id' => 1],
                ['id' => 6,  'title' => "National",      'unread' => 18, 'order_id' => 2],
                ['id' => 2,  'title' => "Rocketry",      'unread' => 5,  'order_id' => 5],
                ['id' => 1,  'title' => "Science",       'unread' => 2,  'order_id' => 6],
                ['id' => -1, 'title' => "Special",       'unread' => 11],
                ['id' => -2, 'title' => "Labels",        'unread' => 6],
            ],
            [
                ['id' => 4,  'title' => "Photography",   'unread' => 0,  'order_id' => 1],
                ['id' => 3,  'title' => "Politics",      'unread' => 28, 'order_id' => 2],
                ['id' => 1,  'title' => "Science",       'unread' => 7,  'order_id' => 3],
                ['id' => 0,  'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1, 'title' => "Special",       'unread' => 11],
                ['id' => -2, 'title' => "Labels",        'unread' => 6],
            ],
            [
                ['id' => 3,  'title' => "Politics",      'unread' => 28, 'order_id' => 2],
                ['id' => 1,  'title' => "Science",       'unread' => 7,  'order_id' => 3],
                ['id' => 0,  'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1, 'title' => "Special",       'unread' => 11],
                ['id' => -2, 'title' => "Labels",        'unread' => 6],
            ],
            [
                ['id' => 3,  'title' => "Politics",      'unread' => 28, 'order_id' => 2],
                ['id' => 1,  'title' => "Science",       'unread' => 7,  'order_id' => 3],
                ['id' => -1, 'title' => "Special",       'unread' => 11],
                ['id' => -2, 'title' => "Labels",        'unread' => 6],
            ],
        ];
        for ($a = 0; $a < sizeof($in); $a++) {
            $this->assertEquals($this->respGood($exp[$a]), $this->h->dispatch(new Request("POST", "", json_encode($in[$a]))), "Test $a failed");
        }
    }

    public function testRetrieveCounterList() {
        $in = ['op' => "getCounters", 'sid' => "PriestsOfSyrinx"];
        Phake::when(Arsse::$db)->folderList($this->anything())->thenReturn(new Result($this->folders));
        Phake::when(Arsse::$db)->subscriptionList($this->anything())->thenReturn(new Result($this->subscriptions));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->usedLabels));
        Phake::when(Arsse::$db)->articleCount($this->anything(), $this->anything())->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->starred);
        $exp = [
            ['id' => "global-unread", 'counter' => 35],
            ['id' => "subscribed-feeds", 'counter' => 6],
            ['id' => 0, 'counter' => 0, 'auxcounter' => 0],
            ['id' => -1, 'counter' => 4, 'auxcounter' => 10],
            ['id' => -2, 'counter' => 0, 'auxcounter' => 0],
            ['id' => -3, 'counter' => 7, 'auxcounter' => 0],
            ['id' => -4, 'counter' => 35, 'auxcounter' => 0],
            ['id' => -1027, 'counter' => 6, 'auxcounter' => 100],
            ['id' => -1025, 'counter' => 0, 'auxcounter' => 2],
            ['id' => 3, 'has_img' => 1, 'counter' => 2,  'updated' => "2016-05-23T06:40:02"],
            ['id' => 4, 'has_img' => 1, 'counter' => 6,  'updated' => "2017-10-09T15:58:34"],
            ['id' => 1, 'has_img' => 0, 'counter' => 5,  'updated' => "2017-09-15T22:54:16"],
            ['id' => 5, 'has_img' => 0, 'counter' => 12, 'updated' => "2017-07-07T17:07:17"],
            ['id' => 2, 'has_img' => 1, 'counter' => 10, 'updated' => "2011-11-11T11:11:11"],
            ['id' => 5, 'kind' => "cat", 'counter' => 10],
            ['id' => 6, 'kind' => "cat", 'counter' => 18],
            ['id' => 3, 'kind' => "cat", 'counter' => 28],
            ['id' => 2, 'kind' => "cat", 'counter' => 5],
            ['id' => 1, 'kind' => "cat", 'counter' => 7],
            ['id' => -2, 'kind' => "cat", 'counter' => 6],
        ];
        $this->assertResponse($this->respGood($exp), $this->h->dispatch(new Request("POST", "", json_encode($in))));
    }

    public function testRetrieveTheLabelList() {
        $in = [
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx"],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 1],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 2],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 3],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 4],
        ];
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->labels));
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 1)->thenReturn([1,3]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2)->thenReturn([3]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 3)->thenReturn([]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 4)->thenThrow(new ExceptionInput("idMissing"));
        $exp = [
            [
                ['id' => -1027, 'caption' => "Fascinating", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1029, 'caption' => "Interesting", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1025, 'caption' => "Logical",     'fg_color' => "", 'bg_color' => "", 'checked' => false],
            ],
            [
                ['id' => -1027, 'caption' => "Fascinating", 'fg_color' => "", 'bg_color' => "", 'checked' => true],
                ['id' => -1029, 'caption' => "Interesting", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1025, 'caption' => "Logical",     'fg_color' => "", 'bg_color' => "", 'checked' => true],
            ],
            [
                ['id' => -1027, 'caption' => "Fascinating", 'fg_color' => "", 'bg_color' => "", 'checked' => true],
                ['id' => -1029, 'caption' => "Interesting", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1025, 'caption' => "Logical",     'fg_color' => "", 'bg_color' => "", 'checked' => false],
            ],
            [
                ['id' => -1027, 'caption' => "Fascinating", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1029, 'caption' => "Interesting", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1025, 'caption' => "Logical",     'fg_color' => "", 'bg_color' => "", 'checked' => false],
            ],
            [
                ['id' => -1027, 'caption' => "Fascinating", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1029, 'caption' => "Interesting", 'fg_color' => "", 'bg_color' => "", 'checked' => false],
                ['id' => -1025, 'caption' => "Logical",     'fg_color' => "", 'bg_color' => "", 'checked' => false],
            ],
        ];
        for ($a = 0; $a < sizeof($in); $a++) {
            $this->assertResponse($this->respGood($exp[$a]), $this->h->dispatch(new Request("POST", "", json_encode($in[$a]))), "Test $a failed");
        }
    }

    public function testAssignArticlesToALabel() {
        $list = [
            range(1,100),
            range(1,50),
            range(51,100),
        ];
        $in = [
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112, 'article_ids' => implode(",", $list[0])],
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112, 'article_ids' => implode(",", $list[0]), 'assign' => true],
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -2112],
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => -42],
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => 42],
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx", 'label_id' => 0],
            ['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx"],
        ];
        Phake::when(Arsse::$db)->labelArticlesSet(Arsse::$user->id, $this->anything(), (new Context)->articles([]), $this->anything())->thenThrow(new ExceptionInput("tooShort")); // data model function requires one valid integer for multiples
        Phake::when(Arsse::$db)->labelArticlesSet(Arsse::$user->id, $this->anything(), (new Context)->articles($list[0]), $this->anything())->thenThrow(new ExceptionInput("tooLong")); // data model function limited to 50 items for multiples
        Phake::when(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[1]), true)->thenReturn(42);
        Phake::when(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[2]), true)->thenReturn(47);
        Phake::when(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[1]), false)->thenReturn(5);
        Phake::when(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[2]), false)->thenReturn(2);
        $exp = $this->respGood(['status' => "OK", 'updated' => 89]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[1]), true);
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[2]), true);
        $exp = $this->respGood(['status' => "OK", 'updated' => 7]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[1]), false);
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[2]), false);
        $exp = $this->respGood(['status' => "OK", 'updated' => 89]);
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
    }

    public function testRetrieveFeedTree() {
        $in = [
            ['op' => "getFeedTree", 'sid' => "PriestsOfSyrinx", 'include_empty' => true],
            ['op' => "getFeedTree", 'sid' => "PriestsOfSyrinx"],
        ];
        Phake::when(Arsse::$db)->folderList($this->anything(), null, true)->thenReturn(new Result($this->folders));
        Phake::when(Arsse::$db)->subscriptionList($this->anything())->thenReturn(new Result($this->subscriptions));
        Phake::when(Arsse::$db)->labelList($this->anything(), true)->thenReturn(new Result($this->labels));
        Phake::when(Arsse::$db)->articleCount($this->anything(), $this->anything())->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->starred);
        // the expectations are packed tightly since they're very verbose; one can use var_export() (or convert to JSON) to pretty-print them
        $exp = ['categories'=>['identifier'=>'id','label'=>'name','items'=>[['id'=>'CAT:-1','items'=>[['id'=>'FEED:-4','name'=>'All articles','unread'=>35,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/folder.png','bare_id'=>-4,'auxcounter'=>0,],['id'=>'FEED:-3','name'=>'Fresh articles','unread'=>7,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/fresh.png','bare_id'=>-3,'auxcounter'=>0,],['id'=>'FEED:-1','name'=>'Starred articles','unread'=>4,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/star.png','bare_id'=>-1,'auxcounter'=>0,],['id'=>'FEED:-2','name'=>'Published articles','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/feed.png','bare_id'=>-2,'auxcounter'=>0,],['id'=>'FEED:0','name'=>'Archived articles','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/archive.png','bare_id'=>0,'auxcounter'=>0,],['id'=>'FEED:-6','name'=>'Recently read','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/time.png','bare_id'=>-6,'auxcounter'=>0,],],'name'=>'Special','type'=>'category','unread'=>0,'bare_id'=>-1,],['id'=>'CAT:-2','items'=>[['id'=>'FEED:-1027','name'=>'Fascinating','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/label.png','bare_id'=>-1027,'auxcounter'=>0,'fg_color'=>'','bg_color'=>'',],['id'=>'FEED:-1029','name'=>'Interesting','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/label.png','bare_id'=>-1029,'auxcounter'=>0,'fg_color'=>'','bg_color'=>'',],['id'=>'FEED:-1025','name'=>'Logical','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/label.png','bare_id'=>-1025,'auxcounter'=>0,'fg_color'=>'','bg_color'=>'',],],'name'=>'Labels','type'=>'category','unread'=>6,'bare_id'=>-2,],['id'=>'CAT:4','bare_id'=>4,'auxcounter'=>0,'name'=>'Photography','items'=>[],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(0 feeds)',],['id'=>'CAT:3','bare_id'=>3,'auxcounter'=>0,'name'=>'Politics','items'=>[['id'=>'CAT:5','bare_id'=>5,'name'=>'Local','items'=>[['id'=>'FEED:2','bare_id'=>2,'auxcounter'=>0,'name'=>'Toronto Star','checkbox'=>false,'unread'=>0,'error'=>'oops','icon'=>'feed-icons/2.ico','param'=>'2011-11-11T11:11:11',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'auxcounter'=>0,'parent_id'=>3,'param'=>'(1 feed)',],['id'=>'CAT:6','bare_id'=>6,'name'=>'National','items'=>[['id'=>'FEED:4','bare_id'=>4,'auxcounter'=>0,'name'=>'CBC News','checkbox'=>false,'unread'=>0,'error'=>'','icon'=>'feed-icons/4.ico','param'=>'2017-10-09T15:58:34',],['id'=>'FEED:5','bare_id'=>5,'auxcounter'=>0,'name'=>'Ottawa Citizen','checkbox'=>false,'unread'=>0,'error'=>'','icon'=>false,'param'=>'2017-07-07T17:07:17',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'auxcounter'=>0,'parent_id'=>3,'param'=>'(2 feeds)',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(3 feeds)',],['id'=>'CAT:1','bare_id'=>1,'auxcounter'=>0,'name'=>'Science','items'=>[['id'=>'CAT:2','bare_id'=>2,'name'=>'Rocketry','items'=>[['id'=>'FEED:1','bare_id'=>1,'auxcounter'=>0,'name'=>'NASA JPL','checkbox'=>false,'unread'=>0,'error'=>'','icon'=>false,'param'=>'2017-09-15T22:54:16',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'auxcounter'=>0,'parent_id'=>1,'param'=>'(1 feed)',],['id'=>'FEED:3','bare_id'=>3,'auxcounter'=>0,'name'=>'Ars Technica','checkbox'=>false,'unread'=>0,'error'=>'argh','icon'=>'feed-icons/3.ico','param'=>'2016-05-23T06:40:02',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(2 feeds)',],['id'=>'CAT:0','bare_id'=>0,'auxcounter'=>0,'name'=>'Uncategorized','items'=>[['id'=>'FEED:6','bare_id'=>6,'auxcounter'=>0,'name'=>'Eurogamer','checkbox'=>false,'error'=>'','icon'=>'feed-icons/6.ico','param'=>'2010-02-12T20:08:47','unread'=>0,],],'type'=>'category','checkbox'=>false,'unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(1 feed)',],],],];
        $this->assertEquals($this->respGood($exp), $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $exp = ['categories'=>['identifier'=>'id','label'=>'name','items'=>[['id'=>'CAT:-1','items'=>[['id'=>'FEED:-4','name'=>'All articles','unread'=>35,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/folder.png','bare_id'=>-4,'auxcounter'=>0,],['id'=>'FEED:-3','name'=>'Fresh articles','unread'=>7,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/fresh.png','bare_id'=>-3,'auxcounter'=>0,],['id'=>'FEED:-1','name'=>'Starred articles','unread'=>4,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/star.png','bare_id'=>-1,'auxcounter'=>0,],['id'=>'FEED:-2','name'=>'Published articles','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/feed.png','bare_id'=>-2,'auxcounter'=>0,],['id'=>'FEED:0','name'=>'Archived articles','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/archive.png','bare_id'=>0,'auxcounter'=>0,],['id'=>'FEED:-6','name'=>'Recently read','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/time.png','bare_id'=>-6,'auxcounter'=>0,],],'name'=>'Special','type'=>'category','unread'=>0,'bare_id'=>-1,],['id'=>'CAT:-2','items'=>[['id'=>'FEED:-1027','name'=>'Fascinating','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/label.png','bare_id'=>-1027,'auxcounter'=>0,'fg_color'=>'','bg_color'=>'',],['id'=>'FEED:-1029','name'=>'Interesting','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/label.png','bare_id'=>-1029,'auxcounter'=>0,'fg_color'=>'','bg_color'=>'',],['id'=>'FEED:-1025','name'=>'Logical','unread'=>0,'type'=>'feed','error'=>'','updated'=>'','icon'=>'images/label.png','bare_id'=>-1025,'auxcounter'=>0,'fg_color'=>'','bg_color'=>'',],],'name'=>'Labels','type'=>'category','unread'=>6,'bare_id'=>-2,],['id'=>'CAT:3','bare_id'=>3,'auxcounter'=>0,'name'=>'Politics','items'=>[['id'=>'CAT:5','bare_id'=>5,'name'=>'Local','items'=>[['id'=>'FEED:2','bare_id'=>2,'auxcounter'=>0,'name'=>'Toronto Star','checkbox'=>false,'unread'=>0,'error'=>'oops','icon'=>'feed-icons/2.ico','param'=>'2011-11-11T11:11:11',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'auxcounter'=>0,'parent_id'=>3,'param'=>'(1 feed)',],['id'=>'CAT:6','bare_id'=>6,'name'=>'National','items'=>[['id'=>'FEED:4','bare_id'=>4,'auxcounter'=>0,'name'=>'CBC News','checkbox'=>false,'unread'=>0,'error'=>'','icon'=>'feed-icons/4.ico','param'=>'2017-10-09T15:58:34',],['id'=>'FEED:5','bare_id'=>5,'auxcounter'=>0,'name'=>'Ottawa Citizen','checkbox'=>false,'unread'=>0,'error'=>'','icon'=>false,'param'=>'2017-07-07T17:07:17',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'auxcounter'=>0,'parent_id'=>3,'param'=>'(2 feeds)',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(3 feeds)',],['id'=>'CAT:1','bare_id'=>1,'auxcounter'=>0,'name'=>'Science','items'=>[['id'=>'CAT:2','bare_id'=>2,'name'=>'Rocketry','items'=>[['id'=>'FEED:1','bare_id'=>1,'auxcounter'=>0,'name'=>'NASA JPL','checkbox'=>false,'unread'=>0,'error'=>'','icon'=>false,'param'=>'2017-09-15T22:54:16',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'auxcounter'=>0,'parent_id'=>1,'param'=>'(1 feed)',],['id'=>'FEED:3','bare_id'=>3,'auxcounter'=>0,'name'=>'Ars Technica','checkbox'=>false,'unread'=>0,'error'=>'argh','icon'=>'feed-icons/3.ico','param'=>'2016-05-23T06:40:02',],],'checkbox'=>false,'type'=>'category','unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(2 feeds)',],['id'=>'CAT:0','bare_id'=>0,'auxcounter'=>0,'name'=>'Uncategorized','items'=>[['id'=>'FEED:6','bare_id'=>6,'auxcounter'=>0,'name'=>'Eurogamer','checkbox'=>false,'error'=>'','icon'=>'feed-icons/6.ico','param'=>'2010-02-12T20:08:47','unread'=>0,],],'type'=>'category','checkbox'=>false,'unread'=>0,'child_unread'=>0,'parent_id'=>null,'param'=>'(1 feed)',],],],];
        $this->assertEquals($this->respGood($exp), $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
    }

    public function testMarkFeedsAsRead() {
        $in1 = [
            // no-ops
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx"],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 0],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1, 'is_cat' => true],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3, 'is_cat' => true],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'is_cat' => true],
        ];
        $in2 = [
            // simple contexts
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'is_cat' => true],
        ];
        $in3 = [
            // complex context
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 0, 'is_cat' => true],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2, 'is_cat' => true],
        ];
        $in4 = [
            // this one has a tricky time-based context
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3],
        ];
        Phake::when(Arsse::$db)->articleMark->thenThrow(new ExceptionInput("typeViolation"));
        $exp = $this->respGood(['status' => "OK"]);
        // verify the above are in fact no-ops
        for ($a = 0; $a < sizeof($in1); $a++) {
            $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in1[$a]))), "Test $a failed");
        }
        Phake::verify(Arsse::$db, Phake::times(0))->articleMark;
        // verify the simple contexts
        for ($a = 0; $a < sizeof($in2); $a++) {
            $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in2[$a]))), "Test $a failed");
        }
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], new Context);
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->starred(true));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->label(1088));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->subscription(2112));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->folder(42));
        // reset the database mock
        $this->setUp();
        Phake::when(Arsse::$db)->articleMark->thenReturn(42);
        Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result($this->subscriptions));
        Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, false)->thenReturn(new Result($this->filterSubs(null)));
        Phake::when(Arsse::$db)->labelList->thenReturn(new Result($this->labels));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->usedLabels));
        // verify the complex contexts
        for ($a = 0; $a < sizeof($in3); $a++) {
            $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in3[$a]))), "Test $a failed");
        }
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->subscription(6));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->label(3));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->label(1));
        Phake::verify(Arsse::$db, Phake::times(3))->articleMark;
        // verify the time-based mock
        $t = Date::sub("PT24H");
        for ($a = 0; $a < sizeof($in4); $a++) {
            $this->assertResponse($exp, $this->h->dispatch(new Request("POST", "", json_encode($in4[$a]))), "Test $a failed");
        }
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->modifiedSince($t));
    }

    public function testRetrieveFeedList() {
        $in1 = [
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx"],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -1],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -1, 'unread_only' => true],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -2],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -2, 'unread_only' => true],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -3],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -3, 'unread_only' => true],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -4],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => -4, 'unread_only' => true],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 6],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 6, 'limit' => 1],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 6, 'limit' => 1, 'offset' => 1],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 1],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 1, 'include_nested' => true],
        ];
        $in2 = [
            // these should all return an empty list
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 0, 'unread_only' => true],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 2112],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 2112, 'include_nested' => true],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 6, 'limit' => -42],
            ['op' => "getFeeds", 'sid' => "PriestsOfSyrinx", 'cat_id' => 6, 'offset' => 2],
        ];
        // statistical mocks
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->starred);
        Phake::when(Arsse::$db)->articleCount->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true))->thenReturn(35);
        // label mocks
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->labels));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->usedLabels));
        // subscription and folder list and unread count mocks
        Phake::when(Arsse::$db)->folderList->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionList->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->folderList($this->anything())->thenReturn(new Result($this->folders));
        Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, true)->thenReturn(new Result($this->subscriptions));
        Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, false)->thenReturn(new Result($this->filterSubs(null)));
        Phake::when(Arsse::$db)->folderList($this->anything(), null)->thenReturn(new Result($this->folders));
        Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result($this->filterFolders(null)));
        foreach ($this->folders as $f) {
            Phake::when(Arsse::$db)->folderList($this->anything(), $f['id'], false)->thenReturn(new Result($this->filterFolders($f['id'])));
            Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true)->folder($f['id']))->thenReturn($this->reduceFolders($f['id']));
            Phake::when(Arsse::$db)->subscriptionList($this->anything(), $f['id'], false)->thenReturn(new Result($this->filterSubs($f['id'])));
        }
        $exp = [
            [
                ['id' => 6, 'title' => 'Eurogamer', 'unread' => 0, 'cat_id' => 0, 'feed_url' => " http://example.com/6", 'has_icon' => true, 'last_updated' => 1266005327, 'order_id' => 1],
            ],
            [
                ['id' => -1, 'title' => "Starred articles",   'unread' => 4,  'cat_id' => -1],
                ['id' => -2, 'title' => "Published articles", 'unread' => 0,  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => 7,  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => 35, 'cat_id' => -1],
                ['id' => -6, 'title' => "Recently read",      'unread' => 0,  'cat_id' => -1],
                ['id' =>  0, 'title' => "Archived articles",  'unread' => 0,  'cat_id' => -1],
            ],
            [
                ['id' => -1, 'title' => "Starred articles",   'unread' => 4,  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => 7,  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => 35, 'cat_id' => -1],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => 6,  'cat_id' => -2],
                ['id' => -1025, 'title' => "Logical",     'unread' => 0,  'cat_id' => -2],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => 6,  'cat_id' => -2],
            ],
            [
                ['id' => 3, 'title' => 'Ars Technica',   'unread' => 2,  'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true,  'last_updated' => 1463985602, 'order_id' => 1],
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 2],
                ['id' => 6, 'title' => 'Eurogamer',      'unread' => 0,  'cat_id' => 0, 'feed_url' => " http://example.com/6", 'has_icon' => true,  'last_updated' => 1266005327, 'order_id' => 3],
                ['id' => 1, 'title' => 'NASA JPL',       'unread' => 5,  'cat_id' => 2, 'feed_url' => " http://example.com/1", 'has_icon' => false, 'last_updated' => 1505516056, 'order_id' => 4],
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 5],
                ['id' => 2, 'title' => 'Toronto Star',   'unread' => 10, 'cat_id' => 5, 'feed_url' => " http://example.com/2", 'has_icon' => true,  'last_updated' => 1321009871, 'order_id' => 6],
            ],
            [
                ['id' => 3, 'title' => 'Ars Technica',   'unread' => 2,  'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true,  'last_updated' => 1463985602, 'order_id' => 1],
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 2],
                ['id' => 1, 'title' => 'NASA JPL',       'unread' => 5,  'cat_id' => 2, 'feed_url' => " http://example.com/1", 'has_icon' => false, 'last_updated' => 1505516056, 'order_id' => 4],
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 5],
                ['id' => 2, 'title' => 'Toronto Star',   'unread' => 10, 'cat_id' => 5, 'feed_url' => " http://example.com/2", 'has_icon' => true,  'last_updated' => 1321009871, 'order_id' => 6],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => 6,  'cat_id' => -2],
                ['id' => -1025, 'title' => "Logical",     'unread' => 0,  'cat_id' => -2],
                ['id' => -1, 'title' => "Starred articles",   'unread' => 4,  'cat_id' => -1],
                ['id' => -2, 'title' => "Published articles", 'unread' => 0,  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => 7,  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => 35, 'cat_id' => -1],
                ['id' => -6, 'title' => "Recently read",      'unread' => 0,  'cat_id' => -1],
                ['id' =>  0, 'title' => "Archived articles",  'unread' => 0,  'cat_id' => -1],
                ['id' => 3, 'title' => 'Ars Technica',   'unread' => 2,  'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true,  'last_updated' => 1463985602, 'order_id' => 1],
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 2],
                ['id' => 6, 'title' => 'Eurogamer',      'unread' => 0,  'cat_id' => 0, 'feed_url' => " http://example.com/6", 'has_icon' => true,  'last_updated' => 1266005327, 'order_id' => 3],
                ['id' => 1, 'title' => 'NASA JPL',       'unread' => 5,  'cat_id' => 2, 'feed_url' => " http://example.com/1", 'has_icon' => false, 'last_updated' => 1505516056, 'order_id' => 4],
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 5],
                ['id' => 2, 'title' => 'Toronto Star',   'unread' => 10, 'cat_id' => 5, 'feed_url' => " http://example.com/2", 'has_icon' => true,  'last_updated' => 1321009871, 'order_id' => 6],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => 6,  'cat_id' => -2],
                ['id' => -1, 'title' => "Starred articles",   'unread' => 4,  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => 7,  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => 35, 'cat_id' => -1],
                ['id' => 3, 'title' => 'Ars Technica',   'unread' => 2,  'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true,  'last_updated' => 1463985602, 'order_id' => 1],
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 2],
                ['id' => 1, 'title' => 'NASA JPL',       'unread' => 5,  'cat_id' => 2, 'feed_url' => " http://example.com/1", 'has_icon' => false, 'last_updated' => 1505516056, 'order_id' => 4],
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 5],
                ['id' => 2, 'title' => 'Toronto Star',   'unread' => 10, 'cat_id' => 5, 'feed_url' => " http://example.com/2", 'has_icon' => true,  'last_updated' => 1321009871, 'order_id' => 6],
            ],
            [
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 1],
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 2],
            ],
            [
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 1],
            ],
            [
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 2],
            ],
            [
                ['id' => 3, 'title' => 'Ars Technica', 'unread' => 2, 'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true, 'last_updated' => 1463985602, 'order_id' => 1],
            ],
            [
                ['id' => 2, 'title' => "Rocketry", 'unread' => 5, 'is_cat' => true, 'order_id' => 1],
                ['id' => 3, 'title' => 'Ars Technica', 'unread' => 2, 'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true, 'last_updated' => 1463985602, 'order_id' => 1],
            ],
        ];
        for ($a = 0; $a < sizeof($in1); $a++) {
            $this->assertResponse($this->respGood($exp[$a]), $this->h->dispatch(new Request("POST", "", json_encode($in1[$a]))), "Test $a failed");
        }
        for ($a = 0; $a < sizeof($in2); $a++) {
            $this->assertResponse($this->respGood([]), $this->h->dispatch(new Request("POST", "", json_encode($in2[$a]))), "Test $a failed");
        }
    }

    protected function filterFolders(int $id = null): array {
        return array_filter($this->folders, function($value) use ($id) {return $value['parent']==$id;});
    }

    protected function filterSubs(int $folder = null): array {
        return array_filter($this->subscriptions, function($value) use ($folder) {return $value['folder']==$folder;});
    }

    protected function reduceFolders(int $id = null) : int {
        $out = 0;
        foreach ($this->filterFolders($id) as $f) {
            $out += $this->reduceFolders($f['id']);
        }
        $out += array_reduce(array_filter($this->subscriptions, function($value) use ($id) {return $value['folder']==$id;}), function($sum, $value) {return $sum + $value['unread'];}, 0);
        return $out;
    }

    public function testChangeArticles() {
        $in = [
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx"],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1"],

            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 0],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 0],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 1],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 2],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 3], // invalid mode
            
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 1], // Published feed' no-op
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 0],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 1],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 2],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 3], // invalid mode

            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 2],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 0],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 1],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 2],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 3], // invalid mode

            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 3],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 0],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 1],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 2],
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 3], // invalid mode
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 3, 'data' => "eh"],

            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "42, 2112, -1", 'field' => 4], // invalid field
            ['op' => "updateArticle", 'sid' => "PriestsOfSyrinx", 'article_ids' => "0, -1", 'field' => 3], // no valid IDs
        ];
        Phake::when(Arsse::$db)->articleMark->thenReturn(1);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' => false], (new Context)->articles([42, 2112]))->thenReturn(2);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' =>  true], (new Context)->articles([42, 2112]))->thenReturn(4);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' => false], (new Context)->articles([42, 2112])->starred(true))->thenReturn(8);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' =>  true], (new Context)->articles([42, 2112])->starred(false))->thenReturn(16);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    =>  true], (new Context)->articles([42, 2112]))->thenReturn(32); // false is read for TT-RSS
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    => false], (new Context)->articles([42, 2112]))->thenReturn(64);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    =>  true], (new Context)->articles([42, 2112])->unread(true))->thenReturn(128);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    => false], (new Context)->articles([42, 2112])->unread(false))->thenReturn(256);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['note'    =>    ""], (new Context)->articles([42, 2112]))->thenReturn(512);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['note'    =>  "eh"], (new Context)->articles([42, 2112]))->thenReturn(1024);
        $out = [
            $this->respErr("INCORRECT_USAGE"),
            $this->respGood(['status' => "OK", 'updated' => 2]),

            $this->respGood(['status' => "OK", 'updated' => 2]),
            $this->respGood(['status' => "OK", 'updated' => 2]),
            $this->respGood(['status' => "OK", 'updated' => 4]),
            $this->respGood(['status' => "OK", 'updated' => 24]),
            $this->respErr("INCORRECT_USAGE"),

            $this->respGood(['status' => "OK", 'updated' => 0]),
            $this->respGood(['status' => "OK", 'updated' => 0]),
            $this->respGood(['status' => "OK", 'updated' => 0]),
            $this->respGood(['status' => "OK", 'updated' => 0]),
            $this->respErr("INCORRECT_USAGE"),

            $this->respGood(['status' => "OK", 'updated' => 32]),
            $this->respGood(['status' => "OK", 'updated' => 32]),
            $this->respGood(['status' => "OK", 'updated' => 64]),
            $this->respGood(['status' => "OK", 'updated' => 384]),
            $this->respErr("INCORRECT_USAGE"),

            $this->respGood(['status' => "OK", 'updated' => 512]),
            $this->respGood(['status' => "OK", 'updated' => 512]),
            $this->respGood(['status' => "OK", 'updated' => 512]),
            $this->respGood(['status' => "OK", 'updated' => 512]),
            $this->respGood(['status' => "OK", 'updated' => 512]),
            $this->respGood(['status' => "OK", 'updated' => 1024]),

            $this->respErr("INCORRECT_USAGE"),
            $this->respErr("INCORRECT_USAGE"),
        ];
        for ($a = 0; $a < sizeof($in); $a++) {
            $this->assertEquals($out[$a], $this->h->dispatch(new Request("POST", "", json_encode($in[$a]))), "Test $a failed");
        }
    }

    public function testListArticles() {
        $in = [
            // error conditions
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx"],
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx", 'article_id' => 0],
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx", 'article_id' => -1],
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx", 'article_id' => "0,-1"],
            // acceptable input
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx", 'article_id' => "101,102"],
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx", 'article_id' => "101"],
            ['op' => "getArticle", 'sid' => "PriestsOfSyrinx", 'article_id' => "102"],
        ];
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->labels));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->usedLabels));
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 101)->thenReturn([]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 102)->thenReturn([1,3]);
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([101, 102]))->thenReturn(new Result($this->articles));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([101]))->thenReturn(new Result([$this->articles[0]]));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([102]))->thenReturn(new Result([$this->articles[1]]));
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[0]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[1]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[2]))));
        $this->assertEquals($exp, $this->h->dispatch(new Request("POST", "", json_encode($in[3]))));
        $exp = [
            [
                'id' => 101,
                'guid' => null,
                'title' => 'Article title 1',
                'link' => 'http://example.com/1',
                'labels' => [],
                'unread' => true,
                'marked' => false,
                'published' => false,
                'comments' => "",
                'author' => '',
                'updated' => strtotime('2000-01-01 00:00:01'),
                'feed_id' => 8,
                'feed_title' => "Feed 11",
                'attachments' => [],
                'score' => 0,
                'note' => null,
                'lang' => "",
                'content' => '<p>Article content 1</p>',
            ],
            [
                'id' => 102,
                'guid' => "SHA256:5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7",
                'title' => 'Article title 2',
                'link' => 'http://example.com/2',
                'labels' => [
                    [-1025, "Logical", "", ""],
                    [-1027, "Fascinating", "", ""],
                ],
                'unread' => false,
                'marked' => false,
                'published' => false,
                'comments' => "",
                'author' => "J. King",
                'updated' => strtotime('2000-01-02 00:00:02'),
                'feed_id' => 8,
                'feed_title' => "Feed 11",
                'attachments' => [
                    [
                        'content_url'  => "http://example.com/text",
                        'content_type' => "text/plain",
                        'title'        => "",
                        'duration'     => "",
                        'width'        => "",
                        'height'       => "",
                        'post_id'      => 102,
                    ],
                ],
                'score' => 0,
                'note' => "Note 2",
                'lang' => "",
                'content' => '<p>Article content 2</p>',
            ],
        ];
        $this->assertEquals($this->respGood($exp), $this->h->dispatch(new Request("POST", "", json_encode($in[4]))));
        $this->assertEquals($this->respGood([$exp[0]]), $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
        $this->assertEquals($this->respGood([$exp[1]]), $this->h->dispatch(new Request("POST", "", json_encode($in[6]))));
        // test the special case when labels are not used
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result([]));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result([]));
        $this->assertEquals($this->respGood([$exp[0]]), $this->h->dispatch(new Request("POST", "", json_encode($in[5]))));
    }
}
