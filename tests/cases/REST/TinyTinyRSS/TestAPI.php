<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\TinyTinyRSS\API;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\TinyTinyRSS\API::class)]
#[CoversClass(\JKingWeb\Arsse\REST\TinyTinyRSS\Exception::class)]
class TestAPI extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const NOW = "2020-12-21T23:09:17.189065Z";

    protected $h;
    protected static $userId = "john.doe@example.com";
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
        ['id' => 3, 'folder' => 1,    'top_folder' => 1,    'unread' => 2,  'updated' => "2016-05-23 06:40:02", 'err_msg' => 'argh', 'title' => 'Ars Technica',   'url' => " http://example.com/3", 'icon_url' => 'http://example.com/3.png'],
        ['id' => 4, 'folder' => 6,    'top_folder' => 3,    'unread' => 6,  'updated' => "2017-10-09 15:58:34", 'err_msg' => '',     'title' => 'CBC News',       'url' => " http://example.com/4", 'icon_url' => 'http://example.com/4.png'],
        ['id' => 6, 'folder' => null, 'top_folder' => null, 'unread' => 0,  'updated' => "2010-02-12 20:08:47", 'err_msg' => '',     'title' => 'Eurogamer',      'url' => " http://example.com/6", 'icon_url' => 'http://example.com/6.png'],
        ['id' => 1, 'folder' => 2,    'top_folder' => 1,    'unread' => 5,  'updated' => "2017-09-15 22:54:16", 'err_msg' => '',     'title' => 'NASA JPL',       'url' => " http://example.com/1", 'icon_url' => null],
        ['id' => 5, 'folder' => 6,    'top_folder' => 3,    'unread' => 12, 'updated' => "2017-07-07 17:07:17", 'err_msg' => '',     'title' => 'Ottawa Citizen', 'url' => " http://example.com/5", 'icon_url' => ''],
        ['id' => 2, 'folder' => 5,    'top_folder' => 3,    'unread' => 10, 'updated' => "2011-11-11 11:11:11", 'err_msg' => 'oops', 'title' => 'Toronto Star',   'url' => " http://example.com/2", 'icon_url' => 'http://example.com/2.png'],
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
            'id'                 => 101,
            'url'                => 'http://example.com/1',
            'title'              => 'Article title 1',
            'subscription_title' => "Feed 11",
            'author'             => '',
            'content'            => '<p>Article content 1</p>',
            'guid'               => '',
            'published_date'     => '2000-01-01 00:00:00',
            'edited_date'        => '2000-01-01 00:00:01',
            'modified_date'      => '2000-01-01 01:00:00',
            'unread'             => 1,
            'starred'            => 0,
            'edition'            => 101,
            'subscription'       => 8,
            'fingerprint'        => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
            'media_url'          => null,
            'media_type'         => null,
            'note'               => "",
        ],
        [
            'id'                 => 102,
            'url'                => 'http://example.com/2',
            'title'              => 'Article title 2',
            'subscription_title' => "Feed 11",
            'author'             => 'J. King',
            'content'            => '<p>Article content 2</p>',
            'guid'               => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
            'published_date'     => '2000-01-02 00:00:00',
            'edited_date'        => '2000-01-02 00:00:02',
            'modified_date'      => '2000-01-02 02:00:00',
            'unread'             => 0,
            'starred'            => 0,
            'edition'            => 202,
            'subscription'       => 8,
            'fingerprint'        => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
            'media_url'          => "http://example.com/text",
            'media_type'         => "text/plain",
            'note'               => "Note 2",
        ],
    ];
    // text from https://corrigeur.fr/lorem-ipsum-traduction-origine.php
    protected static $richContent = <<<LONG_STRING
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

    protected static function v($value) {
        return $value;
    }

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create mock timestamps
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        // create a mock user manager
        self::$userId = "john.doe@example.com";
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->sessionResume->thenThrow(new \JKingWeb\Arsse\User\ExceptionSession("invalid"));
        \Phake::when(Arsse::$db)->sessionResume("PriestsOfSyrinx")->thenReturn([
            'id'      => "PriestsOfSyrinx",
            'created' => "2000-01-01 00:00:00",
            'expires' => "2112-12-21 21:12:00",
            'user'    => self::$userId,
        ]);
        $this->h = new API;
    }

    protected function req($data, string $method = "POST", string $target = "", ?string $strData = null, ?string $user = null): ResponseInterface {
        Arsse::$user->id = self::$userId;
        $prefix = "/tt-rss/api";
        $url = $prefix.$target;
        $body = $strData ?? json_encode($data);
        $req = $this->serverRequest($method, $url, $prefix, [], ['HTTP_CONTENT_TYPE' => "application/x-www-form-urlencoded"], $body, "application/json", [], $user);
        return $this->h->dispatch($req);
    }

    protected function reqAuth($data, $user): ResponseInterface {
        return $this->req($data, "POST", "", null, $user);
    }

    protected static function respGood($content = null, $seq = 0): ResponseInterface {
        return HTTP::respJson([
            'seq'     => $seq,
            'status'  => 0,
            'content' => $content,
        ]);
    }

    protected static function respErr(string $msg, $content = [], $seq = 0): ResponseInterface {
        $err = ['error' => $msg];
        return HTTP::respJson([
            'seq'     => $seq,
            'status'  => 1,
            'content' => array_merge($err, $content, $err),
        ]);
    }

    public function testHandleInvalidPaths(): void {
        $exp = self::respErr("MALFORMED_INPUT", [], null);
        $this->assertMessage($exp, $this->req(null, "POST", "", ""));
        $this->assertMessage($exp, $this->req(null, "POST", "/", ""));
        $this->assertMessage($exp, $this->req(null, "POST", "/index.php", ""));
        $exp = HTTP::respEmpty(404);
        $this->assertMessage($exp, $this->req(null, "POST", "/bad/path", ""));
    }

    public function testHandleOptionsRequest(): void {
        $exp = HTTP::challenge(HTTP::respEmpty(204, [
            'Allow'  => "POST",
            'Accept' => "application/json, text/json",
        ]));
        $this->assertMessage($exp, $this->req(null, "OPTIONS", "", ""));
    }

    public function testHandleInvalidData(): void {
        $exp = self::respErr("MALFORMED_INPUT", [], null);
        $this->assertMessage($exp, $this->req(null, "POST", "", "This is not valid JSON data"));
        $this->assertMessage($exp, $this->req(null, "POST", "", "")); // lack of data is also an error
    }

    #[DataProvider("provideLoginRequests")]
    public function testLogIn(array $conf, $httpUser, array $data, $sessions): void {
        self::$userId = null;
        self::setConf($conf);
        \Phake::when(Arsse::$user)->auth->thenReturn(false);
        \Phake::when(Arsse::$user)->auth("john.doe@example.com", "secret")->thenReturn(true);
        \Phake::when(Arsse::$user)->auth("jane.doe@example.com", "superman")->thenReturn(true);
        \Phake::when(Arsse::$db)->sessionCreate("john.doe@example.com")->thenReturn("PriestsOfSyrinx")->thenReturn("SolarFederation");
        \Phake::when(Arsse::$db)->sessionCreate("jane.doe@example.com")->thenReturn("ClockworkAngels")->thenReturn("SevenCitiesOfGold");
        if ($sessions instanceof ResponseInterface) {
            $exp1 = $sessions;
            $exp2 = $sessions;
        } elseif ($sessions) {
            $exp1 = self::respGood(['session_id' => $sessions[0], 'api_level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
            $exp2 = self::respGood(['session_id' => $sessions[1], 'api_level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        } else {
            $exp1 = self::respErr("LOGIN_ERROR");
            $exp2 = self::respErr("LOGIN_ERROR");
        }
        $data['op'] = "login";
        $this->assertMessage($exp1, $this->reqAuth($data, $httpUser));
        // base64 passwords are also accepted
        if (isset($data['password'])) {
            $data['password'] = base64_encode($data['password']);
        }
        $this->assertMessage($exp2, $this->reqAuth($data, $httpUser));
        // logging in should never try to resume a session
        \Phake::verify(Arsse::$db, \Phake::never())->sessionResume(\Phake::anyParameters());
    }

    public static function provideLoginRequests(): iterable {
        return self::generateLoginRequests("login");
    }

    #[DataProvider("provideResumeRequests")]
    public function testValidateASession(array $conf, $httpUser, string $data, $result): void {
        self::$userId = null;
        self::setConf($conf);
        \Phake::when(Arsse::$db)->sessionResume("PriestsOfSyrinx")->thenReturn([
            'id'      => "PriestsOfSyrinx",
            'created' => "2000-01-01 00:00:00",
            'expires' => "2112-12-21 21:12:00",
            'user'    => "john.doe@example.com",
        ]);
        \Phake::when(Arsse::$db)->sessionResume("ClockworkAngels")->thenReturn([
            'id'      => "ClockworkAngels",
            'created' => "2000-01-01 00:00:00",
            'expires' => "2112-12-21 21:12:00",
            'user'    => "jane.doe@example.com",
        ]);
        $data = [
            'op'       => "isLoggedIn",
            'sid'      => $data,
        ];
        if ($result instanceof ResponseInterface) {
            $exp1 = $result;
            $exp2 = null;
        } elseif ($result) {
            $exp1 = self::respGood(['status' => true]);
            $exp2 = $result;
        } else {
            $exp1 = self::respErr("NOT_LOGGED_IN");
            $exp2 = ($httpUser) ? $httpUser : null;
        }
        $this->assertMessage($exp1, $this->reqAuth($data, $httpUser));
        $this->assertSame($exp2, Arsse::$user->id);
    }

    public static function provideResumeRequests(): iterable {
        return self::generateLoginRequests("isLoggedIn");
    }

    public static function generateLoginRequests(string $type): array {
        $john = "john.doe@example.com";
        $johnGood = [
            'user'     => $john,
            'password' => "secret",
        ];
        $johnBad = [
            'user'     => $john,
            'password' => "superman",
        ];
        $johnSess = ["PriestsOfSyrinx", "SolarFederation"];
        $jane = "jane.doe@example.com";
        $janeGood = [
            'user'     => $jane,
            'password' => "superman",
        ];
        $janeBad = [
            'user'     => $jane,
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
            'userPreAuth'          => false,
            'userHTTPAuthRequired' => false,
            'userSessionEnforced'  => true,
        ];
        $preAuth = [
            'userPreAuth'          => true,
            'userHTTPAuthRequired' => false, // implied true by pre-auth
            'userSessionEnforced'  => true,
        ];
        $httpReq = [
            'userPreAuth'          => false,
            'userHTTPAuthRequired' => true,
            'userSessionEnforced'  => true,
        ];
        $noSess = [
            'userPreAuth'          => false,
            'userHTTPAuthRequired' => false,
            'userSessionEnforced'  => false,
        ];
        $fullHttp = [
            'userPreAuth'          => false,
            'userHTTPAuthRequired' => true,
            'userSessionEnforced'  => false,
        ];
        $http401 = HTTP::respEmpty(401);
        if ($type === "login") {
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
        } elseif ($type === "isLoggedIn") {
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

    public function testHandleGenericError(): void {
        \Phake::when(Arsse::$user)->auth->thenThrow(new \JKingWeb\Arsse\Db\ExceptionTimeout("general"));
        $data = [
            'op'       => "login",
            'user'     => self::$userId,
            'password' => "secret",
        ];
        $exp = HTTP::respEmpty(500);
        $this->assertMessage($exp, $this->req($data));
    }

    public function testLogOut(): void {
        \Phake::when(Arsse::$db)->sessionDestroy->thenReturn(true);
        $data = [
            'op'       => "logout",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = self::respGood(['status' => "OK"]);
        $this->assertMessage($exp, $this->req($data));
        \Phake::verify(Arsse::$db)->sessionDestroy(self::$userId, "PriestsOfSyrinx");
    }

    public function testHandleUnknownMethods(): void {
        $exp = self::respErr("UNKNOWN_METHOD", ['method' => "thisMethodDoesNotExist"]);
        $data = [
            'op'       => "thisMethodDoesNotExist",
            'sid'      => "PriestsOfSyrinx",
        ];
        $this->assertMessage($exp, $this->req($data));
    }

    public function testHandleMixedCaseMethods(): void {
        $data = [
            'op'       => "isLoggedIn",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = self::respGood(['status' => true]);
        $this->assertMessage($exp, $this->req($data));
        $data['op'] = "isloggedin";
        $this->assertMessage($exp, $this->req($data));
        $data['op'] = "ISLOGGEDIN";
        $this->assertMessage($exp, $this->req($data));
        $data['op'] = "iSlOgGeDiN";
        $this->assertMessage($exp, $this->req($data));
    }

    public function testRetrieveServerVersion(): void {
        $data = [
            'op'       => "getVersion",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = self::respGood([
            'version'       => \JKingWeb\Arsse\REST\TinyTinyRSS\API::VERSION,
            'arsse_version' => Arsse::VERSION,
        ]);
        $this->assertMessage($exp, $this->req($data));
    }

    public function testRetrieveProtocolLevel(): void {
        $data = [
            'op'       => "getApiLevel",
            'sid'      => "PriestsOfSyrinx",
        ];
        $exp = self::respGood(['level' => \JKingWeb\Arsse\REST\TinyTinyRSS\API::LEVEL]);
        $this->assertMessage($exp, $this->req($data));
    }

    #[DataProvider("provideCategoryAdditions")]
    public function testAddACategory(array $in, array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "addCategory", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->folderAdd->$action($out);
        \Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result(self::v([
            ['id' => 2, 'name' => "Software", 'parent' => null],
            ['id' => 1, 'name' => "Politics", 'parent' => null],
        ])));
        \Phake::when(Arsse::$db)->folderList($this->anything(), 1, false)->thenReturn(new Result(self::v([
            ['id' => 3, 'name' => "Hardware", 'parent' => 1],
        ])));
        $this->assertMessage($exp, $this->req($in));
        \Phake::verify(Arsse::$db)->folderAdd(self::$userId, $data);
        if (!$out instanceof \Exception) {
            \Phake::verify(Arsse::$db, \Phake::never())->folderList(\Phake::anyParameters());
        }
    }

    public static function provideCategoryAdditions(): iterable {
        return [
            [[],                                             ['name' => null,       'parent' => null], new ExceptionInput("missing"),             self::respErr("INCORRECT_USAGE")],
            [['caption' => ""],                              ['name' => "",         'parent' => null], new ExceptionInput("missing"),             self::respErr("INCORRECT_USAGE")],
            [['caption' => "   "],                           ['name' => "   ",      'parent' => null], new ExceptionInput("whitespace"),          self::respErr("INCORRECT_USAGE")],
            [['caption' => "Software"],                      ['name' => "Software", 'parent' => null], 2,                                         self::respGood("2")],
            [['caption' => "Hardware", 'parent_id' => 1],    ['name' => "Hardware", 'parent' => 1],    3,                                         self::respGood("3")],
            [['caption' => "Hardware", 'parent_id' => 2112], ['name' => "Hardware", 'parent' => 2112], new ExceptionInput("idMissing"),           self::respGood(false)],
            [['caption' => "Software"],                      ['name' => "Software", 'parent' => null], new ExceptionInput("constraintViolation"), self::respGood("2")],
            [['caption' => "Hardware", 'parent_id' => 1],    ['name' => "Hardware", 'parent' => 1],    new ExceptionInput("constraintViolation"), self::respGood("3")],
        ];
    }

    #[DataProvider("provideCategoryRemovals")]
    public function testRemoveACategory(array $in, ?int $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "removeCategory", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->folderRemove->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($data > 0) {
            \Phake::verify(Arsse::$db)->folderRemove(self::$userId, (int) $data);
        }
    }

    public static function provideCategoryRemovals(): iterable {
        return [
            [['category_id' => 42],   42,   true,                                 self::respGood()],
            [['category_id' => 2112], 2112, new ExceptionInput("subjectMissing"), self::respGood()],
            [[],                      null, null,                                 self::respErr("INCORRECT_USAGE")],
            [['category_id' => -1],   null, null,                                 self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideCategoryMoves")]
    public function testMoveACategory(array $in, array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "moveCategory", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->folderPropertiesSet->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->folderPropertiesSet(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->folderPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideCategoryMoves(): iterable {
        return [
            [['category_id' => 42,   'parent_id' => 1],  [self::$userId, 42,   ['parent' => 1]],  true,                                      self::respGood()],
            [['category_id' => 2112, 'parent_id' => 2],  [self::$userId, 2112, ['parent' => 2]],  new ExceptionInput("subjectMissing"),      self::respGood()],
            [['category_id' => 42,   'parent_id' => 0],  [self::$userId, 42,   ['parent' => 0]],  new ExceptionInput("constraintViolation"), self::respGood()],
            [['category_id' => 42,   'parent_id' => 47], [self::$userId, 42,   ['parent' => 47]], new ExceptionInput("idMissing"),           self::respGood()],
            [['category_id' => -1,   'parent_id' => 1],  [self::$userId, -1,   ['parent' => 1]],  null,                                      self::respErr("INCORRECT_USAGE")],
            [['category_id' => 42,   'parent_id' => -1], [self::$userId, 42,   ['parent' => -1]], null,                                      self::respErr("INCORRECT_USAGE")],
            [['category_id' => 42],                      [self::$userId, 42,   ['parent' => 0]],  new ExceptionInput("constraintViolation"), self::respGood()],
            [['parent_id' => -1],                        [self::$userId, 0,    ['parent' => -1]], null,                                      self::respErr("INCORRECT_USAGE")],
            [[],                                         [self::$userId, 0,    ['parent' => 0]],  null,                                      self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideCategoryRenamings")]
    public function testRenameACategory(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "renameCategory", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->folderPropertiesSet->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->folderPropertiesSet(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->folderPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideCategoryRenamings(): iterable {
        return [
            [['category_id' => 42,   'caption' => "Ook"], [self::$userId, 42,   ['name' => "Ook"]], true,                                      self::respGood()],
            [['category_id' => 2112, 'caption' => "Eek"], [self::$userId, 2112, ['name' => "Eek"]], new ExceptionInput("subjectMissing"),      self::respGood()],
            [['category_id' => 42,   'caption' => "Eek"], [self::$userId, 42,   ['name' => "Eek"]], new ExceptionInput("constraintViolation"), self::respGood()],
            [['category_id' => 42,   'caption' => ""],    null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [['category_id' => 42,   'caption' => " "],   null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [['category_id' => -1,   'caption' => "Ook"], null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [['category_id' => 42],                       null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [['caption' => "Ook"],                        null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [[],                                          null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideFeedSubscriptions")]
    public function testAddASubscription(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "subscribeToFeed", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        $list = [
            ['id' => 1, 'url' => "http://localhost:8000/Feed/Discovery/Feed"],
            ['id' => 2, 'url' => "http://example.com/0"],
            ['id' => 3, 'url' => "http://example.com/3"],
            ['id' => 4, 'url' => "http://example.com/9"],
        ];
        \Phake::when(Arsse::$db)->subscriptionAdd->$action($out);
        \Phake::when(Arsse::$db)->folderPropertiesGet(self::$userId, 42)->thenReturn(self::v(['id' => 42]));
        \Phake::when(Arsse::$db)->folderPropertiesGet(self::$userId, 47)->thenReturn(self::v(['id' => 47]));
        \Phake::when(Arsse::$db)->folderPropertiesGet(self::$userId, 2112)->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet(self::$userId, $this->anything())->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet(self::$userId, 4, $this->anything())->thenThrow(new ExceptionInput("idMissing"));
        \Phake::when(Arsse::$db)->subscriptionList(self::$userId)->thenReturn(new Result(self::v($list)));
        $this->assertMessage($exp, $this->req($in));
        if ($data !== null) {
            \Phake::verify(Arsse::$db)->subscriptionAdd(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionAdd(\Phake::anyParameters());
        }
        \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesSet(self::$userId, 4, ['folder' => 1]);
    }

    public static function provideFeedSubscriptions(): iterable {
        return [
            [['feed_url' => "http://example.com/0"],                                 [self::$userId, "http://example.com/0", true, ['username' => null, 'password' => null]],                         2,                                         self::respGood(['code' => 1, 'feed_id' => 2])],
            [['feed_url' => "http://example.com/1", 'category_id' => 42],            [self::$userId, "http://example.com/1", true, ['username' => null, 'password' => null]],                         new FeedException("unauthorized"),         self::respGood(['code' => 5, 'message' => (new FeedException("unauthorized"))->getMessage()])],
            [['feed_url' => "http://example.com/2", 'category_id' => 2112],          null,                                                                                                            null,                                      self::respGood(['code' => 1, 'feed_id' => 0])],
            [['feed_url' => "http://example.com/3"],                                 [self::$userId, "http://example.com/3", true, ['username' => null, 'password' => null]],                         new ExceptionInput("constraintViolation"), self::respGood(['code' => 0, 'feed_id' => 3])],
            [['feed_url' => "http://localhost:8000/Feed/Discovery/Valid"],           [self::$userId, "http://localhost:8000/Feed/Discovery/Valid", true, ['username' => null, 'password' => null]],   new ExceptionInput("constraintViolation"), self::respGood(['code' => 0, 'feed_id' => 1])],
            [['feed_url' => "http://localhost:8000/Feed/Discovery/Invalid"],         [self::$userId, "http://localhost:8000/Feed/Discovery/Invalid", true, ['username' => null, 'password' => null]], new ExceptionInput("constraintViolation"), self::respGood(['code' => 3, 'message' => (new FeedException("subscriptionNotFound", ['url' => "http://localhost:8000/Feed/Discovery/Invalid"]))->getMessage()])],
            [['feed_url' => "http://example.com/6"],                                 [self::$userId, "http://example.com/6", true, ['username' => null, 'password' => null]],                         new FeedException("invalidUrl"),           self::respGood(['code' => 2, 'message' => (new FeedException("invalidUrl"))->getMessage()])],
            [['feed_url' => "http://example.com/7"],                                 [self::$userId, "http://example.com/7", true, ['username' => null, 'password' => null]],                         new FeedException("malformedXml"),         self::respGood(['code' => 6, 'message' => (new FeedException("malformedXml"))->getMessage()])],
            [['feed_url' => "http://example.com/8", 'category_id' => 47],            [self::$userId, "http://example.com/8", true, ['username' => null, 'password' => null]],                         4,                                         self::respGood(['code' => 1, 'feed_id' => 4])],
            [['feed_url' => "http://example.com/9", 'category_id' => 1],             [self::$userId, "http://example.com/9", true, ['username' => null, 'password' => null]],                         new ExceptionInput("constraintViolation"), self::respGood(['code' => 0, 'feed_id' => 4])],
            [[],                                                                     null,                                                                                                            null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_url' => "http://example.com/", 'login' => []],                   null,                                                                                                            null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_url' => "http://example.com/", 'login' => "", 'password' => []], null,                                                                                                            null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_url' => "http://example.com/", 'category_id' => -1],             null,                                                                                                            null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_url' => "relative"],                                             [self::$userId, "relative", true, ['username' => null, 'password' => null]],                                     new ExceptionInput("invalidValue"),        self::respGood(['code' => 2, 'message' => (new ExceptionInput("invalidValue"))->getMessage()])],
        ];
    }

    #[DataProvider("provideFeedUnsubscriptions")]
    public function testRemoveASubscription(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "unsubscribeFeed", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->subscriptionRemove->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->subscriptionRemove(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionRemove(\Phake::anyParameters());
        }
    }

    public static function provideFeedUnsubscriptions(): iterable {
        return [
            [['feed_id' => 42],   [self::$userId, 42],   true,                                 self::respGood(['status' => "OK"])],
            [['feed_id' => 2112], [self::$userId, 2112], new ExceptionInput("subjectMissing"), self::respErr("FEED_NOT_FOUND")],
            [['feed_id' => -1],   [self::$userId, -1],   new ExceptionInput("typeViolation"),  self::respErr("FEED_NOT_FOUND")],
            [[],                  [self::$userId, 0],    new ExceptionInput("typeViolation"),  self::respErr("FEED_NOT_FOUND")],
        ];
    }

    #[DataProvider("provideFeedMoves")]
    public function testMoveAFeed(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "moveFeed", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesSet(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideFeedMoves(): iterable {
        return [
            [['feed_id' => 42,   'category_id' => 1],  [self::$userId, 42,   ['folder' => 1]],  true,                                      self::respGood()],
            [['feed_id' => 2112, 'category_id' => 2],  [self::$userId, 2112, ['folder' => 2]],  new ExceptionInput("subjectMissing"),      self::respGood()],
            [['feed_id' => 42,   'category_id' => 0],  [self::$userId, 42,   ['folder' => 0]],  new ExceptionInput("constraintViolation"), self::respGood()],
            [['feed_id' => 42,   'category_id' => 47], [self::$userId, 42,   ['folder' => 47]], new ExceptionInput("constraintViolation"), self::respGood()],
            [['feed_id' => -1,   'category_id' => 1],  null,                                    null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_id' => 42,   'category_id' => -1], null,                                    null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_id' => 42],                        null,                                    null,                                      self::respErr("INCORRECT_USAGE")],
            [['category_id' => -1],                    null,                                    null,                                      self::respErr("INCORRECT_USAGE")],
            [[],                                       null,                                    null,                                      self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideFeedRenamings")]
    public function testRenameAFeed(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "renameFeed", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesSet(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideFeedRenamings(): iterable {
        return [
            [['feed_id' => 42,   'caption' => "Ook"], [self::$userId, 42,   ['title' => "Ook"]], true,                                      self::respGood()],
            [['feed_id' => 2112, 'caption' => "Eek"], [self::$userId, 2112, ['title' => "Eek"]], new ExceptionInput("subjectMissing"),      self::respGood()],
            [['feed_id' => 42,   'caption' => "Eek"], [self::$userId, 42,   ['title' => "Eek"]], new ExceptionInput("constraintViolation"), self::respGood()],
            [['feed_id' => 42,   'caption' => ""],    null,                                      null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_id' => 42,   'caption' => " "],   null,                                      null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_id' => -1,   'caption' => "Ook"], null,                                      null,                                      self::respErr("INCORRECT_USAGE")],
            [['feed_id' => 42],                       null,                                      null,                                      self::respErr("INCORRECT_USAGE")],
            [['caption' => "Ook"],                    null,                                      null,                                      self::respErr("INCORRECT_USAGE")],
            [[],                                      null,                                      null,                                      self::respErr("INCORRECT_USAGE")],
        ];
    }

    public function testRetrieveTheGlobalUnreadCount(): void {
        $in = ['op' => "getUnread", 'sid' => "PriestsOfSyrinx"];
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v([
            ['id' => 1, 'unread' => 2112],
            ['id' => 2, 'unread' => 42],
            ['id' => 3, 'unread' => 47],
        ])));
        $exp = self::respGood(['unread' => (string) (2112 + 42 + 47)]);
        $this->assertMessage($exp, $this->req($in));
    }

    public function testRetrieveTheServerConfiguration(): void {
        $in = ['op' => "getConfig", 'sid' => "PriestsOfSyrinx"];
        $interval = Arsse::$conf->serviceFrequency;
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        \Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        \Phake::when(Arsse::$db)->subscriptionCount(self::$userId)->thenReturn(12)->thenReturn(2);
        $this->assertMessage(self::respGood(['icons_dir' => "feed-icons", 'icons_url' => "feed-icons", 'daemon_is_running' => true, 'num_feeds' => 12]), $this->req($in));
        $this->assertMessage(self::respGood(['icons_dir' => "feed-icons", 'icons_url' => "feed-icons", 'daemon_is_running' => false, 'num_feeds' => 2]), $this->req($in));
    }

    #[DataProvider("provideFeedUpdates")]
    public function testUpdateAFeed(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "updateFeed", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->subscriptionUpdate->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($data !== null) {
            \Phake::verify(Arsse::$db)->subscriptionUpdate(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionUpdate(\Phake::anyParameters());
        }
    }

    public static function provideFeedUpdates(): iterable {
        return [
            [['feed_id' => 1],  [self::$userId, 1], true,                                 self::respGood(['status' => "OK"])],
            [['feed_id' => 2],  [self::$userId, 2], new ExceptionInput("subjectMissing"), self::respErr("FEED_NOT_FOUND")],
            [['feed_id' => -1], null,               null,                                 self::respErr("INCORRECT_USAGE")],
            [[],                null,               null,                                 self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideLabelAdditions")]
    public function testAddALabel(array $in, ?array $data1, $out1, ?array $data2, $out2, ResponseInterface $exp): void {
        $in = array_merge(['op' => "addLabel", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out1 instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->labelAdd->$action($out1);
        \Phake::when(Arsse::$db)->labelPropertiesGet->thenReturn($out2);
        $this->assertMessage($exp, $this->req($in));
        if ($out1 !== null) {
            \Phake::verify(Arsse::$db)->labelAdd(...$data1);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->labelAdd(\Phake::anyParameters());
        }
        if ($out2 !== null) {
            \Phake::verify(Arsse::$db)->labelPropertiesGet(...$data2);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->labelPropertiesGet(\Phake::anyParameters());
        }
    }

    public static function provideLabelAdditions(): iterable {
        return [
            [['caption' => "Software"], [self::$userId, ['name' => "Software"]], 2,                                         null,                              null,        self::respGood(-1026)],
            [['caption' => "Hardware"], [self::$userId, ['name' => "Hardware"]], 3,                                         null,                              null,        self::respGood(-1027)],
            [['caption' => "Software"], [self::$userId, ['name' => "Software"]], new ExceptionInput("constraintViolation"), [self::$userId, "Software", true], ['id' => 2], self::respGood(-1026)],
            [['caption' => "Hardware"], [self::$userId, ['name' => "Hardware"]], new ExceptionInput("constraintViolation"), [self::$userId, "Hardware", true], ['id' => 3], self::respGood(-1027)],
            [[],                        [self::$userId, ['name' => ""]],         new ExceptionInput("typeViolation"),       null,                              null,        self::respErr("INCORRECT_USAGE")],
            [['caption' => ""],         [self::$userId, ['name' => ""]],         new ExceptionInput("typeViolation"),       null,                              null,        self::respErr("INCORRECT_USAGE")],
            [['caption' => "   "],      [self::$userId, ['name' => "   "]],      new ExceptionInput("typeViolation"),       null,                              null,        self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideLabelRemovals")]
    public function testRemoveALabel(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "removeLabel", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->labelRemove->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->labelRemove(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->labelRemove(\Phake::anyParameters());
        }
    }

    public static function provideLabelRemovals(): iterable {
        return [
            [['label_id' => -1042], [self::$userId, 18],   true,                                 self::respGood()],
            [['label_id' => -2112], [self::$userId, 1088], new ExceptionInput("subjectMissing"), self::respGood()],
            [['label_id' => 1],     null,                  null,                                 self::respErr("INCORRECT_USAGE")],
            [['label_id' => 0],     null,                  null,                                 self::respErr("INCORRECT_USAGE")],
            [['label_id' => -10],   null,                  null,                                 self::respErr("INCORRECT_USAGE")],
            [[],                    null,                  null,                                 self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideLabelRenamings")]
    public function testRenameALabel(array $in, ?array $data, $out, ResponseInterface $exp): void {
        $in = array_merge(['op' => "renameLabel", 'sid' => "PriestsOfSyrinx"], $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$db)->labelPropertiesSet->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out !== null) {
            \Phake::verify(Arsse::$db)->labelPropertiesSet(...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->labelPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideLabelRenamings(): iterable {
        return [
            [['label_id' => -1042, 'caption' => "Ook"], [self::$userId, 18,   ['name' => "Ook"]], true,                                      self::respGood()],
            [['label_id' => -2112, 'caption' => "Eek"], [self::$userId, 1088, ['name' => "Eek"]], new ExceptionInput("subjectMissing"),      self::respGood()],
            [['label_id' => -1042, 'caption' => "Eek"], [self::$userId, 18,   ['name' => "Eek"]], new ExceptionInput("constraintViolation"), self::respGood()],
            [['label_id' => -1042, 'caption' => ""],    [self::$userId, 18,   ['name' => ""]],    new ExceptionInput("missing"),             self::respGood()],
            [['label_id' => -1042, 'caption' => " "],   [self::$userId, 18,   ['name' => " "]],   new ExceptionInput("whitespace"),          self::respGood()],
            [['label_id' => -1042],                     [self::$userId, 18,   ['name' => ""]],    new ExceptionInput("missing"),             self::respGood()],
            [['label_id' => -1042],                     [self::$userId, 18,   ['name' => ""]],    new ExceptionInput("typeViolation"),       self::respErr("INCORRECT_USAGE")],
            [['label_id' => -1,    'caption' => "Ook"], null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [['caption' => "Ook"],                      null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
            [[],                                        null,                                     null,                                      self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideCategoryListings")]
    public function testRetrieveCategoryLists(array $in, ResponseInterface $exp): void {
        $in = array_merge(['op' => "getCategories", 'sid' => "PriestsOfSyrinx"], $in);
        \Phake::when(Arsse::$db)->folderList($this->anything(), null, true)->thenReturn(new Result(self::v($this->folders)));
        \Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result(self::v($this->topFolders)));
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v($this->subscriptions)));
        \Phake::when(Arsse::$db)->labelList->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->articleCount($this->anything(), $this->equalTo((new Context)->hidden(false)->unread(true)->modifiedRange(Date::sub("PT24H", self::NOW), null)))->thenReturn(7);
        \Phake::when(Arsse::$db)->articleStarred->thenReturn(self::v($this->starred));
        $this->assertMessage($exp, $this->req($in));
    }

    public static function provideCategoryListings(): iterable {
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
        return [
            [['include_empty' => true],                          self::respGood($exp[0])],
            [[],                                                 self::respGood($exp[1])],
            [['unread_only' => true],                            self::respGood($exp[2])],
            [['enable_nested' => true, 'include_empty' => true], self::respGood($exp[3])],
            [['enable_nested' => true],                          self::respGood($exp[4])],
            [['enable_nested' => true, 'unread_only' => true],   self::respGood($exp[5])],
        ];
    }

    public function testRetrieveCounterList(): void {
        $in = ['op' => "getCounters", 'sid' => "PriestsOfSyrinx"];
        \Phake::when(Arsse::$db)->folderList->thenReturn(new Result(self::v($this->folders)));
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v($this->subscriptions)));
        \Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result(self::v($this->usedLabels)));
        \Phake::when(Arsse::$db)->articleCount->thenReturn(7);
        \Phake::when(Arsse::$db)->articleStarred->thenReturn(self::v($this->starred));
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
        $this->assertMessage(self::respGood($exp), $this->req($in));
        \Phake::verify(Arsse::$db)->articleCount(self::$userId, $this->equalTo((new Context)->hidden(false)->unread(true)->modifiedRange(Date::sub("PT24H", self::NOW), null)));
    }

    #[DataProvider("provideLabelListings")]
    public function testRetrieveTheLabelList(array $in, ResponseInterface $exp): void {
        $in = array_merge(['op' => "getLabels", 'sid' => "PriestsOfSyrinx"], $in);
        \Phake::when(Arsse::$db)->labelList->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 1)->thenReturn(self::v([1,3]));
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2)->thenReturn(self::v([3]));
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 3)->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 4)->thenThrow(new ExceptionInput("idMissing"));
        $this->assertMessage($exp, $this->req($in));
    }

    public static function provideLabelListings(): iterable {
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
        return [
            [[],                  self::respGood($exp[0])],
            [['article_id' => 1], self::respGood($exp[1])],
            [['article_id' => 2], self::respGood($exp[2])],
            [['article_id' => 3], self::respGood($exp[3])],
            [['article_id' => 4], self::respGood($exp[4])],
        ];
    }

    public static function provideLabelAssignments(): iterable {
        $ids = implode(",", range(1, 100));
        return [
            [['label_id' => -2112, 'article_ids' => $ids],                   1088, Database::ASSOC_REMOVE, self::respGood(['status' => "OK", 'updated' => 89])],
            [['label_id' => -2112, 'article_ids' => $ids, 'assign' => true], 1088, Database::ASSOC_ADD,    self::respGood(['status' => "OK", 'updated' => 7])],
            [['label_id' => -2112],                                          null, null,                   self::respGood(['status' => "OK", 'updated' => 0])],
            [['label_id' => -42],                                            null, null,                   self::respErr("INCORRECT_USAGE")],
            [['label_id' => 42],                                             null, null,                   self::respErr("INCORRECT_USAGE")],
            [['label_id' => 0],                                              null, null,                   self::respErr("INCORRECT_USAGE")],
            [[],                                                             null, null,                   self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideLabelAssignments")]
    public function testAssignArticlesToALabel(array $in, ?int $label, ?int $operation, ResponseInterface $exp): void {
        $in = array_merge(['op' => "setArticleLabel", 'sid' => "PriestsOfSyrinx"], $in);
        \Phake::when(Arsse::$db)->labelArticlesSet(self::$userId, $this->anything(), $this->anything(), Database::ASSOC_REMOVE)->thenReturn(42)->thenReturn(47);
        \Phake::when(Arsse::$db)->labelArticlesSet(self::$userId, $this->anything(), $this->anything(), Database::ASSOC_ADD)->thenReturn(5)->thenReturn(2);
        \Phake::when(Arsse::$db)->labelArticlesSet(self::$userId, $this->anything(), $this->equalTo((new Context)->articles([])), $this->anything())->thenThrow(new ExceptionInput("tooShort"));
        $this->assertMessage($exp, $this->req($in));
        if ($label !== null) {
            \Phake::verify(Arsse::$db)->labelArticlesSet(self::$userId, $label, $this->equalTo((new Context)->articles(range(1, 50))), $operation);
            \Phake::verify(Arsse::$db)->labelArticlesSet(self::$userId, $label, $this->equalTo((new Context)->articles(range(51, 100))), $operation);
        }
    }

    public function testRetrieveFeedTree(): void {
        $in = [
            ['op' => "getFeedTree", 'sid' => "PriestsOfSyrinx", 'include_empty' => true],
            ['op' => "getFeedTree", 'sid' => "PriestsOfSyrinx"],
        ];
        \Phake::when(Arsse::$db)->folderList($this->anything(), null, true)->thenReturn(new Result(self::v($this->folders)));
        \Phake::when(Arsse::$db)->subscriptionList->thenReturn(new Result(self::v($this->subscriptions)));
        \Phake::when(Arsse::$db)->labelList($this->anything(), true)->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->articleCount->thenReturn(7);
        \Phake::when(Arsse::$db)->articleStarred->thenReturn(self::v($this->starred));
        // the expectations are packed tightly since they're very verbose; one can use var_export() (or convert to JSON) to pretty-print them
        $exp = ['categories' => ['identifier' => 'id','label' => 'name','items' => [['name' => 'Special','id' => 'CAT:-1','bare_id' => -1,'type' => 'category','unread' => 0,'items' => [['name' => 'All articles','id' => 'FEED:-4','bare_id' => -4,'icon' => 'images/folder.png','unread' => 35,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Fresh articles','id' => 'FEED:-3','bare_id' => -3,'icon' => 'images/fresh.png','unread' => 7,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Starred articles','id' => 'FEED:-1','bare_id' => -1,'icon' => 'images/star.png','unread' => 4,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Published articles','id' => 'FEED:-2','bare_id' => -2,'icon' => 'images/feed.png','unread' => 0,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Archived articles','id' => 'FEED:0','bare_id' => 0,'icon' => 'images/archive.png','unread' => 0,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Recently read','id' => 'FEED:-6','bare_id' => -6,'icon' => 'images/time.png','unread' => 0,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => '']]],['name' => 'Labels','id' => 'CAT:-2','bare_id' => -2,'type' => 'category','unread' => 6,'items' => [['name' => 'Fascinating','id' => 'FEED:-1027','bare_id' => -1027,'unread' => 0,'icon' => 'images/label.png','type' => 'feed','auxcounter' => 0,'error' => '','updated' => '','fg_color' => '','bg_color' => ''],['name' => 'Interesting','id' => 'FEED:-1029','bare_id' => -1029,'unread' => 0,'icon' => 'images/label.png','type' => 'feed','auxcounter' => 0,'error' => '','updated' => '','fg_color' => '','bg_color' => ''],['name' => 'Logical','id' => 'FEED:-1025','bare_id' => -1025,'unread' => 0,'icon' => 'images/label.png','type' => 'feed','auxcounter' => 0,'error' => '','updated' => '','fg_color' => '','bg_color' => '']]],['name' => 'Photography','id' => 'CAT:4','bare_id' => 4,'parent_id' => null,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(0 feeds)','items' => []],['name' => 'Politics','id' => 'CAT:3','bare_id' => 3,'parent_id' => null,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(3 feeds)','items' => [['name' => 'Local','id' => 'CAT:5','bare_id' => 5,'parent_id' => 3,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(1 feed)','items' => [['name' => 'Toronto Star','id' => 'FEED:2','bare_id' => 2,'icon' => 'feed-icons/2.ico','error' => 'oops','param' => '2011-11-11T11:11:11Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]],['name' => 'National','id' => 'CAT:6','bare_id' => 6,'parent_id' => 3,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(2 feeds)','items' => [['name' => 'CBC News','id' => 'FEED:4','bare_id' => 4,'icon' => 'feed-icons/4.ico','error' => '','param' => '2017-10-09T15:58:34Z','unread' => 0,'auxcounter' => 0,'checkbox' => false],['name' => 'Ottawa Citizen','id' => 'FEED:5','bare_id' => 5,'icon' => false,'error' => '','param' => '2017-07-07T17:07:17Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]]]],['name' => 'Science','id' => 'CAT:1','bare_id' => 1,'parent_id' => null,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(2 feeds)','items' => [['name' => 'Rocketry','id' => 'CAT:2','bare_id' => 2,'parent_id' => 1,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(1 feed)','items' => [['name' => 'NASA JPL','id' => 'FEED:1','bare_id' => 1,'icon' => false,'error' => '','param' => '2017-09-15T22:54:16Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]],['name' => 'Ars Technica','id' => 'FEED:3','bare_id' => 3,'icon' => 'feed-icons/3.ico','error' => 'argh','param' => '2016-05-23T06:40:02Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]],['name' => 'Uncategorized','id' => 'CAT:0','bare_id' => 0,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'parent_id' => null,'param' => '(1 feed)','items' => [['name' => 'Eurogamer','id' => 'FEED:6','bare_id' => 6,'icon' => 'feed-icons/6.ico','error' => '','param' => '2010-02-12T20:08:47Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]]]]];
        $this->assertMessage(self::respGood($exp), $this->req($in[0]));
        $exp = ['categories' => ['identifier' => 'id','label' => 'name','items' => [['name' => 'Special','id' => 'CAT:-1','bare_id' => -1,'type' => 'category','unread' => 0,'items' => [['name' => 'All articles','id' => 'FEED:-4','bare_id' => -4,'icon' => 'images/folder.png','unread' => 35,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Fresh articles','id' => 'FEED:-3','bare_id' => -3,'icon' => 'images/fresh.png','unread' => 7,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Starred articles','id' => 'FEED:-1','bare_id' => -1,'icon' => 'images/star.png','unread' => 4,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Published articles','id' => 'FEED:-2','bare_id' => -2,'icon' => 'images/feed.png','unread' => 0,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Archived articles','id' => 'FEED:0','bare_id' => 0,'icon' => 'images/archive.png','unread' => 0,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => ''],['name' => 'Recently read','id' => 'FEED:-6','bare_id' => -6,'icon' => 'images/time.png','unread' => 0,'type' => 'feed','auxcounter' => 0,'error' => '','updated' => '']]],['name' => 'Labels','id' => 'CAT:-2','bare_id' => -2,'type' => 'category','unread' => 6,'items' => [['name' => 'Fascinating','id' => 'FEED:-1027','bare_id' => -1027,'unread' => 0,'icon' => 'images/label.png','type' => 'feed','auxcounter' => 0,'error' => '','updated' => '','fg_color' => '','bg_color' => ''],['name' => 'Interesting','id' => 'FEED:-1029','bare_id' => -1029,'unread' => 0,'icon' => 'images/label.png','type' => 'feed','auxcounter' => 0,'error' => '','updated' => '','fg_color' => '','bg_color' => ''],['name' => 'Logical','id' => 'FEED:-1025','bare_id' => -1025,'unread' => 0,'icon' => 'images/label.png','type' => 'feed','auxcounter' => 0,'error' => '','updated' => '','fg_color' => '','bg_color' => '']]],['name' => 'Politics','id' => 'CAT:3','bare_id' => 3,'parent_id' => null,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(3 feeds)','items' => [['name' => 'Local','id' => 'CAT:5','bare_id' => 5,'parent_id' => 3,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(1 feed)','items' => [['name' => 'Toronto Star','id' => 'FEED:2','bare_id' => 2,'icon' => 'feed-icons/2.ico','error' => 'oops','param' => '2011-11-11T11:11:11Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]],['name' => 'National','id' => 'CAT:6','bare_id' => 6,'parent_id' => 3,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(2 feeds)','items' => [['name' => 'CBC News','id' => 'FEED:4','bare_id' => 4,'icon' => 'feed-icons/4.ico','error' => '','param' => '2017-10-09T15:58:34Z','unread' => 0,'auxcounter' => 0,'checkbox' => false],['name' => 'Ottawa Citizen','id' => 'FEED:5','bare_id' => 5,'icon' => false,'error' => '','param' => '2017-07-07T17:07:17Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]]]],['name' => 'Science','id' => 'CAT:1','bare_id' => 1,'parent_id' => null,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(2 feeds)','items' => [['name' => 'Rocketry','id' => 'CAT:2','bare_id' => 2,'parent_id' => 1,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'param' => '(1 feed)','items' => [['name' => 'NASA JPL','id' => 'FEED:1','bare_id' => 1,'icon' => false,'error' => '','param' => '2017-09-15T22:54:16Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]],['name' => 'Ars Technica','id' => 'FEED:3','bare_id' => 3,'icon' => 'feed-icons/3.ico','error' => 'argh','param' => '2016-05-23T06:40:02Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]],['name' => 'Uncategorized','id' => 'CAT:0','bare_id' => 0,'type' => 'category','auxcounter' => 0,'unread' => 0,'child_unread' => 0,'checkbox' => false,'parent_id' => null,'param' => '(1 feed)','items' => [['name' => 'Eurogamer','id' => 'FEED:6','bare_id' => 6,'icon' => 'feed-icons/6.ico','error' => '','param' => '2010-02-12T20:08:47Z','unread' => 0,'auxcounter' => 0,'checkbox' => false]]]]]];
        $this->assertMessage(self::respGood($exp), $this->req($in[1]));
        \Phake::verify(Arsse::$db, \Phake::times(2))->articleCount(self::$userId, $this->equalTo((new Context)->hidden(false)->unread(true)->modifiedRange(Date::sub("PT24H", self::NOW), null)));
    }

    #[DataProvider("provideMassMarkings")]
    public function testMarkFeedsAsRead(array $in, ?Context $c): void {
        $base = ['op' => "catchupFeed", 'sid' => "PriestsOfSyrinx"];
        $in = array_merge($base, $in);
        \Phake::when(Arsse::$db)->articleMark->thenThrow(new ExceptionInput("typeViolation"));
        // create a mock-current time
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        // TT-RSS always responds the same regardless of success or failure
        $this->assertMessage(self::respGood(['status' => "OK"]), $this->req($in));
        if (isset($c)) {
            \Phake::verify(Arsse::$db)->articleMark(self::$userId, ['read' => true], $this->equalTo($c));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideMassMarkings(): iterable {
        $c = (new Context)->hidden(false);
        return [
            [[],                                                     null],
            [['feed_id' => 0],                                       null],
            [['feed_id' => 0, 'is_cat' => true],                     (clone $c)->folderShallow(0)],
            [['feed_id' => 0, 'is_cat' => true, 'mode' => "bogus"],  (clone $c)->folderShallow(0)],
            [['feed_id' => -1],                                      (clone $c)->starred(true)],
            [['feed_id' => -1, 'is_cat' => "t"],                     null],
            [['feed_id' => -3],                                      (clone $c)->modifiedRange(Date::sub("PT24H", self::NOW), null)],
            [['feed_id' => -3, 'mode' => "1day"],                    (clone $c)->modifiedRange(Date::sub("PT24H", self::NOW), Date::sub("PT24H", self::NOW))], // this is a nonsense query, but it's what TT-RSS appearsto do
            [['feed_id' => -3, 'is_cat' => true],                    null],
            [['feed_id' => -2],                                      null],
            [['feed_id' => -2, 'is_cat' => true],                    (clone $c)->labelled(true)],
            [['feed_id' => -2, 'is_cat' => true, 'mode' => "all"],   (clone $c)->labelled(true)],
            [['feed_id' => -4],                                      $c],
            [['feed_id' => -4, 'is_cat' => true],                    null],
            [['feed_id' => -6, 'is_cat' => "f"],                     null],
            [['feed_id' => -2112],                                   (clone $c)->label(1088)],
            [['feed_id' => 42, 'is_cat' => true],                    (clone $c)->folder(42)],
            [['feed_id' => 42, 'is_cat' => true, 'mode' => "1week"], (clone $c)->folder(42)->modifiedRange(null, Date::sub("P1W", self::NOW))],
            [['feed_id' => 2112],                                    (clone $c)->subscription(2112)],
            [['feed_id' => 2112, 'mode' => "2week"],                 (clone $c)->subscription(2112)->modifiedRange(null, Date::sub("P2W", self::NOW))],
        ];
    }

    #[DataProvider("provideFeedListings")]
    public function testRetrieveFeedList(array $in, ResponseInterface $exp): void {
        $in = array_merge(['op' => "getFeeds", 'sid' => "PriestsOfSyrinx"], $in);
        // statistical mocks
        \Phake::when(Arsse::$db)->articleStarred->thenReturn(self::v($this->starred));
        \Phake::when(Arsse::$db)->articleCount($this->anything(), $this->equalTo((new Context)->unread(true)->hidden(false)->modifiedRange(Date::sub("PT24H", self::NOW), null)))->thenReturn(7);
        \Phake::when(Arsse::$db)->articleCount($this->anything(), $this->equalTo((new Context)->unread(true)->hidden(false)))->thenReturn(35);
        // label mocks
        \Phake::when(Arsse::$db)->labelList->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result(self::v($this->usedLabels)));
        // subscription and folder list and unread count mocks
        \Phake::when(Arsse::$db)->folderList->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionList->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->folderList($this->anything())->thenReturn(new Result(self::v($this->folders)));
        \Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, true)->thenReturn(new Result(self::v($this->subscriptions)));
        \Phake::when(Arsse::$db)->subscriptionList($this->anything(), null, false)->thenReturn(new Result(self::v($this->filterSubs(null))));
        \Phake::when(Arsse::$db)->folderList($this->anything(), null)->thenReturn(new Result(self::v($this->folders)));
        \Phake::when(Arsse::$db)->folderList($this->anything(), null, false)->thenReturn(new Result(self::v($this->filterFolders(null))));
        foreach ($this->folders as $f) {
            \Phake::when(Arsse::$db)->folderList($this->anything(), $f['id'], false)->thenReturn(new Result(self::v($this->filterFolders($f['id']))));
            \Phake::when(Arsse::$db)->articleCount($this->anything(), $this->equalTo((new Context)->unread(true)->hidden(false)->folder($f['id'])))->thenReturn($this->reduceFolders($f['id']));
            \Phake::when(Arsse::$db)->subscriptionList($this->anything(), $f['id'], false)->thenReturn(new Result(self::v($this->filterSubs($f['id']))));
        }
        $this->assertMessage($exp, $this->req($in));
    }

    protected function filterFolders(?int $id = null): array {
        return array_filter($this->folders, function($value) use ($id) {
            return $value['parent'] == $id;
        });
    }

    protected function filterSubs(?int $folder = null): array {
        return array_filter($this->subscriptions, function($value) use ($folder) {
            return $value['folder'] == $folder;
        });
    }

    protected function reduceFolders(?int $id = null): int {
        $out = 0;
        foreach ($this->filterFolders($id) as $f) {
            $out += $this->reduceFolders($f['id']);
        }
        $out += array_reduce(array_filter($this->subscriptions, function($value) use ($id) {
            return $value['folder'] == $id;
        }), function($sum, $value) {
            return $sum + $value['unread'];
        }, 0);
        return $out;
    }

    public static function provideFeedListings(): iterable {
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
                ['id' => 0, 'title' => "Archived articles",  'unread' => "0",  'cat_id' => -1],
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
                ['id' => 0, 'title' => "Archived articles",  'unread' => "0",  'cat_id' => -1],
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
        return [
            [[],                                           self::respGood($exp[0])],
            [['cat_id' => -1],                             self::respGood($exp[1])],
            [['cat_id' => -1, 'unread_only' => true],      self::respGood($exp[2])],
            [['cat_id' => -2],                             self::respGood($exp[3])],
            [['cat_id' => -2, 'unread_only' => true],      self::respGood($exp[4])],
            [['cat_id' => -3],                             self::respGood($exp[5])],
            [['cat_id' => -3, 'unread_only' => true],      self::respGood($exp[6])],
            [['cat_id' => -4],                             self::respGood($exp[7])],
            [['cat_id' => -4, 'unread_only' => true],      self::respGood($exp[8])],
            [['cat_id' => 6],                              self::respGood($exp[9])],
            [['cat_id' => 6, 'limit' => 1],                self::respGood($exp[10])],
            [['cat_id' => 6, 'limit' => 1, 'offset' => 1], self::respGood($exp[11])],
            [['cat_id' => 1],                              self::respGood($exp[12])],
            [['cat_id' => 1, 'include_nested' => true],    self::respGood($exp[13])],
            [['cat_id' => 0, 'unread_only' => true],       self::respGood([])],
            [['cat_id' => 2112],                           self::respGood([])],
            [['cat_id' => 2112, 'include_nested' => true], self::respGood([])],
            [['cat_id' => 6, 'limit' => -42],              self::respGood([])],
            [['cat_id' => 6, 'offset' => 2],               self::respGood([])],
        ];
    }

    #[DataProvider("provideArticleChanges")]
    public function testChangeArticles(array $in, ResponseInterface $exp): void {
        $in = array_merge(['op' => "updateArticle", 'sid' => "PriestsOfSyrinx"], $in);
        \Phake::when(Arsse::$db)->articleMark->thenReturn(1);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['starred' => false], $this->equalTo((new Context)->articles([42, 2112])))->thenReturn(2);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['starred' =>  true], $this->equalTo((new Context)->articles([42, 2112])))->thenReturn(4);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['starred' => false], $this->equalTo((new Context)->articles([42])))->thenReturn(8);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['starred' =>  true], $this->equalTo((new Context)->articles([2112])))->thenReturn(16);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['read'    =>  true], $this->equalTo((new Context)->articles([42, 2112])))->thenReturn(32); // false is read for TT-RSS
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['read'    => false], $this->equalTo((new Context)->articles([42, 2112])))->thenReturn(64);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['read'    =>  true], $this->equalTo((new Context)->articles([42])))->thenReturn(128);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['read'    => false], $this->equalTo((new Context)->articles([2112])))->thenReturn(256);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['note'    =>    ""], $this->equalTo((new Context)->articles([42, 2112])))->thenReturn(512);
        \Phake::when(Arsse::$db)->articleMark(self::$userId, ['note'    =>  "eh"], $this->equalTo((new Context)->articles([42, 2112])))->thenReturn(1024);
        \Phake::when(Arsse::$db)->articleList(self::$userId, $this->equalTo((new Context)->articles([42, 2112])->starred(true)), $this->anything())->thenReturn(new Result(self::v([['id' => 42]])));
        \Phake::when(Arsse::$db)->articleList(self::$userId, $this->equalTo((new Context)->articles([42, 2112])->starred(false)), $this->anything())->thenReturn(new Result(self::v([['id' => 2112]])));
        \Phake::when(Arsse::$db)->articleList(self::$userId, $this->equalTo((new Context)->articles([42, 2112])->unread(true)), $this->anything())->thenReturn(new Result(self::v([['id' => 42]])));
        \Phake::when(Arsse::$db)->articleList(self::$userId, $this->equalTo((new Context)->articles([42, 2112])->unread(false)), $this->anything())->thenReturn(new Result(self::v([['id' => 2112]])));
        $this->assertMessage($exp, $this->req($in));
    }

    public static function provideArticleChanges(): iterable {
        return [
            [[],                                                              self::respErr("INCORRECT_USAGE")],
            [['article_ids' => "42, 2112, -1"],                               self::respGood(['status' => "OK", 'updated' => 2])],
            [['article_ids' => "42, 2112, -1", 'field' => 0],                 self::respGood(['status' => "OK", 'updated' => 2])],
            [['article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 0],    self::respGood(['status' => "OK", 'updated' => 2])],
            [['article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 1],    self::respGood(['status' => "OK", 'updated' => 4])],
            [['article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 2],    self::respGood(['status' => "OK", 'updated' => 24])],
            [['article_ids' => "42, 2112, -1", 'field' => 0, 'mode' => 3],    self::respErr("INCORRECT_USAGE")],
            [['article_ids' => "42, 2112, -1", 'field' => 1],                 self::respGood(['status' => "OK", 'updated' => 0])],
            [['article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 0],    self::respGood(['status' => "OK", 'updated' => 0])],
            [['article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 1],    self::respGood(['status' => "OK", 'updated' => 0])],
            [['article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 2],    self::respGood(['status' => "OK", 'updated' => 0])],
            [['article_ids' => "42, 2112, -1", 'field' => 1, 'mode' => 3],    self::respErr("INCORRECT_USAGE")],
            [['article_ids' => "42, 2112, -1", 'field' => 2],                 self::respGood(['status' => "OK", 'updated' => 32])],
            [['article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 0],    self::respGood(['status' => "OK", 'updated' => 32])],
            [['article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 1],    self::respGood(['status' => "OK", 'updated' => 64])],
            [['article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 2],    self::respGood(['status' => "OK", 'updated' => 384])],
            [['article_ids' => "42, 2112, -1", 'field' => 2, 'mode' => 3],    self::respErr("INCORRECT_USAGE")],
            [['article_ids' => "42, 2112, -1", 'field' => 3],                 self::respGood(['status' => "OK", 'updated' => 512])],
            [['article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 0],    self::respGood(['status' => "OK", 'updated' => 512])],
            [['article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 1],    self::respGood(['status' => "OK", 'updated' => 512])],
            [['article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 2],    self::respGood(['status' => "OK", 'updated' => 512])],
            [['article_ids' => "42, 2112, -1", 'field' => 3, 'mode' => 3],    self::respGood(['status' => "OK", 'updated' => 512])],
            [['article_ids' => "42, 2112, -1", 'field' => 3, 'data' => "eh"], self::respGood(['status' => "OK", 'updated' => 1024])],
            [['article_ids' => "42, 2112, -1", 'field' => 4],                 self::respErr("INCORRECT_USAGE")],
            [['article_ids' => "0, -1",        'field' => 3],                 self::respErr("INCORRECT_USAGE")],
        ];
    }

    #[DataProvider("provideArticleListings")]
    public function testListArticles(array $in, ResponseInterface $exp): void {
        $in = array_merge(['op' => "getArticle", 'sid' => "PriestsOfSyrinx"], $in);
        \Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result(self::v($this->usedLabels)));
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 101)->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 102)->thenReturn(self::v([1,3]));
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->articles([101, 102])), $this->anything())->thenReturn(new Result(self::v($this->articles)));
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->articles([101])), $this->anything())->thenReturn(new Result(self::v([$this->articles[0]])));
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->articles([102])), $this->anything())->thenReturn(new Result(self::v([$this->articles[1]])));
        $this->assertMessage($exp, $this->req($in));
    }

    public static function provideArticleListings(): iterable {
        $exp = [
            [
                'id'          => "101",
                'guid'        => null,
                'title'       => 'Article title 1',
                'link'        => 'http://example.com/1',
                'labels'      => [],
                'unread'      => true,
                'marked'      => false,
                'published'   => false,
                'comments'    => "",
                'author'      => '',
                'updated'     => strtotime('2000-01-01T00:00:01Z'),
                'feed_id'     => "8",
                'feed_title'  => "Feed 11",
                'attachments' => [],
                'score'       => 0,
                'note'        => null,
                'lang'        => "",
                'content'     => '<p>Article content 1</p>',
            ],
            [
                'id'     => "102",
                'guid'   => "SHA256:5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7",
                'title'  => 'Article title 2',
                'link'   => 'http://example.com/2',
                'labels' => [
                    [-1025, "Logical", "", ""],
                    [-1027, "Fascinating", "", ""],
                ],
                'unread'      => false,
                'marked'      => false,
                'published'   => false,
                'comments'    => "",
                'author'      => "J. King",
                'updated'     => strtotime('2000-01-02T00:00:02Z'),
                'feed_id'     => "8",
                'feed_title'  => "Feed 11",
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
                'score'   => 0,
                'note'    => "Note 2",
                'lang'    => "",
                'content' => '<p>Article content 2</p>',
            ],
        ];
        return [
            [[],                          self::respErr("INCORRECT_USAGE")],
            [['article_id' => 0],         self::respErr("INCORRECT_USAGE")],
            [['article_id' => -1],        self::respErr("INCORRECT_USAGE")],
            [['article_id' => "0,-1"],    self::respErr("INCORRECT_USAGE")],
            [['article_id' => "101,102"], self::respGood($exp)],
            [['article_id' => "101"],     self::respGood([$exp[0]])],
            [['article_id' => "102"],     self::respGood([$exp[1]])],
        ];
    }

    #[DataProvider("provideArticleListingsWithoutLabels")]
    public function testListArticlesWithoutLabels(array $in, ResponseInterface $exp): void {
        $in = array_merge(['op' => "getArticle", 'sid' => "PriestsOfSyrinx"], $in);
        \Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 101)->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 102)->thenReturn(self::v([1,3]));
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->articles([101, 102])), $this->anything())->thenReturn(new Result(self::v($this->articles)));
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->articles([101])), $this->anything())->thenReturn(new Result(self::v([$this->articles[0]])));
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->articles([102])), $this->anything())->thenReturn(new Result(self::v([$this->articles[1]])));
        $this->assertMessage($exp, $this->req($in));
    }

    public static function provideArticleListingsWithoutLabels(): iterable {
        $exp = [
            [
                'id'          => "101",
                'guid'        => null,
                'title'       => 'Article title 1',
                'link'        => 'http://example.com/1',
                'labels'      => [],
                'unread'      => true,
                'marked'      => false,
                'published'   => false,
                'comments'    => "",
                'author'      => '',
                'updated'     => strtotime('2000-01-01T00:00:01Z'),
                'feed_id'     => "8",
                'feed_title'  => "Feed 11",
                'attachments' => [],
                'score'       => 0,
                'note'        => null,
                'lang'        => "",
                'content'     => '<p>Article content 1</p>',
            ],
            [
                'id'          => "102",
                'guid'        => "SHA256:5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7",
                'title'       => 'Article title 2',
                'link'        => 'http://example.com/2',
                'labels'      => [],
                'unread'      => false,
                'marked'      => false,
                'published'   => false,
                'comments'    => "",
                'author'      => "J. King",
                'updated'     => strtotime('2000-01-02T00:00:02Z'),
                'feed_id'     => "8",
                'feed_title'  => "Feed 11",
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
                'score'   => 0,
                'note'    => "Note 2",
                'lang'    => "",
                'content' => '<p>Article content 2</p>',
            ],
        ];
        return [
            [[],                          self::respErr("INCORRECT_USAGE")],
            [['article_id' => 0],         self::respErr("INCORRECT_USAGE")],
            [['article_id' => -1],        self::respErr("INCORRECT_USAGE")],
            [['article_id' => "0,-1"],    self::respErr("INCORRECT_USAGE")],
            [['article_id' => "101,102"], self::respGood($exp)],
            [['article_id' => "101"],     self::respGood([$exp[0]])],
            [['article_id' => "102"],     self::respGood([$exp[1]])],
        ];
    }

    #[DataProvider("provideHeadlines")]
    public function testRetrieveHeadlines(bool $full, array $in, $out, Context $c, array $fields, array $order, ResponseInterface $exp): void {
        $base = ['op' => $full ? "getHeadlines" : "getCompactHeadlines", 'sid' => "PriestsOfSyrinx"];
        $in = array_merge($base, $in);
        $action = ($out instanceof \Exception) ? "thenThrow" : "thenReturn";
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        \Phake::when(Arsse::$db)->labelList->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result(self::v($this->usedLabels)));
        \Phake::when(Arsse::$db)->articleLabelsGet->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2112)->thenReturn(self::v([1,3]));
        \Phake::when(Arsse::$db)->articleCategoriesGet->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($this->anything(), 2112)->thenReturn(["Boring","Illogical"]);
        \Phake::when(Arsse::$db)->articleCount->thenReturn(2);
        \Phake::when(Arsse::$db)->articleList->$action($out);
        $this->assertMessage($exp, $this->req($in));
        if ($out) {
            \Phake::verify(Arsse::$db)->articleList(self::$userId, $this->equalTo($c), $fields, $order);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleList(\Phake::anyParameters());
        }
    }

    public static function provideHeadlines(): iterable {
        $t = Date::normalize(self::NOW);
        $c = (new Context)->hidden(false)->limit(200);
        $out = self::generateHeadlines(47);
        $gone = new ExceptionInput("idMissing");
        $comp = new Result(self::v([['id' => 47], ['id' => 2112]]));
        $expFull = self::outputHeadlines(47);
        $expComp = self::respGood([['id' => 47], ['id' => 2112]]);
        $fields = ["id", "guid", "title", "author", "url", "unread", "starred", "edited_date", "published_date", "subscription", "subscription_title", "note"];
        $sort = ["edited_date desc"];
        return [
            [true,  [],                                                                     null,  $c,                                                                                                [],      [],                   self::respErr("INCORRECT_USAGE")],
            [true,  ['feed_id' => 0],                                                       null,  $c,                                                                                                [],      [],                   self::respGood([])],
            [true,  ['feed_id' => -1],                                                      $out,  (clone $c)->starred(true),                                                                         $fields, ["marked_date desc"], $expFull],
            [true,  ['feed_id' => -2],                                                      null,  $c,                                                                                                [],      [],                   self::respGood([])],
            [true,  ['feed_id' => -4],                                                      $out,  $c,                                                                                                $fields, $sort,                $expFull],
            [true,  ['feed_id' => 2112],                                                    $gone, (clone $c)->subscription(2112),                                                                    $fields, $sort,                self::respGood([])],
            [true,  ['feed_id' => -2112],                                                   $out,  (clone $c)->label(1088),                                                                           $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'view_mode' => "adaptive"],                           $out,  (clone $c)->unread(true),                                                                          $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'view_mode' => "published"],                          null,  $c,                                                                                                [],      [],                   self::respGood([])],
            [true,  ['feed_id' => -2112, 'view_mode' => "adaptive"],                        $out,  (clone $c)->label(1088)->unread(true),                                                             $fields, $sort,                $expFull],
            [true,  ['feed_id' => -2112, 'view_mode' => "unread"],                          $out,  (clone $c)->label(1088)->unread(true),                                                             $fields, $sort,                $expFull],
            [true,  ['feed_id' => 42, 'view_mode' => "marked"],                             $out,  (clone $c)->subscription(42)->starred(true),                                                       $fields, $sort,                $expFull],
            [true,  ['feed_id' => 42, 'view_mode' => "has_note"],                           $out,  (clone $c)->subscription(42)->annotated(true),                                                     $fields, $sort,                $expFull],
            [true,  ['feed_id' => 42, 'view_mode' => "unread", 'search' => "unread:false"], null,  $c,                                                                                                [],      [],                   self::respGood([])],
            [true,  ['feed_id' => 42, 'search' => "pub:true"],                              null,  $c,                                                                                                [],      [],                   self::respGood([])],
            [true,  ['feed_id' => -4, 'limit' => 5],                                        $out,  (clone $c)->limit(5),                                                                              $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'skip' => 2],                                         $out,  (clone $c)->offset(2),                                                                             $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'limit' => 5, 'skip' => 2],                           $out,  (clone $c)->limit(5)->offset(2),                                                                   $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'since_id' => 47],                                    $out,  (clone $c)->articleRange(48, null),                                                                $fields, $sort,                $expFull],
            [true,  ['feed_id' => -3, 'is_cat' => true],                                    $out,  $c,                                                                                                $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'is_cat' => true],                                    $out,  $c,                                                                                                $fields, $sort,                $expFull],
            [true,  ['feed_id' => -2, 'is_cat' => true],                                    $out,  (clone $c)->labelled(true),                                                                        $fields, $sort,                $expFull],
            [true,  ['feed_id' => -1, 'is_cat' => true],                                    null,  $c,                                                                                                [],      [],                   self::respGood([])],
            [true,  ['feed_id' => 0, 'is_cat' => true],                                     $out,  (clone $c)->folderShallow(0),                                                                      $fields, $sort,                $expFull],
            [true,  ['feed_id' => 0, 'is_cat' => true, 'include_nested' => true],           $out,  (clone $c)->folderShallow(0),                                                                      $fields, $sort,                $expFull],
            [true,  ['feed_id' => 42, 'is_cat' => true],                                    $out,  (clone $c)->folderShallow(42),                                                                     $fields, $sort,                $expFull],
            [true,  ['feed_id' => 42, 'is_cat' => true, 'include_nested' => true],          $out,  (clone $c)->folder(42),                                                                            $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'order_by' => "feed_dates"],                          $out,  $c,                                                                                                $fields, $sort,                $expFull],
            [true,  ['feed_id' => -4, 'order_by' => "date_reverse"],                        $out,  $c,                                                                                                $fields, ["edited_date"],      $expFull],
            [true,  ['feed_id' => 42, 'search' => "interesting"],                           $out,  (clone $c)->subscription(42)->searchTerms(["interesting"]),                                        $fields, $sort,                $expFull],
            [true,  ['feed_id' => -6],                                                      $out,  (clone $c)->unread(false)->markedRange(Date::sub("PT24H", $t), null),                              $fields, ["marked_date desc"], $expFull],
            [true,  ['feed_id' => -6, 'view_mode' => "unread"],                             null,  $c,                                                                                                $fields, $sort,                self::respGood([])],
            [true,  ['feed_id' => -3],                                                      $out,  (clone $c)->unread(true)->modifiedRange(Date::sub("PT24H", $t), null),                             $fields, $sort,                $expFull],
            [true,  ['feed_id' => -3, 'view_mode' => "marked"],                             $out,  (clone $c)->unread(true)->starred(true)->modifiedRange(Date::sub("PT24H", $t), null),              $fields, $sort,                $expFull],
            [false, [],                                                                     null,  (clone $c)->limit(null),                                                                           [],      [],                   self::respErr("INCORRECT_USAGE")],
            [false, ['feed_id' => 0],                                                       null,  (clone $c)->limit(null),                                                                           [],      [],                   self::respGood([])],
            [false, ['feed_id' => -1],                                                      $comp, (clone $c)->limit(null)->starred(true),                                                            ["id"],  ["marked_date desc"], $expComp],
            [false, ['feed_id' => -2],                                                      null,  (clone $c)->limit(null),                                                                           [],      [],                   self::respGood([])],
            [false, ['feed_id' => -4],                                                      $comp, (clone $c)->limit(null),                                                                           ["id"],  $sort,                $expComp],
            [false, ['feed_id' => 2112],                                                    $gone, (clone $c)->limit(null)->subscription(2112),                                                       ["id"],  $sort,                self::respGood([])],
            [false, ['feed_id' => -2112],                                                   $comp, (clone $c)->limit(null)->label(1088),                                                              ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -4, 'view_mode' => "adaptive"],                           $comp, (clone $c)->limit(null)->unread(true),                                                             ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -4, 'view_mode' => "published"],                          null,  (clone $c)->limit(null),                                                                           [],      [],                   self::respGood([])],
            [false, ['feed_id' => -2112, 'view_mode' => "adaptive"],                        $comp, (clone $c)->limit(null)->label(1088)->unread(true),                                                ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -2112, 'view_mode' => "unread"],                          $comp, (clone $c)->limit(null)->label(1088)->unread(true),                                                ["id"],  $sort,                $expComp],
            [false, ['feed_id' => 42, 'view_mode' => "marked"],                             $comp, (clone $c)->limit(null)->subscription(42)->starred(true),                                          ["id"],  $sort,                $expComp],
            [false, ['feed_id' => 42, 'view_mode' => "has_note"],                           $comp, (clone $c)->limit(null)->subscription(42)->annotated(true),                                        ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -4, 'limit' => 5],                                        $comp, (clone $c)->limit(5),                                                                              ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -4, 'skip' => 2],                                         $comp, (clone $c)->limit(null)->offset(2),                                                                ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -4, 'limit' => 5, 'skip' => 2],                           $comp, (clone $c)->limit(5)->offset(2),                                                                   ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -4, 'since_id' => 47],                                    $comp, (clone $c)->limit(null)->articleRange(48, null),                                                   ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -6],                                                      $comp, (clone $c)->limit(null)->unread(false)->markedRange(Date::sub("PT24H", $t), null),                 ["id"],  ["marked_date desc"], $expComp],
            [false, ['feed_id' => -6, 'view_mode' => "unread"],                             null,  (clone $c)->limit(null),                                                                           ["id"],  $sort,                self::respGood([])],
            [false, ['feed_id' => -3],                                                      $comp, (clone $c)->limit(null)->unread(true)->modifiedRange(Date::sub("PT24H", $t), null),                ["id"],  $sort,                $expComp],
            [false, ['feed_id' => -3, 'view_mode' => "marked"],                             $comp, (clone $c)->limit(null)->unread(true)->starred(true)->modifiedRange(Date::sub("PT24H", $t), null), ["id"],  $sort,                $expComp],
        ];
    }

    public function testRetrieveFullHeadlinesCheckingExtraFields(): void {
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
        \Phake::when(Arsse::$db)->labelList($this->anything())->thenReturn(new Result(self::v($this->labels)));
        \Phake::when(Arsse::$db)->labelList($this->anything(), false)->thenReturn(new Result(self::v($this->usedLabels)));
        \Phake::when(Arsse::$db)->articleLabelsGet->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($this->anything(), 2112)->thenReturn(self::v([1,3]));
        \Phake::when(Arsse::$db)->articleCategoriesGet->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($this->anything(), 2112)->thenReturn(["Boring","Illogical"]);
        \Phake::when(Arsse::$db)->articleList->thenReturn(self::generateHeadlines(1));
        \Phake::when(Arsse::$db)->articleCount->thenReturn(0);
        \Phake::when(Arsse::$db)->articleCount($this->anything(), $this->equalTo((new Context)->unread(true)->hidden(false)))->thenReturn(1);
        // sanity check; this makes sure extra fields are not included in default situations
        $test = $this->req($in[0]);
        $this->assertMessage(self::outputHeadlines(1), $test);
        // test 'show_content'
        $test = $this->req($in[1]);
        $this->assertArrayHasKey("content", $this->extractMessageJson($test)['content'][0]);
        $this->assertArrayHasKey("content", $this->extractMessageJson($test)['content'][1]);
        foreach (self::generateHeadlines(1) as $key => $row) {
            $this->assertSame($row['content'], $this->extractMessageJson($test)['content'][$key]['content']);
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
        $this->assertArrayHasKey("attachments", $this->extractMessageJson($test)['content'][0]);
        $this->assertArrayHasKey("attachments", $this->extractMessageJson($test)['content'][1]);
        $this->assertSame([], $this->extractMessageJson($test)['content'][0]['attachments']);
        $this->assertSame($exp, $this->extractMessageJson($test)['content'][1]['attachments']);
        // test 'include_header'
        $test = $this->req($in[3]);
        $exp = self::respGood([
            ['id' => -4, 'is_cat' => false, 'first_id' => 1],
            $this->extractMessageJson(self::outputHeadlines(1))['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with a category
        $test = $this->req($in[4]);
        $exp = self::respGood([
            ['id' => -3, 'is_cat' => true, 'first_id' => 1],
            $this->extractMessageJson(self::outputHeadlines(1))['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with an empty result
        $test = $this->req($in[5]);
        $exp = self::respGood([
            ['id' => -1, 'is_cat' => true, 'first_id' => 0],
            [],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with an erroneous result
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->limit(200)->subscription(2112)->hidden(false)), $this->anything(), ["edited_date desc"])->thenThrow(new ExceptionInput("subjectMissing"));
        $test = $this->req($in[6]);
        $exp = self::respGood([
            ['id' => 2112, 'is_cat' => false, 'first_id' => 0],
            [],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with ascending order
        $test = $this->req($in[7]);
        $exp = self::respGood([
            ['id' => -4, 'is_cat' => false, 'first_id' => 0],
            $this->extractMessageJson(self::outputHeadlines(1))['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with skip
        \Phake::when(Arsse::$db)->articleList($this->anything(), $this->equalTo((new Context)->limit(1)->subscription(42)->hidden(false)), $this->anything(), ["edited_date desc"])->thenReturn(self::generateHeadlines(1867));
        $test = $this->req($in[8]);
        $exp = self::respGood([
            ['id' => 42, 'is_cat' => false, 'first_id' => 1867],
            $this->extractMessageJson(self::outputHeadlines(1))['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'include_header' with skip and ascending order
        $test = $this->req($in[9]);
        $exp = self::respGood([
            ['id' => 42, 'is_cat' => false, 'first_id' => 0],
            $this->extractMessageJson(self::outputHeadlines(1))['content'],
        ]);
        $this->assertMessage($exp, $test);
        // test 'show_excerpt'
        $exp1 = "“This & that, you know‽”";
        $exp2 = "Pour vous faire mieux connaitre d’ou\u{300} vient l’erreur de ceux qui bla\u{302}ment la volupte\u{301}, et qui louent en…";
        $test = $this->req($in[10]);
        $this->assertArrayHasKey("excerpt", $this->extractMessageJson($test)['content'][0]);
        $this->assertArrayHasKey("excerpt", $this->extractMessageJson($test)['content'][1]);
        $this->assertSame($exp1, $this->extractMessageJson($test)['content'][0]['excerpt']);
        $this->assertSame($exp2, $this->extractMessageJson($test)['content'][1]['excerpt']);
    }

    protected static function generateHeadlines(int $id): Result {
        return new Result(self::v([
            [
                'id'                 => $id,
                'url'                => 'http://example.com/1',
                'title'              => 'Article title 1',
                'subscription_title' => "Feed 2112",
                'author'             => '',
                'content'            => '<p>&ldquo;This &amp; that, you know&#8253;&rdquo;</p>',
                'guid'               => null,
                'published_date'     => '2000-01-01 00:00:00',
                'edited_date'        => '2000-01-01 00:00:00',
                'modified_date'      => '2000-01-01 01:00:00',
                'unread'             => 0,
                'starred'            => 0,
                'edition'            => 101,
                'subscription'       => 12,
                'fingerprint'        => 'f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6:fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4:18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207',
                'media_url'          => null,
                'media_type'         => null,
                'note'               => "",
            ],
            [
                'id'                 => 2112,
                'url'                => 'http://example.com/2',
                'title'              => 'Article title 2',
                'subscription_title' => "Feed 11",
                'author'             => 'J. King',
                'content'            => self::$richContent,
                'guid'               => '5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7',
                'published_date'     => '2000-01-02 00:00:00',
                'edited_date'        => '2000-01-02 00:00:02',
                'modified_date'      => '2000-01-02 02:00:00',
                'unread'             => 1,
                'starred'            => 1,
                'edition'            => 202,
                'subscription'       => 8,
                'fingerprint'        => '0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153:13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9:2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e',
                'media_url'          => "http://example.com/text",
                'media_type'         => "text/plain",
                'note'               => "Note 2",
            ],
        ]));
    }

    protected static function outputHeadlines(int $id): ResponseInterface {
        return self::respGood([
            [
                'id'                         => $id,
                'guid'                       => '',
                'title'                      => 'Article title 1',
                'link'                       => 'http://example.com/1',
                'labels'                     => [],
                'unread'                     => false,
                'marked'                     => false,
                'published'                  => false,
                'author'                     => '',
                'updated'                    => strtotime('2000-01-01T00:00:00Z'),
                'is_updated'                 => false,
                'feed_id'                    => "12",
                'feed_title'                 => "Feed 2112",
                'score'                      => 0,
                'note'                       => null,
                'lang'                       => "",
                'tags'                       => [],
                'comments_count'             => 0,
                'comments_link'              => "",
                'always_display_attachments' => false,
            ],
            [
                'id'     => 2112,
                'guid'   => "SHA256:5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7",
                'title'  => 'Article title 2',
                'link'   => 'http://example.com/2',
                'labels' => [
                    [-1025, "Logical", "", ""],
                    [-1027, "Fascinating", "", ""],
                ],
                'unread'                     => true,
                'marked'                     => true,
                'published'                  => false,
                'author'                     => "J. King",
                'updated'                    => strtotime('2000-01-02T00:00:02Z'),
                'is_updated'                 => true,
                'feed_id'                    => "8",
                'feed_title'                 => "Feed 11",
                'score'                      => 0,
                'note'                       => "Note 2",
                'lang'                       => "",
                'tags'                       => ["Boring", "Illogical"],
                'comments_count'             => 0,
                'comments_link'              => "",
                'always_display_attachments' => false,
            ],
        ]);
    }
}
