<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\REST\Request;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\TinyTinyRSS\API;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse as Response;
use Zend\Diactoros\Response\EmptyResponse;
use Phake;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\API<extended>
 *  @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Exception */
class TestAPI extends \JKingWeb\Arsse\Test\AbstractTest {
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
    // text from https://corrigeur.fr/lorem-ipsum-traduction-origine.php
    protected $richContent = <<<LONG_STRING
<section>
    <p>
        <b>Pour</b> vous faire mieux
        connaitre d’ou\u{300} vient
        l’erreur de ceux qui
        bla\u{302}ment la
        volupte\u{301}, et qui louent
        en quelque sorte la douleur,
        je vais entrer dans une
        explication plus
        e\u{301}tendue, et vous faire
        voir tout ce qui a
        e\u{301}te\u{301} dit
        la\u{300}-dessus par
        l’inventeur de la
        ve\u{301}rite\u{301}, et, pour
        ainsi dire, par l’architecte
        de la vie heureuse.
    </p>
</section>
LONG_STRING;

    protected function v($value) {
        return $value;
    }

    protected function req($data, string $method = "POST", string $target = "", string $strData = null, string $user = null): ResponseInterface {
        $url = "/tt-rss/api".$target;
        $server = [
            'REQUEST_METHOD'    => $method,
            'REQUEST_URI'       => $url,
            'HTTP_CONTENT_TYPE' => "application/x-www-form-urlencoded",
        ];
        $req = new ServerRequest($server, [], $url, $method, "php://memory");
        $body = $req->getBody();
        if (!is_null($strData)) {
            $body->write($strData);
        } else {
            $body->write(json_encode($data));
        }
        $req = $req->withBody($body)->withRequestTarget($target);
        if (isset($user)) {
            if (strlen($user)) {
                $req = $req->withAttribute("authenticated", true)->withAttribute("authenticatedUser", $user);
            } else {
                $req = $req->withAttribute("authenticationFailed", true);
            }
        }
        return $this->h->dispatch($req);
    }

    protected function reqAuth($data, $user) {
        return $this->req($data, "POST", "", null, $user);
    }

    protected function respGood($content = null, $seq = 0): Response {
        return new Response([
            'seq' => $seq,
            'status' => 0,
            'content' => $content,
        ]);
    }

    protected function respErr(string $msg, $content = [], $seq = 0): Response {
        $err = ['error' => $msg];
        return new Response([
            'seq' => $seq,
            'status' => 1,
            'content' => array_merge($err, $content, $err),
        ]);
    }

    public function setUp() {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        Phake::when(Arsse::$user)->auth->thenReturn(true);
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
        $this->h = new API();
    }

    public function tearDown() {
        self::clearData();
    }

    public function testHandleInvalidPaths() {
        $exp = $this->respErr("MALFORMED_INPUT", [], null);
        $this->assertMessage($exp, $this->req(null, "POST", "", ""));
        $this->assertMessage($exp, $this->req(null, "POST", "/", ""));
        $this->assertMessage($exp, $this->req(null, "POST", "/index.php", ""));
        $exp = new EmptyResponse(404);
        $this->assertMessage($exp, $this->req(null, "POST", "/bad/path", ""));
    }

    public function testHandleOptionsRequest() {
        $exp = new EmptyResponse(204, [
            'Allow'  => "POST",
            'Accept' => "application/json, text/json",
        ]);
        $this->assertMessage($exp, $this->req(null, "OPTIONS", "", ""));
    }

    public function testHandleInvalidData() {
        $exp = $this->respErr("MALFORMED_INPUT", [], null);
        $this->assertMessage($exp, $this->req(null, "POST", "", "This is not valid JSON data"));
        $this->assertMessage($exp, $this->req(null, "POST", "", "")); // lack of data is also an error
    }

    /** @dataProvider provideLoginRequests */
    public function testLogIn(array $conf, $httpUser, array $data, $sessions) {
        Arsse::$user->id = null;
        self::setConf($conf);
        Phake::when(Arsse::$user)->auth->thenReturn(false);
        Phake::when(Arsse::$user)->auth("john.doe@example.com", "secret")->thenReturn(true);
        Phake::when(Arsse::$user)->auth("jane.doe@example.com", "superman")->thenReturn(true);
        Phake::when(Arsse::$db)->sessionCreate("john.doe@example.com")->thenReturn("PriestsOfSyrinx")->thenReturn("SolarFederation");
        Phake::when(Arsse::$db)->sessionCreate("jane.doe@example.com")->thenReturn("ClockworkAngels")->thenReturn("SevenCitiesOfGold");
        if ($sessions instanceof EmptyResponse) {
            $exp1 = $sessions;
            $exp2 = $sessions;
        } elseif ($sessions) {
            $exp1 = $this->respGood(['session_id' => $sessions[0], 'api_level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
            $exp2 = $this->respGood(['session_id' => $sessions[1], 'api_level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        } else {
            $exp1 = $this->respErr("LOGIN_ERROR");
            $exp2 = $this->respErr("LOGIN_ERROR");
        }
        $data['op'] = "login";
        $this->assertMessage($exp1, $this->reqAuth($data, $httpUser));
        // base64 passwords are also accepted
        if (isset($data['password'])) {
            $data['password'] = base64_encode($data['password']);
        }
        $this->assertMessage($exp2, $this->reqAuth($data, $httpUser));
        // logging in should never try to resume a session
        Phake::verify(Arsse::$db, Phake::times(0))->sessionResume($this->anything());
    }

    public function provideLoginRequests() {
        return $this->generateLoginRequests("login");
    }

    /** @dataProvider provideResumeRequests */
    public function testValidateASession(array $conf, $httpUser, string $data, $result) {
        Arsse::$user->id = null;
        self::setConf($conf);
        Phake::when(Arsse::$db)->sessionResume("PriestsOfSyrinx")->thenReturn([
            'id' => "PriestsOfSyrinx",
            'created' => "2000-01-01 00:00:00",
            'expires' => "2112-12-21 21:12:00",
            'user'    => "john.doe@example.com",
        ]);
        Phake::when(Arsse::$db)->sessionResume("ClockworkAngels")->thenReturn([
            'id' => "ClockworkAngels",
            'created' => "2000-01-01 00:00:00",
            'expires' => "2112-12-21 21:12:00",
            'user'    => "jane.doe@example.com",
        ]);
        $data = [
            'op'       => "isLoggedIn",
            'sid'      => $data,
        ];
        if ($result instanceof EmptyResponse) {
            $exp1 = $result;
            $exp2 = null;
        } elseif ($result) {
            $exp1 = $this->respGood(['status' => true]);
            $exp2 = $result;
        } else {
            $exp1 = $this->respErr("NOT_LOGGED_IN");
            $exp2 = ($httpUser) ? $httpUser : null;
        }
        $this->assertMessage($exp1, $this->reqAuth($data, $httpUser));
        $this->assertSame($exp2, Arsse::$user->id);
    }

    public function provideResumeRequests() {
        return $this->generateLoginRequests("isLoggedIn");
    }

    public function generateLoginRequests(string $type) {
        $john = "john.doe@example.com";
        $johnGood = [
            'user' => $john,
            'password' => "secret",
        ];
        $johnBad = [
            'user' => $john,
            'password' => "superman",
        ];
        $johnSess = ["PriestsOfSyrinx", "SolarFederation"];
        $jane = "jane.doe@example.com";
        $janeGood = [
            'user' => $jane,
            'password' => "superman",
        ];
        $janeBad = [
            'user' => $jane,
            'password' => "secret",
        ];
        $janeSess = ["ClockworkAngels", "SevenCitiesOfGold"];
        $missingU = [
            'password' => "secret",
        ];
        $missingP = [
            'user' => $john,
        ];
        $sidJohn = "PriestsOfSyrinx";
        $sidJane = "ClockworkAngels";
        $sidBad = "TheWatchmaker";
        $defaults = [
            'userPreAuth' => false,
            'userHTTPAuthRequired' => false,
            'userSessionEnforced' => true,
        ];
        $preAuth = [
            'userPreAuth' => true,
            'userHTTPAuthRequired' => false, // implied true by pre-auth
            'userSessionEnforced' => true,
        ];
        $httpReq = [
            'userPreAuth' => false,
            'userHTTPAuthRequired' => true,
            'userSessionEnforced' => true,
        ];
        $noSess = [
            'userPreAuth' => false,
            'userHTTPAuthRequired' => false,
            'userSessionEnforced' => false,
        ];
        $fullHttp = [
            'userPreAuth' => false,
            'userHTTPAuthRequired' => true,
            'userSessionEnforced' => false,
        ];
        $http401 = new EmptyResponse(401);
        if ($type=="login") {
            return [
                // conf,    user,  data,      result
                [$defaults, null,  $johnGood, $johnSess],
                [$defaults, null,  $johnBad,  false],
                [$defaults, null,  $janeGood, $janeSess],
                [$defaults, null,  $janeBad,  false],
                [$defaults, null,  $missingU, false],
                [$defaults, null,  $missingP, false],
                [$defaults, $john, $johnGood, $johnSess],
                [$defaults, $john, $johnBad,  false],
                [$defaults, $john, $janeGood, $janeSess],
                [$defaults, $john, $janeBad,  false],
                [$defaults, $john, $missingU, false],
                [$defaults, $john, $missingP, false],
                [$defaults, $jane, $johnGood, $johnSess],
                [$defaults, $jane, $johnBad,  false],
                [$defaults, $jane, $janeGood, $janeSess],
                [$defaults, $jane, $janeBad,  false],
                [$defaults, $jane, $missingU, false],
                [$defaults, $jane, $missingP, false],
                [$defaults, "",    $johnGood, $http401],
                [$defaults, "",    $johnBad,  $http401],
                [$defaults, "",    $janeGood, $http401],
                [$defaults, "",    $janeBad,  $http401],
                [$defaults, "",    $missingU, $http401],
                [$defaults, "",    $missingP, $http401],
                [$preAuth,  null,  $johnGood, $http401],
                [$preAuth,  null,  $johnBad,  $http401],
                [$preAuth,  null,  $janeGood, $http401],
                [$preAuth,  null,  $janeBad,  $http401],
                [$preAuth,  null,  $missingU, $http401],
                [$preAuth,  null,  $missingP, $http401],
                [$preAuth,  $john, $johnGood, $johnSess],
                [$preAuth,  $john, $johnBad,  $johnSess],
                [$preAuth,  $john, $janeGood, false],
                [$preAuth,  $john, $janeBad,  false],
                [$preAuth,  $john, $missingU, false],
                [$preAuth,  $john, $missingP, $johnSess],
                [$preAuth,  $jane, $johnGood, false],
                [$preAuth,  $jane, $johnBad,  false],
                [$preAuth,  $jane, $janeGood, $janeSess],
                [$preAuth,  $jane, $janeBad,  $janeSess],
                [$preAuth,  $jane, $missingU, false],
                [$preAuth,  $jane, $missingP, false],
                [$preAuth,  "",    $johnGood, $http401],
                [$preAuth,  "",    $johnBad,  $http401],
                [$preAuth,  "",    $janeGood, $http401],
                [$preAuth,  "",    $janeBad,  $http401],
                [$preAuth,  "",    $missingU, $http401],
                [$preAuth,  "",    $missingP, $http401],
                [$httpReq,  null,  $johnGood, $http401],
                [$httpReq,  null,  $johnBad,  $http401],
                [$httpReq,  null,  $janeGood, $http401],
                [$httpReq,  null,  $janeBad,  $http401],
                [$httpReq,  null,  $missingU, $http401],
                [$httpReq,  null,  $missingP, $http401],
                [$httpReq,  $john, $johnGood, $johnSess],
                [$httpReq,  $john, $johnBad,  false],
                [$httpReq,  $john, $janeGood, $janeSess],
                [$httpReq,  $john, $janeBad,  false],
                [$httpReq,  $john, $missingU, false],
                [$httpReq,  $john, $missingP, false],
                [$httpReq,  $jane, $johnGood, $johnSess],
                [$httpReq,  $jane, $johnBad,  false],
                [$httpReq,  $jane, $janeGood, $janeSess],
                [$httpReq,  $jane, $janeBad,  false],
                [$httpReq,  $jane, $missingU, false],
                [$httpReq,  $jane, $missingP, false],
                [$httpReq,  "",    $johnGood, $http401],
                [$httpReq,  "",    $johnBad,  $http401],
                [$httpReq,  "",    $janeGood, $http401],
                [$httpReq,  "",    $janeBad,  $http401],
                [$httpReq,  "",    $missingU, $http401],
                [$httpReq,  "",    $missingP, $http401],
                [$noSess,   null,  $johnGood, $johnSess],
                [$noSess,   null,  $johnBad,  false],
                [$noSess,   null,  $janeGood, $janeSess],
                [$noSess,   null,  $janeBad,  false],
                [$noSess,   null,  $missingU, false],
                [$noSess,   null,  $missingP, false],
                [$noSess,   $john, $johnGood, $johnSess],
                [$noSess,   $john, $johnBad,  $johnSess],
                [$noSess,   $john, $janeGood, $johnSess],
                [$noSess,   $john, $janeBad,  $johnSess],
                [$noSess,   $john, $missingU, $johnSess],
                [$noSess,   $john, $missingP, $johnSess],
                [$noSess,   $jane, $johnGood, $janeSess],
                [$noSess,   $jane, $johnBad,  $janeSess],
                [$noSess,   $jane, $janeGood, $janeSess],
                [$noSess,   $jane, $janeBad,  $janeSess],
                [$noSess,   $jane, $missingU, $janeSess],
                [$noSess,   $jane, $missingP, $janeSess],
                [$noSess,   "",    $johnGood, $http401],
                [$noSess,   "",    $johnBad,  $http401],
                [$noSess,   "",    $janeGood, $http401],
                [$noSess,   "",    $janeBad,  $http401],
                [$noSess,   "",    $missingU, $http401],
                [$noSess,   "",    $missingP, $http401],
                [$fullHttp, null,  $johnGood, $http401],
                [$fullHttp, null,  $johnBad,  $http401],
                [$fullHttp, null,  $janeGood, $http401],
                [$fullHttp, null,  $janeBad,  $http401],
                [$fullHttp, null,  $missingU, $http401],
                [$fullHttp, null,  $missingP, $http401],
                [$fullHttp, $john, $johnGood, $johnSess],
                [$fullHttp, $john, $johnBad,  $johnSess],
                [$fullHttp, $john, $janeGood, $johnSess],
                [$fullHttp, $john, $janeBad,  $johnSess],
                [$fullHttp, $john, $missingU, $johnSess],
                [$fullHttp, $john, $missingP, $johnSess],
                [$fullHttp, $jane, $johnGood, $janeSess],
                [$fullHttp, $jane, $johnBad,  $janeSess],
                [$fullHttp, $jane, $janeGood, $janeSess],
                [$fullHttp, $jane, $janeBad,  $janeSess],
                [$fullHttp, $jane, $missingU, $janeSess],
                [$fullHttp, $jane, $missingP, $janeSess],
                [$fullHttp, "",    $johnGood, $http401],
                [$fullHttp, "",    $johnBad,  $http401],
                [$fullHttp, "",    $janeGood, $http401],
                [$fullHttp, "",    $janeBad,  $http401],
                [$fullHttp, "",    $missingU, $http401],
                [$fullHttp, "",    $missingP, $http401],
            ];
        } elseif ($type=="isLoggedIn") {
            return [
                // conf,    user,  session,  result
                [$defaults, null,  $sidJohn, $john],
                [$defaults, null,  $sidJane, $jane],
                [$defaults, null,  $sidBad,  false],
                [$defaults, $john, $sidJohn, $john],
                [$defaults, $john, $sidJane, $jane],
                [$defaults, $john, $sidBad,  false],
                [$defaults, $jane, $sidJohn, $john],
                [$defaults, $jane, $sidJane, $jane],
                [$defaults, $jane, $sidBad,  false],
                [$defaults, "",    $sidJohn, $http401],
                [$defaults, "",    $sidJane, $http401],
                [$defaults, "",    $sidBad,  $http401],
                [$preAuth,  null,  $sidJohn, $http401],
                [$preAuth,  null,  $sidJane, $http401],
                [$preAuth,  null,  $sidBad,  $http401],
                [$preAuth,  $john, $sidJohn, $john],
                [$preAuth,  $john, $sidJane, $jane],
                [$preAuth,  $john, $sidBad,  false],
                [$preAuth,  $jane, $sidJohn, $john],
                [$preAuth,  $jane, $sidJane, $jane],
                [$preAuth,  $jane, $sidBad,  false],
                [$preAuth,  "",    $sidJohn, $http401],
                [$preAuth,  "",    $sidJane, $http401],
                [$preAuth,  "",    $sidBad,  $http401],
                [$httpReq,  null,  $sidJohn, $http401],
                [$httpReq,  null,  $sidJane, $http401],
                [$httpReq,  null,  $sidBad,  $http401],
                [$httpReq,  $john, $sidJohn, $john],
                [$httpReq,  $john, $sidJane, $jane],
                [$httpReq,  $john, $sidBad,  false],
                [$httpReq,  $jane, $sidJohn, $john],
                [$httpReq,  $jane, $sidJane, $jane],
                [$httpReq,  $jane, $sidBad,  false],
                [$httpReq,  "",    $sidJohn, $http401],
                [$httpReq,  "",    $sidJane, $http401],
                [$httpReq,  "",    $sidBad,  $http401],
                [$noSess,   null,  $sidJohn, $john],
                [$noSess,   null,  $sidJane, $jane],
                [$noSess,   null,  $sidBad,  false],
                [$noSess,   $john, $sidJohn, $john],
                [$noSess,   $john, $sidJane, $john],
                [$noSess,   $john, $sidBad,  $john],
                [$noSess,   $jane, $sidJohn, $jane],
                [$noSess,   $jane, $sidJane, $jane],
                [$noSess,   $jane, $sidBad,  $jane],
                [$noSess,   "",    $sidJohn, $http401],
                [$noSess,   "",    $sidJane, $http401],
                [$noSess,   "",    $sidBad,  $http401],
                [$fullHttp, null,  $sidJohn, $http401],
                [$fullHttp, null,  $sidJane, $http401],
                [$fullHttp, null,  $sidBad,  $http401],
                [$fullHttp, $john, $sidJohn, $john],
                [$fullHttp, $john, $sidJane, $john],
                [$fullHttp, $john, $sidBad,  $john],
                [$fullHttp, $jane, $sidJohn, $jane],
                [$fullHttp, $jane, $sidJane, $jane],
                [$fullHttp, $jane, $sidBad,  $jane],
                [$fullHttp, "",    $sidJohn, $http401],
                [$fullHttp, "",    $sidJane, $http401],
                [$fullHttp, "",    $sidBad,  $http401],
            ];
        }
    }

    public function testHandleGenericError() {
        Phake::when(Arsse::$user)->auth(Arsse::$user->id, $this->anything())->thenThrow(new \JKingWeb\Arsse\Db\ExceptionTimeout("general"));
        $data = [
            'op'       => "login",
            'user'     => Arsse::$user->id,
            'password' => "secret",
        ];
        $exp = new EmptyResponse(500);
        $this->assertMessage($exp, $this->req($data));
    }

    public function testLogOut() {
        Phake::when(Arsse::$db)->sessionDestroy->thenReturn(true);
        $data = [
            'op'       => "logout",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood(['status' => "OK"]);
        $this->assertMessage($exp, $this->req($data));
        Phake::verify(Arsse::$db)->sessionDestroy(Arsse::$user->id, "PriestsOfSyrinx");
    }

    public function testHandleUnknownMethods() {
        $exp = $this->respErr("UNKNOWN_METHOD", ['method' => "thisMethodDoesNotExist"]);
        $data = [
            'op'       => "thisMethodDoesNotExist",
            'sid'      => "PriestsOfSyrinx",
        ];
        $this->assertMessage($exp, $this->req($data));
    }

    public function testHandleMixedCaseMethods() {
        $data = [
            'op'       => "isLoggedIn",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood(['status' => true]);
        $this->assertMessage($exp, $this->req($data));
        $data['op'] = "isloggedin";
        $this->assertMessage($exp, $this->req($data));
        $data['op'] = "ISLOGGEDIN";
        $this->assertMessage($exp, $this->req($data));
        $data['op'] = "iSlOgGeDiN";
        $this->assertMessage($exp, $this->req($data));
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
        $this->assertMessage($exp, $this->req($data));
    }

    public function testRetrieveProtocolLevel() {
        $data = [
            'op'       => "getApiLevel",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = $this->respGood(['level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        $this->assertMessage($exp, $this->req($data));
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
        Phake::when(Arsse::$db)->folderList(Arsse::$user->id, null, false)->thenReturn(new Result($this->v([$out[0], $out[2]])));
        Phake::when(Arsse::$db)->folderList(Arsse::$user->id, 1, false)->thenReturn(new Result($this->v([$out[1]])));
        // set up mocks that produce errors
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, $db[2])->thenThrow(new ExceptionInput("idMissing")); // parent folder does not exist
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, [])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => "",    'parent' => null])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->folderAdd(Arsse::$user->id, ['name' => "   ", 'parent' => null])->thenThrow(new ExceptionInput("whitespace"));
        // correctly add two folders
        $exp = $this->respGood("2");
        $this->assertMessage($exp, $this->req($in[0]));
        $exp = $this->respGood("3");
        $this->assertMessage($exp, $this->req($in[1]));
        // attempt to add the two folders again
        $exp = $this->respGood("2");
        $this->assertMessage($exp, $this->req($in[0]));
        $exp = $this->respGood("3");
        $this->assertMessage($exp, $this->req($in[1]));
        Phake::verify(Arsse::$db)->folderList(Arsse::$user->id, null, false);
        Phake::verify(Arsse::$db)->folderList(Arsse::$user->id, 1, false);
        // add a folder to a missing parent (silently fails)
        $exp = $this->respGood(false);
        $this->assertMessage($exp, $this->req($in[2]));
        // add some invalid folders
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
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
        $this->assertMessage($exp, $this->req($in[0]));
        // try deleting it again (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[0]));
        // delete a folder which does not exist (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // delete an invalid folder (causes an error)
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[2]));
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
        $this->assertMessage($exp, $this->req($in[0]));
        // move a folder which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // move a folder causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[6]));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
        $this->assertMessage($exp, $this->req($in[7]));
        $this->assertMessage($exp, $this->req($in[8]));
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
        $this->assertMessage($exp, $this->req($in[0]));
        // rename a folder which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // rename a folder causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[2]));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
        $this->assertMessage($exp, $this->req($in[6]));
        $this->assertMessage($exp, $this->req($in[7]));
        $this->assertMessage($exp, $this->req($in[8]));
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
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 42)->thenReturn($this->v(['id' => 42]));
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 47)->thenReturn($this->v(['id' => 47]));
        Phake::when(Arsse::$db)->folderPropertiesGet(Arsse::$user->id, 2112)->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, $this->anything(), $this->anything())->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(Arsse::$user->id, 4, $this->anything())->thenThrow(new ExceptionInput("idMissing"));
        Phake::when(Arsse::$db)->subscriptionList(Arsse::$user->id)->thenReturn(new Result($this->v($list)));
        for ($a = 0; $a < (sizeof($in) - 4); $a++) {
            $exp = $this->respGood($out[$a]);
            $this->assertMessage($exp, $this->req($in[$a]), "Failed test $a");
        }
        $exp = $this->respErr("INCORRECT_USAGE");
        for ($a = (sizeof($in) - 4); $a < sizeof($in); $a++) {
            $this->assertMessage($exp, $this->req($in[$a]), "Failed test $a");
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
        $this->assertMessage($exp, $this->req($in[0]));
        // try deleting it again (this should noisily fail, as should everything else)
        $exp = $this->respErr("FEED_NOT_FOUND");
        $this->assertMessage($exp, $this->req($in[0]));
        $this->assertMessage($exp, $this->req($in[1]));
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
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
        $this->assertMessage($exp, $this->req($in[0]));
        // move a subscription which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // move a subscription causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
        $this->assertMessage($exp, $this->req($in[6]));
        $this->assertMessage($exp, $this->req($in[7]));
        $this->assertMessage($exp, $this->req($in[8]));
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
            [Arsse::$user->id, 42, ['title' => "Ook"]],
            [Arsse::$user->id, 2112, ['title' => "Eek"]],
            [Arsse::$user->id, 42, ['title' => "Eek"]],
        ];
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[0])->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[1])->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionPropertiesSet(...$db[2])->thenThrow(new ExceptionInput("constraintViolation"));
        // succefully rename a subscription
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[0]));
        // rename a subscription which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // rename a subscription causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[2]));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
        $this->assertMessage($exp, $this->req($in[6]));
        $this->assertMessage($exp, $this->req($in[7]));
        $this->assertMessage($exp, $this->req($in[8]));
        Phake::verify(Arsse::$db)->subscriptionPropertiesSet(...$db[0]);
        Phake::verify(Arsse::$db)->subscriptionPropertiesSet(...$db[1]);
        Phake::verify(Arsse::$db)->subscriptionPropertiesSet(...$db[2]);
    }

    public function testRetrieveTheGlobalUnreadCount() {
        $in = ['op' => "getUnread", 'sid' => "PriestsOfSyrinx"];
        Phake::when(Arsse::$db)->subscriptionList(Arsse::$user->id)->thenReturn(new Result($this->v([
            ['id' => 1, 'unread' => 2112],
            ['id' => 2, 'unread' => 42],
            ['id' => 3, 'unread' => 47],
        ])));
        $exp = $this->respGood(['unread' => (string) (2112 + 42 + 47)]);
        $this->assertMessage($exp, $this->req($in));
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
        $this->assertMessage($this->respGood($exp[0]), $this->req($in));
        $this->assertMessage($this->respGood($exp[1]), $this->req($in));
    }

    public function testUpdateAFeed() {
        $in = [
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 1],
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2],
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1],
            ['op' => "updateFeed", 'sid' => "PriestsOfSyrinx"],
        ];
        Phake::when(Arsse::$db)->feedUpdate(11)->thenReturn(true);
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 1)->thenReturn($this->v(['id' => 1, 'feed' => 11]));
        Phake::when(Arsse::$db)->subscriptionPropertiesGet(Arsse::$user->id, 2)->thenThrow(new ExceptionInput("subjectMissing"));
        $exp = $this->respGood(['status' => "OK"]);
        $this->assertMessage($exp, $this->req($in[0]));
        Phake::verify(Arsse::$db)->feedUpdate(11);
        $exp = $this->respErr("FEED_NOT_FOUND");
        $this->assertMessage($exp, $this->req($in[1]));
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
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
        Phake::when(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Software", true)->thenReturn($this->v($out[0]));
        Phake::when(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Hardware", true)->thenReturn($this->v($out[1]));
        // set up mocks that produce errors
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, [])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, ['name' => ""])->thenThrow(new ExceptionInput("missing"));
        Phake::when(Arsse::$db)->labelAdd(Arsse::$user->id, ['name' => "   "])->thenThrow(new ExceptionInput("whitespace"));
        // correctly add two labels
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 2);
        $this->assertMessage($exp, $this->req($in[0]));
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 3);
        $this->assertMessage($exp, $this->req($in[1]));
        // attempt to add the two labels again
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 2);
        $this->assertMessage($exp, $this->req($in[0]));
        $exp = $this->respGood((-1 * API::LABEL_OFFSET) - 3);
        $this->assertMessage($exp, $this->req($in[1]));
        Phake::verify(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Software", true);
        Phake::verify(Arsse::$db)->labelPropertiesGet(Arsse::$user->id, "Hardware", true);
        // add some invalid labels
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
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
        $this->assertMessage($exp, $this->req($in[0]));
        // try deleting it again (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[0]));
        // delete a label which does not exist (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // delete some invalid labels (causes an error)
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
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
        $this->assertMessage($exp, $this->req($in[0]));
        // rename a label which does not exist (this should silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[1]));
        // rename a label causing a duplication (this should also silently fail)
        $exp = $this->respGood();
        $this->assertMessage($exp, $this->req($in[2]));
        // all the rest should cause errors
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
        $this->assertMessage($exp, $this->req($in[6]));
        $this->assertMessage($exp, $this->req($in[7]));
        $this->assertMessage($exp, $this->req($in[8]));
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
        Phake::when(Arsse::$db)->folderList($this->anything(), null, true)->thenReturn(new Result($this->v($this->folders)));
        Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result($this->v($this->topFolders)));
        Phake::when(Arsse::$db)->subscriptionList($this->anything())->thenReturn(new Result($this->v($this->subscriptions)));
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->articleCount($this->anything(), $this->anything())->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->v($this->starred));
        $exp = [
            [
                ['id' => "5", 'title' => "Local",         'unread' => 10, 'order_id' => 1],
                ['id' => "6", 'title' => "National",      'unread' => 18, 'order_id' => 2],
                ['id' => "4", 'title' => "Photography",   'unread' => 0,  'order_id' => 3],
                ['id' => "3", 'title' => "Politics",      'unread' => 0,  'order_id' => 4],
                ['id' => "2", 'title' => "Rocketry",      'unread' => 5,  'order_id' => 5],
                ['id' => "1", 'title' => "Science",       'unread' => 2,  'order_id' => 6],
                ['id' => 0,   'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1,  'title' => "Special",       'unread' => 11],
                ['id' => -2,  'title' => "Labels",        'unread' => "6"],
            ],
            [
                ['id' => "5", 'title' => "Local",         'unread' => 10, 'order_id' => 1],
                ['id' => "6", 'title' => "National",      'unread' => 18, 'order_id' => 2],
                ['id' => "3", 'title' => "Politics",      'unread' => 0,  'order_id' => 4],
                ['id' => "2", 'title' => "Rocketry",      'unread' => 5,  'order_id' => 5],
                ['id' => "1", 'title' => "Science",       'unread' => 2,  'order_id' => 6],
                ['id' => 0,   'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1,  'title' => "Special",       'unread' => 11],
                ['id' => -2,  'title' => "Labels",        'unread' => "6"],
            ],
            [
                ['id' => "5", 'title' => "Local",         'unread' => 10, 'order_id' => 1],
                ['id' => "6", 'title' => "National",      'unread' => 18, 'order_id' => 2],
                ['id' => "2", 'title' => "Rocketry",      'unread' => 5,  'order_id' => 5],
                ['id' => "1", 'title' => "Science",       'unread' => 2,  'order_id' => 6],
                ['id' => -1,  'title' => "Special",       'unread' => 11],
                ['id' => -2,  'title' => "Labels",        'unread' => "6"],
            ],
            [
                ['id' => "4", 'title' => "Photography",   'unread' => 0,  'order_id' => 1],
                ['id' => "3", 'title' => "Politics",      'unread' => 28, 'order_id' => 2],
                ['id' => "1", 'title' => "Science",       'unread' => 7,  'order_id' => 3],
                ['id' => 0,   'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1,  'title' => "Special",       'unread' => 11],
                ['id' => -2,  'title' => "Labels",        'unread' => "6"],
            ],
            [
                ['id' => "3", 'title' => "Politics",      'unread' => 28, 'order_id' => 2],
                ['id' => "1", 'title' => "Science",       'unread' => 7,  'order_id' => 3],
                ['id' => 0,   'title' => "Uncategorized", 'unread' => 0],
                ['id' => -1,  'title' => "Special",       'unread' => 11],
                ['id' => -2,  'title' => "Labels",        'unread' => "6"],
            ],
            [
                ['id' => "3", 'title' => "Politics",      'unread' => 28, 'order_id' => 2],
                ['id' => "1", 'title' => "Science",       'unread' => 7,  'order_id' => 3],
                ['id' => -1,  'title' => "Special",       'unread' => 11],
                ['id' => -2,  'title' => "Labels",        'unread' => "6"],
            ],
        ];
        for ($a = 0; $a < sizeof($in); $a++) {
            $this->assertMessage($this->respGood($exp[$a]), $this->req($in[$a]), "Test $a failed");
        }
    }

    public function testRetrieveCounterList() {
        $in = ['op' => "getCounters", 'sid' => "PriestsOfSyrinx"];
        Phake::when(Arsse::$db)->folderList($this->anything())->thenReturn(new Result($this->v($this->folders)));
        Phake::when(Arsse::$db)->subscriptionList($this->anything())->thenReturn(new Result($this->v($this->subscriptions)));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->v($this->usedLabels)));
        Phake::when(Arsse::$db)->articleCount($this->anything(), $this->anything())->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->v($this->starred));
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
            ['id' => "3", 'updated' => "2016-05-23T06:40:02Z", 'counter' => 2,  'has_img' => 1],
            ['id' => "4", 'updated' => "2017-10-09T15:58:34Z", 'counter' => 6,  'has_img' => 1],
            ['id' => "6", 'updated' => "2010-02-12T20:08:47Z", 'counter' => 0,  'has_img' => 1],
            ['id' => "1", 'updated' => "2017-09-15T22:54:16Z", 'counter' => 5,  'has_img' => 0],
            ['id' => "5", 'updated' => "2017-07-07T17:07:17Z", 'counter' => 12, 'has_img' => 0],
            ['id' => "2", 'updated' => "2011-11-11T11:11:11Z", 'counter' => 10, 'has_img' => 1],
            ['id' => 5, 'kind' => "cat", 'counter' => 10],
            ['id' => 6, 'kind' => "cat", 'counter' => 18],
            ['id' => 4, 'kind' => "cat", 'counter' => 0],
            ['id' => 3, 'kind' => "cat", 'counter' => 28],
            ['id' => 2, 'kind' => "cat", 'counter' => 5],
            ['id' => 1, 'kind' => "cat", 'counter' => 7],
            ['id' => 0, 'kind' => "cat", 'counter' => 0],
            ['id' => -2, 'kind' => "cat", 'counter' => 6],
        ];
        $this->assertMessage($this->respGood($exp), $this->req($in));
    }

    public function testRetrieveTheLabelList() {
        $in = [
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx"],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 1],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 2],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 3],
            ['op' => "getLabels", 'sid' => "PriestsOfSyrinx", 'article_id' => 4],
        ];
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 1)->thenReturn($this->v([1,3]));
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2)->thenReturn($this->v([3]));
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
            $this->assertMessage($this->respGood($exp[$a]), $this->req($in[$a]), "Test $a failed");
        }
    }

    public function testAssignArticlesToALabel() {
        $list = [
            range(1, 100),
            range(1, 50),
            range(51, 100),
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
        $this->assertMessage($exp, $this->req($in[0]));
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[1]), true);
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[2]), true);
        $exp = $this->respGood(['status' => "OK", 'updated' => 7]);
        $this->assertMessage($exp, $this->req($in[1]));
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[1]), false);
        Phake::verify(Arsse::$db)->labelArticlesSet(Arsse::$user->id, 1088, (new Context)->articles($list[2]), false);
        $exp = $this->respGood(['status' => "OK", 'updated' => 0]);
        $this->assertMessage($exp, $this->req($in[2]));
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[3]));
        $this->assertMessage($exp, $this->req($in[4]));
        $this->assertMessage($exp, $this->req($in[5]));
        $this->assertMessage($exp, $this->req($in[6]));
    }

    public function testRetrieveFeedTree() {
        $in = [
            ['op' => "getFeedTree", 'sid' => "PriestsOfSyrinx", 'include_empty' => true],
            ['op' => "getFeedTree", 'sid' => "PriestsOfSyrinx"],
        ];
        Phake::when(Arsse::$db)->folderList($this->anything(), null, true)->thenReturn(new Result($this->v($this->folders)));
        Phake::when(Arsse::$db)->subscriptionList($this->anything())->thenReturn(new Result($this->v($this->subscriptions)));
        Phake::when(Arsse::$db)->labelList($this->anything(), true)->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->articleCount($this->anything(), $this->anything())->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->v($this->starred));
        // the expectations are packed tightly since they're very verbose; one can use var_export() (or convert to JSON) to pretty-print them
        $exp = ['categories'=>['identifier'=>'id','label'=>'name','items'=>[['name'=>'Special','id'=>'CAT:-1','bare_id'=>-1,'type'=>'category','unread'=>0,'items'=>[['name'=>'All articles','id'=>'FEED:-4','bare_id'=>-4,'icon'=>'images/folder.png','unread'=>35,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Fresh articles','id'=>'FEED:-3','bare_id'=>-3,'icon'=>'images/fresh.png','unread'=>7,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Starred articles','id'=>'FEED:-1','bare_id'=>-1,'icon'=>'images/star.png','unread'=>4,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Published articles','id'=>'FEED:-2','bare_id'=>-2,'icon'=>'images/feed.png','unread'=>0,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Archived articles','id'=>'FEED:0','bare_id'=>0,'icon'=>'images/archive.png','unread'=>0,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Recently read','id'=>'FEED:-6','bare_id'=>-6,'icon'=>'images/time.png','unread'=>0,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],],],['name'=>'Labels','id'=>'CAT:-2','bare_id'=>-2,'type'=>'category','unread'=>6,'items'=>[['name'=>'Fascinating','id'=>'FEED:-1027','bare_id'=>-1027,'unread'=>0,'icon'=>'images/label.png','type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'','fg_color'=>'','bg_color'=>'',],['name'=>'Interesting','id'=>'FEED:-1029','bare_id'=>-1029,'unread'=>0,'icon'=>'images/label.png','type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'','fg_color'=>'','bg_color'=>'',],['name'=>'Logical','id'=>'FEED:-1025','bare_id'=>-1025,'unread'=>0,'icon'=>'images/label.png','type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'','fg_color'=>'','bg_color'=>'',],],],['name'=>'Photography','id'=>'CAT:4','bare_id'=>4,'parent_id'=>null,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(0 feeds)','items'=>[],],['name'=>'Politics','id'=>'CAT:3','bare_id'=>3,'parent_id'=>null,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(3 feeds)','items'=>[['name'=>'Local','id'=>'CAT:5','bare_id'=>5,'parent_id'=>3,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(1 feed)','items'=>[['name'=>'Toronto Star','id'=>'FEED:2','bare_id'=>2,'icon'=>'feed-icons/2.ico','error'=>'oops','param'=>'2011-11-11T11:11:11Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],['name'=>'National','id'=>'CAT:6','bare_id'=>6,'parent_id'=>3,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(2 feeds)','items'=>[['name'=>'CBC News','id'=>'FEED:4','bare_id'=>4,'icon'=>'feed-icons/4.ico','error'=>'','param'=>'2017-10-09T15:58:34Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],['name'=>'Ottawa Citizen','id'=>'FEED:5','bare_id'=>5,'icon'=>false,'error'=>'','param'=>'2017-07-07T17:07:17Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],],],['name'=>'Science','id'=>'CAT:1','bare_id'=>1,'parent_id'=>null,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(2 feeds)','items'=>[['name'=>'Rocketry','id'=>'CAT:2','bare_id'=>2,'parent_id'=>1,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(1 feed)','items'=>[['name'=>'NASA JPL','id'=>'FEED:1','bare_id'=>1,'icon'=>false,'error'=>'','param'=>'2017-09-15T22:54:16Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],['name'=>'Ars Technica','id'=>'FEED:3','bare_id'=>3,'icon'=>'feed-icons/3.ico','error'=>'argh','param'=>'2016-05-23T06:40:02Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],['name'=>'Uncategorized','id'=>'CAT:0','bare_id'=>0,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'parent_id'=>null,'param'=>'(1 feed)','items'=>[['name'=>'Eurogamer','id'=>'FEED:6','bare_id'=>6,'icon'=>'feed-icons/6.ico','error'=>'','param'=>'2010-02-12T20:08:47Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],],],];
        $this->assertMessage($this->respGood($exp), $this->req($in[0]));
        $exp = ['categories'=>['identifier'=>'id','label'=>'name','items'=>[['name'=>'Special','id'=>'CAT:-1','bare_id'=>-1,'type'=>'category','unread'=>0,'items'=>[['name'=>'All articles','id'=>'FEED:-4','bare_id'=>-4,'icon'=>'images/folder.png','unread'=>35,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Fresh articles','id'=>'FEED:-3','bare_id'=>-3,'icon'=>'images/fresh.png','unread'=>7,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Starred articles','id'=>'FEED:-1','bare_id'=>-1,'icon'=>'images/star.png','unread'=>4,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Published articles','id'=>'FEED:-2','bare_id'=>-2,'icon'=>'images/feed.png','unread'=>0,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Archived articles','id'=>'FEED:0','bare_id'=>0,'icon'=>'images/archive.png','unread'=>0,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],['name'=>'Recently read','id'=>'FEED:-6','bare_id'=>-6,'icon'=>'images/time.png','unread'=>0,'type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'',],],],['name'=>'Labels','id'=>'CAT:-2','bare_id'=>-2,'type'=>'category','unread'=>6,'items'=>[['name'=>'Fascinating','id'=>'FEED:-1027','bare_id'=>-1027,'unread'=>0,'icon'=>'images/label.png','type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'','fg_color'=>'','bg_color'=>'',],['name'=>'Interesting','id'=>'FEED:-1029','bare_id'=>-1029,'unread'=>0,'icon'=>'images/label.png','type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'','fg_color'=>'','bg_color'=>'',],['name'=>'Logical','id'=>'FEED:-1025','bare_id'=>-1025,'unread'=>0,'icon'=>'images/label.png','type'=>'feed','auxcounter'=>0,'error'=>'','updated'=>'','fg_color'=>'','bg_color'=>'',],],],['name'=>'Politics','id'=>'CAT:3','bare_id'=>3,'parent_id'=>null,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(3 feeds)','items'=>[['name'=>'Local','id'=>'CAT:5','bare_id'=>5,'parent_id'=>3,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(1 feed)','items'=>[['name'=>'Toronto Star','id'=>'FEED:2','bare_id'=>2,'icon'=>'feed-icons/2.ico','error'=>'oops','param'=>'2011-11-11T11:11:11Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],['name'=>'National','id'=>'CAT:6','bare_id'=>6,'parent_id'=>3,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(2 feeds)','items'=>[['name'=>'CBC News','id'=>'FEED:4','bare_id'=>4,'icon'=>'feed-icons/4.ico','error'=>'','param'=>'2017-10-09T15:58:34Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],['name'=>'Ottawa Citizen','id'=>'FEED:5','bare_id'=>5,'icon'=>false,'error'=>'','param'=>'2017-07-07T17:07:17Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],],],['name'=>'Science','id'=>'CAT:1','bare_id'=>1,'parent_id'=>null,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(2 feeds)','items'=>[['name'=>'Rocketry','id'=>'CAT:2','bare_id'=>2,'parent_id'=>1,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'param'=>'(1 feed)','items'=>[['name'=>'NASA JPL','id'=>'FEED:1','bare_id'=>1,'icon'=>false,'error'=>'','param'=>'2017-09-15T22:54:16Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],['name'=>'Ars Technica','id'=>'FEED:3','bare_id'=>3,'icon'=>'feed-icons/3.ico','error'=>'argh','param'=>'2016-05-23T06:40:02Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],['name'=>'Uncategorized','id'=>'CAT:0','bare_id'=>0,'type'=>'category','auxcounter'=>0,'unread'=>0,'child_unread'=>0,'checkbox'=>false,'parent_id'=>null,'param'=>'(1 feed)','items'=>[['name'=>'Eurogamer','id'=>'FEED:6','bare_id'=>6,'icon'=>'feed-icons/6.ico','error'=>'','param'=>'2010-02-12T20:08:47Z','unread'=>0,'auxcounter'=>0,'checkbox'=>false,],],],],],];
        $this->assertMessage($this->respGood($exp), $this->req($in[1]));
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
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => 0, 'is_cat' => true],
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2, 'is_cat' => true],
        ];
        $in3 = [
            // this one has a tricky time-based context
            ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3],
        ];
        Phake::when(Arsse::$db)->articleMark->thenThrow(new ExceptionInput("typeViolation"));
        $exp = $this->respGood(['status' => "OK"]);
        // verify the above are in fact no-ops
        for ($a = 0; $a < sizeof($in1); $a++) {
            $this->assertMessage($exp, $this->req($in1[$a]), "Test $a failed");
        }
        Phake::verify(Arsse::$db, Phake::times(0))->articleMark;
        // verify the simple contexts
        for ($a = 0; $a < sizeof($in2); $a++) {
            $this->assertMessage($exp, $this->req($in2[$a]), "Test $a failed");
        }
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], new Context);
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->starred(true));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->label(1088));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->subscription(2112));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->folder(42));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->folderShallow(0));
        Phake::verify(Arsse::$db)->articleMark($this->anything(), ['read' => true], (new Context)->labelled(true));
        // verify the time-based mock
        $t = Date::sub("PT24H");
        for ($a = 0; $a < sizeof($in3); $a++) {
            $this->assertMessage($exp, $this->req($in3[$a]), "Test $a failed");
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
        Phake::when(Arsse::$db)->articleStarred($this->anything())->thenReturn($this->v($this->starred));
        Phake::when(Arsse::$db)->articleCount->thenReturn(7); // FIXME: this should check an unread+modifiedSince context
        Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true))->thenReturn(35);
        // label mocks
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->v($this->usedLabels)));
        // subscription and folder list and unread count mocks
        Phake::when(Arsse::$db)->folderList->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->subscriptionList->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->folderList($this->anything())->thenReturn(new Result($this->v($this->folders)));
        Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, true)->thenReturn(new Result($this->v($this->subscriptions)));
        Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, false)->thenReturn(new Result($this->v($this->filterSubs(null))));
        Phake::when(Arsse::$db)->folderList($this->anything(), null)->thenReturn(new Result($this->v($this->folders)));
        Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result($this->v($this->filterFolders(null))));
        foreach ($this->folders as $f) {
            Phake::when(Arsse::$db)->folderList($this->anything(), $f['id'], false)->thenReturn(new Result($this->v($this->filterFolders($f['id']))));
            Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true)->folder($f['id']))->thenReturn($this->reduceFolders($f['id']));
            Phake::when(Arsse::$db)->subscriptionList($this->anything(), $f['id'], false)->thenReturn(new Result($this->v($this->filterSubs($f['id']))));
        }
        $exp = [
            [
                ['id' => 6, 'title' => 'Eurogamer', 'unread' => 0, 'cat_id' => 0, 'feed_url' => " http://example.com/6", 'has_icon' => true, 'last_updated' => 1266005327, 'order_id' => 1],
            ],
            [
                ['id' => -1, 'title' => "Starred articles",   'unread' => "4",  'cat_id' => -1],
                ['id' => -2, 'title' => "Published articles", 'unread' => "0",  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => "7",  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => "35", 'cat_id' => -1],
                ['id' => -6, 'title' => "Recently read",      'unread' => 0,    'cat_id' => -1],
                ['id' =>  0, 'title' => "Archived articles",  'unread' => "0",  'cat_id' => -1],
            ],
            [
                ['id' => -1, 'title' => "Starred articles",   'unread' => "4",  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => "7",  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => "35", 'cat_id' => -1],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => "6",  'cat_id' => -2],
                ['id' => -1025, 'title' => "Logical",     'unread' => "0",  'cat_id' => -2],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => "6",  'cat_id' => -2],
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
                ['id' => -1027, 'title' => "Fascinating", 'unread' => "6",  'cat_id' => -2],
                ['id' => -1025, 'title' => "Logical",     'unread' => "0",  'cat_id' => -2],
                ['id' => -1, 'title' => "Starred articles",   'unread' => "4",  'cat_id' => -1],
                ['id' => -2, 'title' => "Published articles", 'unread' => "0",  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => "7",  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => "35", 'cat_id' => -1],
                ['id' => -6, 'title' => "Recently read",      'unread' => 0,    'cat_id' => -1],
                ['id' =>  0, 'title' => "Archived articles",  'unread' => "0",  'cat_id' => -1],
                ['id' => 3, 'title' => 'Ars Technica',   'unread' => 2,  'cat_id' => 1, 'feed_url' => " http://example.com/3", 'has_icon' => true,  'last_updated' => 1463985602, 'order_id' => 1],
                ['id' => 4, 'title' => 'CBC News',       'unread' => 6,  'cat_id' => 6, 'feed_url' => " http://example.com/4", 'has_icon' => true,  'last_updated' => 1507564714, 'order_id' => 2],
                ['id' => 6, 'title' => 'Eurogamer',      'unread' => 0,  'cat_id' => 0, 'feed_url' => " http://example.com/6", 'has_icon' => true,  'last_updated' => 1266005327, 'order_id' => 3],
                ['id' => 1, 'title' => 'NASA JPL',       'unread' => 5,  'cat_id' => 2, 'feed_url' => " http://example.com/1", 'has_icon' => false, 'last_updated' => 1505516056, 'order_id' => 4],
                ['id' => 5, 'title' => 'Ottawa Citizen', 'unread' => 12, 'cat_id' => 6, 'feed_url' => " http://example.com/5", 'has_icon' => false, 'last_updated' => 1499447237, 'order_id' => 5],
                ['id' => 2, 'title' => 'Toronto Star',   'unread' => 10, 'cat_id' => 5, 'feed_url' => " http://example.com/2", 'has_icon' => true,  'last_updated' => 1321009871, 'order_id' => 6],
            ],
            [
                ['id' => -1027, 'title' => "Fascinating", 'unread' => "6",  'cat_id' => -2],
                ['id' => -1, 'title' => "Starred articles",   'unread' => "4",  'cat_id' => -1],
                ['id' => -3, 'title' => "Fresh articles",     'unread' => "7",  'cat_id' => -1],
                ['id' => -4, 'title' => "All articles",       'unread' => "35", 'cat_id' => -1],
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
            $this->assertMessage($this->respGood($exp[$a]), $this->req($in1[$a]), "Test $a failed");
        }
        for ($a = 0; $a < sizeof($in2); $a++) {
            $this->assertMessage($this->respGood([]), $this->req($in2[$a]), "Test $a failed");
        }
    }

    protected function filterFolders(int $id = null): array {
        return array_filter($this->folders, function ($value) use ($id) {
            return $value['parent']==$id;
        });
    }

    protected function filterSubs(int $folder = null): array {
        return array_filter($this->subscriptions, function ($value) use ($folder) {
            return $value['folder']==$folder;
        });
    }

    protected function reduceFolders(int $id = null) : int {
        $out = 0;
        foreach ($this->filterFolders($id) as $f) {
            $out += $this->reduceFolders($f['id']);
        }
        $out += array_reduce(array_filter($this->subscriptions, function ($value) use ($id) {
            return $value['folder']==$id;
        }), function ($sum, $value) {
            return $sum + $value['unread'];
        }, 0);
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
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([42, 2112])->starred(true), $this->anything())->thenReturn(new Result($this->v([['id' => 42]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([42, 2112])->starred(false), $this->anything())->thenReturn(new Result($this->v([['id' => 2112]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([42, 2112])->unread(true), $this->anything())->thenReturn(new Result($this->v([['id' => 42]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([42, 2112])->unread(false), $this->anything())->thenReturn(new Result($this->v([['id' => 2112]])));
        Phake::when(Arsse::$db)->articleMark->thenReturn(1);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' => false], (new Context)->articles([42, 2112]))->thenReturn(2);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' =>  true], (new Context)->articles([42, 2112]))->thenReturn(4);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' => false], (new Context)->articles([42]))->thenReturn(8);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['starred' =>  true], (new Context)->articles([2112]))->thenReturn(16);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    =>  true], (new Context)->articles([42, 2112]))->thenReturn(32); // false is read for TT-RSS
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    => false], (new Context)->articles([42, 2112]))->thenReturn(64);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    =>  true], (new Context)->articles([42]))->thenReturn(128);
        Phake::when(Arsse::$db)->articleMark($this->anything(), ['read'    => false], (new Context)->articles([2112]))->thenReturn(256);
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
            $this->assertMessage($out[$a], $this->req($in[$a]), "Test $a failed");
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
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->v($this->usedLabels)));
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 101)->thenReturn([]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 102)->thenReturn($this->v([1,3]));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([101, 102]))->thenReturn(new Result($this->v($this->articles)));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([101]))->thenReturn(new Result($this->v([$this->articles[0]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->articles([102]))->thenReturn(new Result($this->v([$this->articles[1]])));
        $exp = $this->respErr("INCORRECT_USAGE");
        $this->assertMessage($exp, $this->req($in[0]));
        $this->assertMessage($exp, $this->req($in[1]));
        $this->assertMessage($exp, $this->req($in[2]));
        $this->assertMessage($exp, $this->req($in[3]));
        $exp = [
            [
                'id' => "101",
                'guid' => null,
                'title' => 'Article title 1',
                'link' => 'http://example.com/1',
                'labels' => [],
                'unread' => true,
                'marked' => false,
                'published' => false,
                'comments' => "",
                'author' => '',
                'updated' => strtotime('2000-01-01T00:00:01Z'),
                'feed_id' => "8",
                'feed_title' => "Feed 11",
                'attachments' => [],
                'score' => 0,
                'note' => null,
                'lang' => "",
                'content' => '<p>Article content 1</p>',
            ],
            [
                'id' => "102",
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
                'updated' => strtotime('2000-01-02T00:00:02Z'),
                'feed_id' => "8",
                'feed_title' => "Feed 11",
                'attachments' => [
                    [
                        'id'           => "0",
                        'content_url'  => "http://example.com/text",
                        'content_type' => "text/plain",
                        'title'        => "",
                        'duration'     => "",
                        'width'        => "",
                        'height'       => "",
                        'post_id'      => "102",
                    ],
                ],
                'score' => 0,
                'note' => "Note 2",
                'lang' => "",
                'content' => '<p>Article content 2</p>',
            ],
        ];
        $this->assertMessage($this->respGood($exp), $this->req($in[4]));
        $this->assertMessage($this->respGood([$exp[0]]), $this->req($in[5]));
        $this->assertMessage($this->respGood([$exp[1]]), $this->req($in[6]));
        // test the special case when labels are not used
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result([]));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result([]));
        $this->assertMessage($this->respGood([$exp[0]]), $this->req($in[5]));
    }

    public function testRetrieveCompactHeadlines() {
        $in1 = [
            // erroneous input
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx"],
            // empty results
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 0],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2, 'is_cat' => true], // is_cat is not used in getCompactHeadlines
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'view_mode' => "published"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6, 'view_mode' => "unread"],
            // non-empty results
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'view_mode' => "adaptive"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112, 'view_mode' => "adaptive"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112, 'view_mode' => "unread"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'view_mode' => "marked"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'view_mode' => "has_note"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'limit' => 5],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'skip' => 2],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'limit' => 5, 'skip' => 2],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'since_id' => 47],
        ];
        $in2 = [
            // time-based contexts, handled separately
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6, 'view_mode' => "adaptive"],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3],
            ['op' => "getCompactHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3, 'view_mode' => "marked"],
        ];
        Phake::when(Arsse::$db)->articleList->thenReturn(new Result($this->v([['id' => 0]])));
        Phake::when(Arsse::$db)->articleCount->thenReturn(0);
        Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true))->thenReturn(1);
        $c = (new Context)->reverse(true);
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->subscription(2112), Database::LIST_MINIMAL)->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->articleList($this->anything(), $c, Database::LIST_MINIMAL)->thenReturn(new Result($this->v($this->articles)));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->starred(true), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 1]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->label(1088), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 2]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(true), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 3]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->label(1088)->unread(true), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 4]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->subscription(42)->starred(true), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 5]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->subscription(42)->annotated(true), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 6]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->limit(5), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 7]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->offset(2), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 8]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->limit(5)->offset(2), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 9]])));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->oldestArticle(48), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 10]])));
        $out1 = [
            $this->respErr("INCORRECT_USAGE"),
            $this->respGood([]),
            $this->respGood([]),
            $this->respGood([]),
            $this->respGood([]),
            $this->respGood([]),
            $this->respGood([]),
            $this->respGood([['id' => 101],['id' => 102]]),
            $this->respGood([['id' => 1]]),
            $this->respGood([['id' => 2]]),
            $this->respGood([['id' => 3]]),
            $this->respGood([['id' => 2]]), // the result is 2 rather than 4 because there are no unread, so the unread context is not used
            $this->respGood([['id' => 4]]),
            $this->respGood([['id' => 5]]),
            $this->respGood([['id' => 6]]),
            $this->respGood([['id' => 7]]),
            $this->respGood([['id' => 8]]),
            $this->respGood([['id' => 9]]),
            $this->respGood([['id' => 10]]),
        ];
        $out2 = [
            $this->respGood([['id' => 1001]]),
            $this->respGood([['id' => 1001]]),
            $this->respGood([['id' => 1002]]),
            $this->respGood([['id' => 1003]]),
        ];
        for ($a = 0; $a < sizeof($in1); $a++) {
            $this->assertMessage($out1[$a], $this->req($in1[$a]), "Test $a failed");
        }
        for ($a = 0; $a < sizeof($in2); $a++) {
            Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(false)->markedSince(Date::sub("PT24H")), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 1001]])));
            Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(true)->modifiedSince(Date::sub("PT24H")), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 1002]])));
            Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(true)->modifiedSince(Date::sub("PT24H"))->starred(true), Database::LIST_MINIMAL)->thenReturn(new Result($this->v([['id' => 1003]])));
            $this->assertMessage($out2[$a], $this->req($in2[$a]), "Test $a failed");
        }
    }

    public function testRetrieveFullHeadlines() {
        $in1 = [
            // empty results
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 0],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1, 'is_cat' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'view_mode' => "published"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6, 'view_mode' => "unread"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112],
        ];
        $in2 = [
            // simple context tests
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'view_mode' => "adaptive"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112, 'view_mode' => "adaptive"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2112, 'view_mode' => "unread"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'view_mode' => "marked"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'view_mode' => "has_note"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'limit' => 5],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'skip' => 2],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'limit' => 5, 'skip' => 2],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'since_id' => 47],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3, 'is_cat' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'is_cat' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -2, 'is_cat' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 0, 'is_cat' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'is_cat' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'is_cat' => true, 'include_nested' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'order_by' => "feed_dates"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'order_by' => "date_reverse"],
        ];
        $in3 = [
            // time-based context tests
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -6, 'view_mode' => "adaptive"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3, 'view_mode' => "marked"],
        ];
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->v($this->usedLabels)));
        Phake::when(Arsse::$db)->articleLabelsGet->thenReturn([]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2112)->thenReturn($this->v([1,3]));
        Phake::when(Arsse::$db)->articleCategoriesGet->thenReturn([]);
        Phake::when(Arsse::$db)->articleCategoriesGet($this->anything(), 2112)->thenReturn(["Boring","Illogical"]);
        Phake::when(Arsse::$db)->articleList->thenReturn($this->generateHeadlines(0));
        Phake::when(Arsse::$db)->articleCount->thenReturn(0);
        Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true))->thenReturn(1);
        $c = (new Context)->limit(200)->reverse(true);
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->subscription(2112), Database::LIST_FULL)->thenThrow(new ExceptionInput("subjectMissing"));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->starred(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(1));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->label(1088), Database::LIST_FULL)->thenReturn($this->generateHeadlines(2));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(3));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->label(1088)->unread(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(4));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->subscription(42)->starred(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(5));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->subscription(42)->annotated(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(6));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->limit(5), Database::LIST_FULL)->thenReturn($this->generateHeadlines(7));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->offset(2), Database::LIST_FULL)->thenReturn($this->generateHeadlines(8));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->limit(5)->offset(2), Database::LIST_FULL)->thenReturn($this->generateHeadlines(9));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->oldestArticle(48), Database::LIST_FULL)->thenReturn($this->generateHeadlines(10));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c), Database::LIST_FULL)->thenReturn($this->generateHeadlines(11));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->labelled(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(12));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->folderShallow(0), Database::LIST_FULL)->thenReturn($this->generateHeadlines(13));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->folderShallow(42), Database::LIST_FULL)->thenReturn($this->generateHeadlines(14));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->folder(42), Database::LIST_FULL)->thenReturn($this->generateHeadlines(15));
        Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->reverse(false), Database::LIST_FULL)->thenReturn($this->generateHeadlines(16));
        $out2 = [
            $this->respErr("INCORRECT_USAGE"),
            $this->outputHeadlines(11),
            $this->outputHeadlines(1),
            $this->outputHeadlines(2),
            $this->outputHeadlines(3),
            $this->outputHeadlines(2), // the result is 2 rather than 4 because there are no unread, so the unread context is not used
            $this->outputHeadlines(4),
            $this->outputHeadlines(5),
            $this->outputHeadlines(6),
            $this->outputHeadlines(7),
            $this->outputHeadlines(8),
            $this->outputHeadlines(9),
            $this->outputHeadlines(10),
            $this->outputHeadlines(11),
            $this->outputHeadlines(11),
            $this->outputHeadlines(12),
            $this->outputHeadlines(13),
            $this->outputHeadlines(14),
            $this->outputHeadlines(15),
            $this->outputHeadlines(11), // defaulting sorting is not fully implemented
            $this->outputHeadlines(16),
        ];
        $out3 = [
            $this->outputHeadlines(1001),
            $this->outputHeadlines(1001),
            $this->outputHeadlines(1002),
            $this->outputHeadlines(1003),
        ];
        for ($a = 0; $a < sizeof($in1); $a++) {
            $this->assertMessage($this->respGood([]), $this->req($in1[$a]), "Test $a failed");
        }
        for ($a = 0; $a < sizeof($in2); $a++) {
            $this->assertMessage($out2[$a], $this->req($in2[$a]), "Test $a failed");
        }
        for ($a = 0; $a < sizeof($in3); $a++) {
            Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(false)->markedSince(Date::sub("PT24H")), Database::LIST_FULL)->thenReturn($this->generateHeadlines(1001));
            Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(true)->modifiedSince(Date::sub("PT24H")), Database::LIST_FULL)->thenReturn($this->generateHeadlines(1002));
            Phake::when(Arsse::$db)->articleList($this->anything(), (clone $c)->unread(true)->modifiedSince(Date::sub("PT24H"))->starred(true), Database::LIST_FULL)->thenReturn($this->generateHeadlines(1003));
            $this->assertMessage($out3[$a], $this->req($in3[$a]), "Test $a failed");
        }
    }

    public function testRetrieveFullHeadlinesCheckingExtraFields() {
        $in = [
            // empty results
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'show_content' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'include_attachments' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'include_header' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -3, 'is_cat' => true, 'include_header' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -1, 'is_cat' => true, 'include_header' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 2112, 'include_header' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'include_header' => true, 'order_by' => "date_reverse"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'skip' => 47, 'include_header' => true],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => 42, 'skip' => 47, 'include_header' => true, 'order_by' => "date_reverse"],
            ['op' => "getHeadlines", 'sid' => "PriestsOfSyrinx", 'feed_id' => -4, 'show_excerpt' => true],
        ];
        Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result($this->v($this->labels)));
        Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result($this->v($this->usedLabels)));
        Phake::when(Arsse::$db)->articleLabelsGet->thenReturn([]);
        Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2112)->thenReturn($this->v([1,3]));
        Phake::when(Arsse::$db)->articleCategoriesGet->thenReturn([]);
        Phake::when(Arsse::$db)->articleCategoriesGet($this->anything(), 2112)->thenReturn(["Boring","Illogical"]);
        Phake::when(Arsse::$db)->articleList->thenReturn($this->generateHeadlines(1));
        Phake::when(Arsse::$db)->articleCount->thenReturn(0);
        Phake::when(Arsse::$db)->articleCount($this->anything(), (new Context)->unread(true))->thenReturn(1);
        // sanity check; this makes sure extra fields are not included in default situations
        $test = $this->req($in[0]);
        $this->assertMessage($this->outputHeadlines(1), $test);
        // test 'show_content'
        $test = $this->req($in[1]);
        $this->assertArrayHasKey("content", $test->getPayload()['content'][0]);
        $this->assertArrayHasKey("content", $test->getPayload()['content'][1]);
        foreach ($this->generateHeadlines(1) as $key => $row) {
            $this->assertSame($row['content'], $test->getPayload()['content'][$key]['content']);
        }
        // test 'include_attachments'
        $test = $this->req($in[2]);
        $exp = [
            [
                'id'           => "0",
                'content_url'  => "http://example.com/text",
                'content_type' => "text/plain",
                'title'        => "",
                'duration'     => "",
                'width'        => "",
                'height'       => "",
                'post_id'      => "2112",
            ],
        ];
        $this->assertArrayHasKey("attachments", $test->getPayload()['content'][0]);
        $this->assertArrayHasKey("attachments", $test->getPayload()['content'][1]);
        $this->assertSame([], $test->getPayload()['content'][0]['attachments']);
        $this->assertSame($exp, $test->getPayload()['content'][1]['attachments']);
        // test 'include_header'
        $test = $this->req($in[3]);
        $exp = $this->respGood([
            ['id' => -4, 'is_cat' => false, 'first_id' => 1],
            $this->outputHeadlines(1)->getPayload()['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with a category
        $test = $this->req($in[4]);
        $exp = $this->respGood([
            ['id' => -3, 'is_cat' => true, 'first_id' => 1],
            $this->outputHeadlines(1)->getPayload()['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with an empty result
        $test = $this->req($in[5]);
        $exp = $this->respGood([
            ['id' => -1, 'is_cat' => true, 'first_id' => 0],
            [],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with an erroneous result
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->limit(200)->reverse(true)->subscription(2112), $this->anything())->thenThrow(new ExceptionInput("subjectMissing"));
        $test = $this->req($in[6]);
        $exp = $this->respGood([
            ['id' => 2112, 'is_cat' => false, 'first_id' => 0],
            [],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with ascending order
        $test = $this->req($in[7]);
        $exp = $this->respGood([
            ['id' => -4, 'is_cat' => false, 'first_id' => 0],
            $this->outputHeadlines(1)->getPayload()['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with skip
        Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->reverse(true)->limit(1)->subscription(42), Database::LIST_MINIMAL)->thenReturn($this->generateHeadlines(1867));
        $test = $this->req($in[8]);
        $exp = $this->respGood([
            ['id' => 42, 'is_cat' => false, 'first_id' => 1867],
            $this->outputHeadlines(1)->getPayload()['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with skip and ascending order
        $test = $this->req($in[9]);
        $exp = $this->respGood([
            ['id' => 42, 'is_cat' => false, 'first_id' => 0],
            $this->outputHeadlines(1)->getPayload()['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'show_excerpt'
        $exp1 = "“This & that, you know‽”";
        $exp2 = "Pour vous faire mieux connaitre d’ou\u{300} vient l’erreur de ceux qui bla\u{302}ment la volupte\u{301}, et qui louent en…";
        $test = $this->req($in[10]);
        $this->assertArrayHasKey("excerpt", $test->getPayload()['content'][0]);
        $this->assertArrayHasKey("excerpt", $test->getPayload()['content'][1]);
        $this->assertSame($exp1, $test->getPayload()['content'][0]['excerpt']);
        $this->assertSame($exp2, $test->getPayload()['content'][1]['excerpt']);
    }

    protected function generateHeadlines(int $id): Result {
        return new Result($this->v([
            [
                'id' => $id,
                'url' => 'http://example.com/1',
                'title' => 'Article title 1',
                'subscription_title' => "Feed 2112",
                'author' => '',
                'content' => '<p>&ldquo;This &amp; that, you know&#8253;&rdquo;</p>',
                'guid' => null,
                'published_date' => '2000-01-01 00:00:00',
                'edited_date' => '2000-01-01 00:00:00',
                'modified_date' => '2000-01-01 01:00:00',
                'unread' => 0,
                'starred' => 0,
                'edition' => 101,
                'subscription' => 12,
                'fingerprint' => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
                'media_url' => null,
                'media_type' => null,
                'note' => "",
            ],
            [
                'id' => 2112,
                'url' => 'http://example.com/2',
                'title' => 'Article title 2',
                'subscription_title' => "Feed 11",
                'author' => 'J. King',
                'content' => $this->richContent,
                'guid' => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'published_date' => '2000-01-02 00:00:00',
                'edited_date' => '2000-01-02 00:00:02',
                'modified_date' => '2000-01-02 02:00:00',
                'unread' => 1,
                'starred' => 1,
                'edition' => 202,
                'subscription' => 8,
                'fingerprint' => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
                'media_url' => "http://example.com/text",
                'media_type' => "text/plain",
                'note' => "Note 2",
            ],
        ]));
    }

    protected function outputHeadlines(int $id): Response {
        return $this->respGood([
            [
                'id' => $id,
                'guid' => '',
                'title' => 'Article title 1',
                'link' => 'http://example.com/1',
                'labels' => [],
                'unread' => false,
                'marked' => false,
                'published' => false,
                'author' => '',
                'updated' => strtotime('2000-01-01T00:00:00Z'),
                'is_updated' => false,
                'feed_id' => "12",
                'feed_title' => "Feed 2112",
                'score' => 0,
                'note' => null,
                'lang' => "",
                'tags' => [],
                'comments_count' => 0,
                'comments_link' => "",
                'always_display_attachments' => false,
            ],
            [
                'id' => 2112,
                'guid' => "SHA256:5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7",
                'title' => 'Article title 2',
                'link' => 'http://example.com/2',
                'labels' => [
                    [-1025, "Logical", "", ""],
                    [-1027, "Fascinating", "", ""],
                ],
                'unread' => true,
                'marked' => true,
                'published' => false,
                'author' => "J. King",
                'updated' => strtotime('2000-01-02T00:00:02Z'),
                'is_updated' => true,
                'feed_id' => "8",
                'feed_title' => "Feed 11",
                'score' => 0,
                'note' => "Note 2",
                'lang' => "",
                'tags' => ["Boring", "Illogical"],
                'comments_count' => 0,
                'comments_link' => "",
                'always_display_attachments' => false,
            ],
        ]);
    }
}
