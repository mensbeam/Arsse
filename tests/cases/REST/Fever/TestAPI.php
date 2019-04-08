<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\REST\Request;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\User\Exception as UserException;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\Fever\API;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;

/** @covers \JKingWeb\Arsse\REST\Fever\API<extended> */
class TestAPI extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $articles = [
        'db' => [
            [
                'id' => 101,
                'url' => 'http://example.com/1',
                'title' => 'Article title 1',
                'author' => '',
                'content' => '<p>Article content 1</p>',
                'published_date' => '2000-01-01 00:00:00',
                'unread' => 1,
                'starred' => 0,
                'subscription' => 8,
            ],
            [
                'id' => 102,
                'url' => 'http://example.com/2',
                'title' => 'Article title 2',
                'author' => '',
                'content' => '<p>Article content 2</p>',
                'published_date' => '2000-01-02 00:00:00',
                'unread' => 0,
                'starred' => 0,
                'subscription' => 8,
            ],
            [
                'id' => 103,
                'url' => 'http://example.com/3',
                'title' => 'Article title 3',
                'author' => '',
                'content' => '<p>Article content 3</p>',
                'published_date' => '2000-01-03 00:00:00',
                'unread' => 1,
                'starred' => 1,
                'subscription' => 9,
            ],
            [
                'id' => 104,
                'url' => 'http://example.com/4',
                'title' => 'Article title 4',
                'author' => '',
                'content' => '<p>Article content 4</p>',
                'published_date' => '2000-01-04 00:00:00',
                'unread' => 0,
                'starred' => 1,
                'subscription' => 9,
            ],
            [
                'id' => 105,
                'url' => 'http://example.com/5',
                'title' => 'Article title 5',
                'author' => '',
                'content' => '<p>Article content 5</p>',
                'published_date' => '2000-01-05 00:00:00',
                'unread' => 1,
                'starred' => 0,
                'subscription' => 10,
            ],
        ],
        'rest' => [
            [
                'id' => 101,
                'feed_id' => 8,
                'title' => 'Article title 1',
                'author' => '',
                'html' => '<p>Article content 1</p>',
                'url' => 'http://example.com/1',
                'is_saved' => 0,
                'is_read' => 0,
                'created_on_time' => 946684800,
            ],
            [
                'id' => 102,
                'feed_id' => 8,
                'title' => 'Article title 2',
                'author' => '',
                'html' => '<p>Article content 2</p>',
                'url' => 'http://example.com/2',
                'is_saved' => 0,
                'is_read' => 1,
                'created_on_time' => 946771200,
            ],
            [
                'id' => 103,
                'feed_id' => 9,
                'title' => 'Article title 3',
                'author' => '',
                'html' => '<p>Article content 3</p>',
                'url' => 'http://example.com/3',
                'is_saved' => 1,
                'is_read' => 0,
                'created_on_time' => 946857600,
            ],
            [
                'id' => 104,
                'feed_id' => 9,
                'title' => 'Article title 4',
                'author' => '',
                'html' => '<p>Article content 4</p>',
                'url' => 'http://example.com/4',
                'is_saved' => 1,
                'is_read' => 1,
                'created_on_time' => 946944000,
            ],
            [
                'id' => 105,
                'feed_id' => 10,
                'title' => 'Article title 5',
                'author' => '',
                'html' => '<p>Article content 5</p>',
                'url' => 'http://example.com/5',
                'is_saved' => 0,
                'is_read' => 0,
                'created_on_time' => 947030400,
            ],
        ],
    ];
    protected function v($value) {
        return $value;
    }

    protected function req($dataGet, $dataPost = "", string $method = "POST", string $type = null, string $url = "", string $user = null): ResponseInterface {
        $url = "/fever/".$url;
        $server = [
            'REQUEST_METHOD'    => $method,
            'REQUEST_URI'       => $url,
            'HTTP_CONTENT_TYPE' => $type ?? "application/x-www-form-urlencoded",
        ];
        $req = new ServerRequest($server, [], $url, $method, "php://memory");
        if (!is_array($dataGet)) {
            parse_str($dataGet, $dataGet);
        }
        $req = $req->withRequestTarget($url)->withQueryParams($dataGet);
        if (is_array($dataPost)) {
            $req = $req->withParsedBody($dataPost);
        } else {
            $body = $req->getBody();
            $body->write($dataPost);
            $req = $req->withBody($body);
        }
        if (isset($user)) {
            if (strlen($user)) {
                $req = $req->withAttribute("authenticated", true)->withAttribute("authenticatedUser", $user);
            } else {
                $req = $req->withAttribute("authenticationFailed", true);
            }
        }
        return $this->h->dispatch($req);
    }

    public function setUp() {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        Arsse::$user->id = "john.doe@example.com";
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->tokenLookup->thenReturn(['user' => "john.doe@example.com"]);
        // instantiate the handler as a partial mock to simplify testing
        $this->h = \Phake::partialMock(API::class);
        \Phake::when($this->h)->baseResponse->thenReturn([]);
    }

    public function tearDown() {
        self::clearData();
    }

    /** @dataProvider provideTokenAuthenticationRequests */
    public function testAuthenticateAUserToken(bool $httpRequired, bool $tokenEnforced, string $httpUser = null, array $dataPost, array $dataGet, ResponseInterface $exp) {
        self::setConf([
            'userHTTPAuthRequired' => $httpRequired,
            'userSessionEnforced' => $tokenEnforced,
        ], true);
        Arsse::$user->id = null;
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("fever.login", "validtoken")->thenReturn(['user' => "jane.doe@example.com"]);
        // test only the authentication process
        \Phake::when($this->h)->baseResponse->thenReturnCallback(function(bool $authenticated) {
            return ['auth' => (int) $authenticated];
        });
        \Phake::when($this->h)->processRequest->thenReturnCallback(function($out, $G, $P) {
            return $out;
        });
        $act = $this->req($dataGet, $dataPost, "POST", null, "", $httpUser);
        $this->assertMessage($exp, $act);
    }

    public function provideTokenAuthenticationRequests() {
        $success = new JsonResponse(['auth' => 1]);
        $failure = new JsonResponse(['auth' => 0]);
        $denied = new EmptyResponse(401);
        return [
            [false, true,  null, [], ['api' => null], $failure],
            [false, false, null, [], ['api' => null], $failure],
            [true,  true,  null, [], ['api' => null], $denied],
            [true,  false, null, [], ['api' => null], $denied],
            [false, true,  "", [], ['api' => null], $denied],
            [false, false, "", [], ['api' => null], $denied],
            [true,  true,  "", [], ['api' => null], $denied],
            [true,  false, "", [], ['api' => null], $denied],
            [false, true,  null, [], ['api' => null, 'api_key' => "validToken"], $failure],
            [false, false, null, [], ['api' => null, 'api_key' => "validToken"], $failure],
            [true,  true,  null, [], ['api' => null, 'api_key' => "validToken"], $denied],
            [true,  false, null, [], ['api' => null, 'api_key' => "validToken"], $denied],
            [false, true,  "", [], ['api' => null, 'api_key' => "validToken"], $denied],
            [false, false, "", [], ['api' => null, 'api_key' => "validToken"], $denied],
            [true,  true,  "", [], ['api' => null, 'api_key' => "validToken"], $denied],
            [true,  false, "", [], ['api' => null, 'api_key' => "validToken"], $denied],
            [false, true,  "validUser", [], ['api' => null, 'api_key' => "validToken"], $failure],
            [false, false, "validUser", [], ['api' => null, 'api_key' => "validToken"], $success],
            [true,  true,  "validUser", [], ['api' => null, 'api_key' => "validToken"], $failure],
            [true,  false, "validUser", [], ['api' => null, 'api_key' => "validToken"], $success],
            [false, true,  null, ['api_key' => "validToken"], ['api' => null], $success],
            [false, false, null, ['api_key' => "validToken"], ['api' => null], $success],
            [true,  true,  null, ['api_key' => "validToken"], ['api' => null], $denied],
            [true,  false, null, ['api_key' => "validToken"], ['api' => null], $denied],
            [false, true,  "", ['api_key' => "validToken"], ['api' => null], $denied],
            [false, false, "", ['api_key' => "validToken"], ['api' => null], $denied],
            [true,  true,  "", ['api_key' => "validToken"], ['api' => null], $denied],
            [true,  false, "", ['api_key' => "validToken"], ['api' => null], $denied],
            [false, true,  "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [false, false, "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [true,  true,  "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [true,  false, "validUser", ['api_key' => "validToken"], ['api' => null], $success],
            [false, true,  null, ['api_key' => "invalidToken"], ['api' => null], $failure],
            [false, false, null, ['api_key' => "invalidToken"], ['api' => null], $failure],
            [true,  true,  null, ['api_key' => "invalidToken"], ['api' => null], $denied],
            [true,  false, null, ['api_key' => "invalidToken"], ['api' => null], $denied],
            [false, true,  "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [false, false, "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [true,  true,  "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [true,  false, "", ['api_key' => "invalidToken"], ['api' => null], $denied],
            [false, true,  "validUser", ['api_key' => "invalidToken"], ['api' => null], $failure],
            [false, false, "validUser", ['api_key' => "invalidToken"], ['api' => null], $success],
            [true,  true,  "validUser", ['api_key' => "invalidToken"], ['api' => null], $failure],
            [true,  false, "validUser", ['api_key' => "invalidToken"], ['api' => null], $success],
        ];
    }

    public function testListGroups() {
        \Phake::when(Arsse::$db)->tagList(Arsse::$user->id)->thenReturn(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscriptions' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscriptions' => 2],
            ['id' => 3, 'name' => "Boring",      'subscriptions' => 0],
        ]));
        \Phake::when(Arsse::$db)->tagSummarize(Arsse::$user->id)->thenReturn(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 3],
        ]));
        $exp = new JsonResponse([
            'groups' => [
                ['id' => 1, 'title' => "Fascinating"],
                ['id' => 2, 'title' => "Interesting"],
                ['id' => 3, 'title' => "Boring"],
            ],
            'feeds_groups' => [
                ['group_id' => 1, 'feed_ids' => "1,2"],
                ['group_id' => 2, 'feed_ids' => "1,3"],
            ],
        ]);
        $act = $this->req("api&groups");
        $this->assertMessage($exp, $act);
    }

    public function testListFeeds() {
        \Phake::when(Arsse::$db)->subscriptionList(Arsse::$user->id)->thenReturn(new Result([
            ['id' => 1, 'feed' => 5, 'title' => "Ankh-Morpork News", 'url' => "http://example.com/feed", 'source' => "http://example.com/", 'edited' => "2019-01-01 21:12:00", 'favicon' => "http://example.com/favicon.ico"],
            ['id' => 2, 'feed' => 9, 'title' => "Ook, Ook Eek Ook!", 'url' => "http://example.net/feed", 'source' => "http://example.net/", 'edited' => "1988-06-24 12:21:00", 'favicon' => ""],
            ['id' => 3, 'feed' => 1, 'title' => "The Last Soul",     'url' => "http://example.org/feed", 'source' => "http://example.org/", 'edited' => "1991-08-12 03:22:00", 'favicon' => "http://example.org/favicon.ico"],
        ]));
        \Phake::when(Arsse::$db)->tagSummarize(Arsse::$user->id)->thenReturn(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 3],
        ]));
        $exp = new JsonResponse([
            'feeds' => [
                ['id' => 1, 'favicon_id' => 5, 'title' => "Ankh-Morpork News", 'url' => "http://example.com/feed", 'site_url' => "http://example.com/", 'is_spark' => 0, 'last_updated_on_time' => strtotime("2019-01-01T21:12:00Z")],
                ['id' => 2, 'favicon_id' => 0, 'title' => "Ook, Ook Eek Ook!", 'url' => "http://example.net/feed", 'site_url' => "http://example.net/", 'is_spark' => 0, 'last_updated_on_time' => strtotime("1988-06-24T12:21:00Z")],
                ['id' => 3, 'favicon_id' => 1, 'title' => "The Last Soul",     'url' => "http://example.org/feed", 'site_url' => "http://example.org/", 'is_spark' => 0, 'last_updated_on_time' => strtotime("1991-08-12T03:22:00Z")],
            ],
            'feeds_groups' => [
                ['group_id' => 1, 'feed_ids' => "1,2"],
                ['group_id' => 2, 'feed_ids' => "1,3"],
            ],
        ]);
        $act = $this->req("api&feeds");
        $this->assertMessage($exp, $act);
    }

    /** @dataProvider provideItemListContexts */
    public function testListItems(string $url, Context $c, bool $desc) {
        $fields = ["id", "subscription", "title", "author", "content", "url", "starred", "unread", "published_date"];
        $order = [$desc ? "id desc" : "id"];
        \Phake::when(Arsse::$db)->articleList->thenReturn(new Result($this->articles['db']));
        \Phake::when(Arsse::$db)->articleCount($this->anything())->thenReturn(1024);
        $exp = new JsonResponse([
            'items' => $this->articles['rest'],
            'total_items' => 1024,
        ]);
        $act = $this->req("api&$url");
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db)->articleList($this->anything(), $c, $fields, $order);
    }

    public function provideItemListContexts() {
        $c = (new Context)->limit(50);
        return [
            ["items", (clone $c), false],
            ["items&group_ids=1,2,3,4", (clone $c)->tags([1,2,3,4]), false],
            ["items&feed_ids=1,2,3,4", (clone $c)->subscriptions([1,2,3,4]), false],
            ["items&with_ids=1,2,3,4", (clone $c)->articles([1,2,3,4]), false],
            ["items&since_id=1", (clone $c)->oldestArticle(2), false],
            ["items&max_id=2", (clone $c)->latestArticle(1), true],
            ["items&with_ids=1,2,3,4&max_id=6", (clone $c)->articles([1,2,3,4]), false],
            ["items&with_ids=1,2,3,4&since_id=6", (clone $c)->articles([1,2,3,4]), false],
            ["items&max_id=3&since_id=6", (clone $c)->latestArticle(2), true],
            ["items&feed_ids=1,2,3,4&since_id=6", (clone $c)->subscriptions([1,2,3,4])->oldestArticle(7), false],
        ];
    }

    public function testListItemIds() {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        \Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->starred(true))->thenReturn(new Result($saved));
        \Phake::when(Arsse::$db)->articleList($this->anything(), (new Context)->unread(true))->thenReturn(new Result($unread));
        $exp = new JsonResponse([
            'saved_item_ids' => "1,2,3"
        ]);
        $this->assertMessage($exp, $this->req("api&saved_item_ids"));
        $exp = new JsonResponse([
            'unread_item_ids' => "4,5,6"
        ]);
        $this->assertMessage($exp, $this->req("api&unread_item_ids"));
    }
}
