<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\Fever\API;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\XmlResponse;
use Laminas\Diactoros\Response\EmptyResponse;

/** @covers \JKingWeb\Arsse\REST\Fever\API<extended> */
class TestAPI extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @var \JKingWeb\Arsse\REST\Fever\API */
    protected $h;
    protected $hMock;
    protected $userId = "john.doe@example.com";
    protected $articles = [
        'db' => [
            [
                'id'             => 101,
                'url'            => 'http://example.com/1',
                'title'          => 'Article title 1',
                'author'         => '',
                'content'        => '<p>Article content 1</p>',
                'published_date' => '2000-01-01 00:00:00',
                'unread'         => 1,
                'starred'        => 0,
                'subscription'   => 8,
            ],
            [
                'id'             => 102,
                'url'            => 'http://example.com/2',
                'title'          => 'Article title 2',
                'author'         => '',
                'content'        => '<p>Article content 2</p>',
                'published_date' => '2000-01-02 00:00:00',
                'unread'         => 0,
                'starred'        => 0,
                'subscription'   => 8,
            ],
            [
                'id'             => 103,
                'url'            => 'http://example.com/3',
                'title'          => 'Article title 3',
                'author'         => '',
                'content'        => '<p>Article content 3</p>',
                'published_date' => '2000-01-03 00:00:00',
                'unread'         => 1,
                'starred'        => 1,
                'subscription'   => 9,
            ],
            [
                'id'             => 104,
                'url'            => 'http://example.com/4',
                'title'          => 'Article title 4',
                'author'         => '',
                'content'        => '<p>Article content 4</p>',
                'published_date' => '2000-01-04 00:00:00',
                'unread'         => 0,
                'starred'        => 1,
                'subscription'   => 9,
            ],
            [
                'id'             => 105,
                'url'            => 'http://example.com/5',
                'title'          => 'Article title 5',
                'author'         => '',
                'content'        => '<p>Article content 5</p>',
                'published_date' => '2000-01-05 00:00:00',
                'unread'         => 1,
                'starred'        => 0,
                'subscription'   => 10,
            ],
        ],
        'rest' => [
            [
                'id'              => 101,
                'feed_id'         => 8,
                'title'           => 'Article title 1',
                'author'          => '',
                'html'            => '<p>Article content 1</p>',
                'url'             => 'http://example.com/1',
                'is_saved'        => 0,
                'is_read'         => 0,
                'created_on_time' => 946684800,
            ],
            [
                'id'              => 102,
                'feed_id'         => 8,
                'title'           => 'Article title 2',
                'author'          => '',
                'html'            => '<p>Article content 2</p>',
                'url'             => 'http://example.com/2',
                'is_saved'        => 0,
                'is_read'         => 1,
                'created_on_time' => 946771200,
            ],
            [
                'id'              => 103,
                'feed_id'         => 9,
                'title'           => 'Article title 3',
                'author'          => '',
                'html'            => '<p>Article content 3</p>',
                'url'             => 'http://example.com/3',
                'is_saved'        => 1,
                'is_read'         => 0,
                'created_on_time' => 946857600,
            ],
            [
                'id'              => 104,
                'feed_id'         => 9,
                'title'           => 'Article title 4',
                'author'          => '',
                'html'            => '<p>Article content 4</p>',
                'url'             => 'http://example.com/4',
                'is_saved'        => 1,
                'is_read'         => 1,
                'created_on_time' => 946944000,
            ],
            [
                'id'              => 105,
                'feed_id'         => 10,
                'title'           => 'Article title 5',
                'author'          => '',
                'html'            => '<p>Article content 5</p>',
                'url'             => 'http://example.com/5',
                'is_saved'        => 0,
                'is_read'         => 0,
                'created_on_time' => 947030400,
            ],
        ],
    ];

    protected function v($value) {
        return $value;
    }

    protected function req($dataGet, $dataPost = "", string $method = "POST", ?string $type = null, string $target = "", ?string $user = null): ResponseInterface {
        Arsse::$db = $this->dbMock->get();
        $this->h = $this->hMock->get();
        $prefix = "/fever/";
        $url = $prefix.$target;
        $type = $type ?? "application/x-www-form-urlencoded";
        return $this->h->dispatch($this->serverRequest($method, $url, $prefix, [], [], $dataPost, $type, $dataGet, $user));
    }

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock user manager
        $this->userMock = $this->mock(User::class);
        $this->userMock->auth->returns(true);
        Arsse::$user = $this->userMock->get();
        Arsse::$user->id = $this->userId;
        // create a mock database interface
        $this->dbMock = $this->mock(Database::class);
        $this->dbMock->begin->returns($this->mock(Transaction::class));
        $this->dbMock->tokenLookup->returns(['user' => "john.doe@example.com"]);
        // instantiate the handler as a partial mock to simplify testing
        $this->hMock = $this->partialMock(API::class);
        $this->hMock->baseResponse->returns([]);
    }

    /** @dataProvider provideTokenAuthenticationRequests */
    public function testAuthenticateAUserToken(bool $httpRequired, bool $tokenEnforced, string $httpUser = null, array $dataPost, array $dataGet, ResponseInterface $exp): void {
        self::setConf([
            'userHTTPAuthRequired' => $httpRequired,
            'userSessionEnforced'  => $tokenEnforced,
        ], true);
        Arsse::$user->id = null;
        $this->dbMock->tokenLookup->throws(new ExceptionInput("subjectMissing"));
        $this->dbMock->tokenLookup->with("fever.login", "validtoken")->returns(['user' => "jane.doe@example.com"]);
        // test only the authentication process
        $this->hMock->baseResponse->does(function(bool $authenticated) {
            return ['auth' => (int) $authenticated];
        });
        $this->hMock->processRequest->does(function($out, $G, $P) {
            return $out;
        });
        $this->assertMessage($exp, $this->req($dataGet, $dataPost, "POST", null, "", $httpUser));
    }

    public function provideTokenAuthenticationRequests(): iterable {
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

    public function testListGroups(): void {
        $this->dbMock->tagList->with($this->userId)->returns(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscriptions' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscriptions' => 2],
            ['id' => 3, 'name' => "Boring",      'subscriptions' => 0],
        ]));
        $this->dbMock->tagSummarize->with($this->userId)->returns(new Result([
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
        $this->assertMessage($exp, $this->req("api&groups"));
    }

    public function testListFeeds(): void {
        $this->dbMock->subscriptionList->with($this->userId)->returns(new Result([
            ['id' => 1, 'feed' => 5, 'title' => "Ankh-Morpork News", 'url' => "http://example.com/feed", 'source' => "http://example.com/", 'edited' => "2019-01-01 21:12:00", 'icon_url' => "http://example.com/favicon.ico", 'icon_id' => 42],
            ['id' => 2, 'feed' => 9, 'title' => "Ook, Ook Eek Ook!", 'url' => "http://example.net/feed", 'source' => "http://example.net/", 'edited' => "1988-06-24 12:21:00", 'icon_url' => "",                               'icon_id' => null],
            ['id' => 3, 'feed' => 1, 'title' => "The Last Soul",     'url' => "http://example.org/feed", 'source' => "http://example.org/", 'edited' => "1991-08-12 03:22:00", 'icon_url' => "http://example.org/favicon.ico", 'icon_id' => 42],
        ]));
        $this->dbMock->tagSummarize->with($this->userId)->returns(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 3],
        ]));
        $exp = new JsonResponse([
            'feeds' => [
                ['id' => 1, 'favicon_id' => 42, 'title' => "Ankh-Morpork News", 'url' => "http://example.com/feed", 'site_url' => "http://example.com/", 'is_spark' => 0, 'last_updated_on_time' => strtotime("2019-01-01T21:12:00Z")],
                ['id' => 2, 'favicon_id' => 0,  'title' => "Ook, Ook Eek Ook!", 'url' => "http://example.net/feed", 'site_url' => "http://example.net/", 'is_spark' => 0, 'last_updated_on_time' => strtotime("1988-06-24T12:21:00Z")],
                ['id' => 3, 'favicon_id' => 42, 'title' => "The Last Soul",     'url' => "http://example.org/feed", 'site_url' => "http://example.org/", 'is_spark' => 0, 'last_updated_on_time' => strtotime("1991-08-12T03:22:00Z")],
            ],
            'feeds_groups' => [
                ['group_id' => 1, 'feed_ids' => "1,2"],
                ['group_id' => 2, 'feed_ids' => "1,3"],
            ],
        ]);
        $this->assertMessage($exp, $this->req("api&feeds"));
    }

    /** @dataProvider provideItemListContexts */
    public function testListItems(string $url, Context $c, bool $desc): void {
        $fields = ["id", "subscription", "title", "author", "content", "url", "starred", "unread", "published_date"];
        $order = [$desc ? "id desc" : "id"];
        $this->dbMock->articleList->returns(new Result($this->articles['db']));
        $this->dbMock->articleCount->with($this->userId, (new Context)->hidden(false))->returns(1024);
        $exp = new JsonResponse([
            'items'       => $this->articles['rest'],
            'total_items' => 1024,
        ]);
        $this->assertMessage($exp, $this->req("api&$url"));
        $this->dbMock->articleList->calledWith($this->userId, $this->equalTo($c), $fields, $order);
    }

    public function provideItemListContexts(): iterable {
        $c = (new Context)->limit(50);
        return [
            ["items", (clone $c)->hidden(false), false],
            ["items&group_ids=1,2,3,4", (clone $c)->tags([1,2,3,4])->hidden(false), false],
            ["items&feed_ids=1,2,3,4", (clone $c)->subscriptions([1,2,3,4])->hidden(false), false],
            ["items&with_ids=1,2,3,4", (clone $c)->articles([1,2,3,4]), false],
            ["items&since_id=1", (clone $c)->oldestArticle(2)->hidden(false), false],
            ["items&max_id=2", (clone $c)->latestArticle(1)->hidden(false), true],
            ["items&with_ids=1,2,3,4&max_id=6", (clone $c)->articles([1,2,3,4]), false],
            ["items&with_ids=1,2,3,4&since_id=6", (clone $c)->articles([1,2,3,4]), false],
            ["items&max_id=3&since_id=6", (clone $c)->latestArticle(2)->hidden(false), true],
            ["items&feed_ids=1,2,3,4&since_id=6", (clone $c)->subscriptions([1,2,3,4])->oldestArticle(7)->hidden(false), false],
        ];
    }

    public function testListItemIds(): void {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        $this->dbMock->articleList->with($this->userId, (new Context)->starred(true)->hidden(false))->returns(new Result($saved));
        $this->dbMock->articleList->with($this->userId, (new Context)->unread(true)->hidden(false))->returns(new Result($unread));
        $exp = new JsonResponse(['saved_item_ids' => "1,2,3"]);
        $this->assertMessage($exp, $this->req("api&saved_item_ids"));
        $exp = new JsonResponse(['unread_item_ids' => "4,5,6"]);
        $this->assertMessage($exp, $this->req("api&unread_item_ids"));
    }

    public function testListHotLinks(): void {
        // hot links are not actually implemented, so an empty array should be all we get
        $exp = new JsonResponse(['links' => []]);
        $this->assertMessage($exp, $this->req("api&links"));
    }

    /** @dataProvider provideMarkingContexts */
    public function testSetMarks(string $post, Context $c, array $data, array $out): void {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        $this->dbMock->articleList->with($this->userId, (new Context)->starred(true)->hidden(false))->returns(new Result($saved));
        $this->dbMock->articleList->with($this->userId, (new Context)->unread(true)->hidden(false))->returns(new Result($unread));
        $this->dbMock->articleMark->returns(0);
        $this->dbMock->articleMark->with($this->userId, $this->anything(), (new Context)->article(2112))->throws(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = new JsonResponse($out);
        $this->assertMessage($exp, $this->req("api", $post));
        if ($c && $data) {
            $this->dbMock->articleMark->calledWith($this->userId, $data, $this->equalTo($c));
        } else {
            $this->dbMock->articleMark->never()->called();
        }
    }

    /** @dataProvider provideMarkingContexts */
    public function testSetMarksWithQuery(string $get, Context $c, array $data, array $out): void {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        $this->dbMock->articleList->with($this->userId, (new Context)->starred(true)->hidden(false))->returns(new Result($saved));
        $this->dbMock->articleList->with($this->userId, (new Context)->unread(true)->hidden(false))->returns(new Result($unread));
        $this->dbMock->articleMark->returns(0);
        $this->dbMock->articleMark->with($this->userId, $this->anything(), (new Context)->article(2112))->throws(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = new JsonResponse($out);
        $this->assertMessage($exp, $this->req("api&$get"));
        if ($c && $data) {
            $this->dbMock->articleMark->calledWith($this->userId, $data, $this->equalTo($c));
        } else {
            $this->dbMock->articleMark->never()->called();
        }
    }

    public function provideMarkingContexts(): iterable {
        $markRead = ['read' => true];
        $markUnread = ['read' => false];
        $markSaved = ['starred' => true];
        $markUnsaved = ['starred' => false];
        $listSaved = ['saved_item_ids' => "1,2,3"];
        $listUnread = ['unread_item_ids' => "4,5,6"];
        return [
            ["mark=item&as=read&id=5", (new Context)->article(5), $markRead, $listUnread],
            ["mark=item&as=unread&id=42", (new Context)->article(42), $markUnread, $listUnread],
            ["mark=item&as=read&id=2112", (new Context)->article(2112), $markRead, $listUnread], // article doesn't exist
            ["mark=item&as=saved&id=5", (new Context)->article(5), $markSaved, $listSaved],
            ["mark=item&as=unsaved&id=42", (new Context)->article(42), $markUnsaved, $listSaved],
            ["mark=feed&as=read&id=5", (new Context)->subscription(5)->hidden(false), $markRead, $listUnread],
            ["mark=feed&as=unread&id=42", (new Context)->subscription(42)->hidden(false), $markUnread, $listUnread],
            ["mark=feed&as=saved&id=5", (new Context)->subscription(5)->hidden(false), $markSaved, $listSaved],
            ["mark=feed&as=unsaved&id=42", (new Context)->subscription(42)->hidden(false), $markUnsaved, $listSaved],
            ["mark=group&as=read&id=5", (new Context)->tag(5)->hidden(false), $markRead, $listUnread],
            ["mark=group&as=unread&id=42", (new Context)->tag(42)->hidden(false), $markUnread, $listUnread],
            ["mark=group&as=saved&id=5", (new Context)->tag(5)->hidden(false), $markSaved, $listSaved],
            ["mark=group&as=unsaved&id=42", (new Context)->tag(42)->hidden(false), $markUnsaved, $listSaved],
            ["mark=item&as=invalid&id=42", new Context, [], []],
            ["mark=invalid&as=unread&id=42", new Context, [], []],
            ["mark=group&as=read&id=0", (new Context)->hidden(false), $markRead, $listUnread],
            ["mark=group&as=unread&id=0", (new Context)->hidden(false), $markUnread, $listUnread],
            ["mark=group&as=saved&id=0", (new Context)->hidden(false), $markSaved, $listSaved],
            ["mark=group&as=unsaved&id=0", (new Context)->hidden(false), $markUnsaved, $listSaved],
            ["mark=group&as=read&id=-1", (new Context)->not->folder(0), $markRead, $listUnread],
            ["mark=group&as=unread&id=-1", (new Context)->not->folder(0), $markUnread, $listUnread],
            ["mark=group&as=saved&id=-1", (new Context)->not->folder(0), $markSaved, $listSaved],
            ["mark=group&as=unsaved&id=-1", (new Context)->not->folder(0), $markUnsaved, $listSaved],
            ["mark=group&as=read&id=-1&before=946684800", (new Context)->not->folder(0)->notMarkedSince("2000-01-01T00:00:00Z"), $markRead, $listUnread],
            ["mark=item&as=unread", new Context, [], []],
            ["mark=item&id=6", new Context, [], []],
            ["as=unread&id=6", new Context, [], []],
        ];
    }

    /** @dataProvider provideInvalidRequests */
    public function testSendInvalidRequests(string $get, string $post, string $method, ?string $type, ResponseInterface $exp): void {
        $this->assertMessage($exp, $this->req($get, $post, $method, $type));
    }

    public function provideInvalidRequests(): iterable {
        return [
            'Not an API request'        => ["",    "",                         "POST", null,                                                                                                new EmptyResponse(404)],
            'Wrong method'              => ["api", "",                         "PUT",  null,                                                                                                new EmptyResponse(405, ['Allow' => "OPTIONS,POST"])],
            'Non-standard method'       => ["api", "",                         "GET",  null,                                                                                                new JsonResponse([])],
            'Wrong content type'        => ["api", '{"api_key":"validToken"}', "POST", "application/json",                                                                                  new JsonResponse([])], // some clients send nonsensical content types; Fever seems to have allowed this
            'Non-standard content type' => ["api", '{"api_key":"validToken"}', "POST", "multipart/form-data; boundary=33b68964f0de4c1f-5144aa6caaa6e4a8-18bfaf416a1786c8-5c5053a45f221bc1", new JsonResponse([])], // some clients send nonsensical content types; Fever seems to have allowed this
        ];
    }

    public function testMakeABaseQuery(): void {
        $this->hMock->baseResponse->forwards();
        $this->hMock->logIn->returns(true);
        $this->dbMock->subscriptionRefreshed->with($this->userId)->returns(new \DateTimeImmutable("2000-01-01T00:00:00Z"));
        $exp = new JsonResponse([
            'api_version'            => API::LEVEL,
            'auth'                   => 1,
            'last_refreshed_on_time' => 946684800,
        ]);
        $this->assertMessage($exp, $this->req("api"));
        $this->dbMock->subscriptionRefreshed->with($this->userId)->returns(null); // no subscriptions
        $exp = new JsonResponse([
            'api_version'            => API::LEVEL,
            'auth'                   => 1,
            'last_refreshed_on_time' => null,
        ]);
        $this->assertMessage($exp, $this->req("api"));
        $this->hMock->logIn->returns(false);
        $exp = new JsonResponse([
            'api_version' => API::LEVEL,
            'auth'        => 0,
        ]);
        $this->assertMessage($exp, $this->req("api"));
    }

    public function testUndoReadMarks(): void {
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        $out = ['unread_item_ids' => "4,5,6"];
        $this->dbMock->articleList->with($this->userId, $this->equalTo((new Context)->limit(1)->hidden(false)), ["marked_date"], ["marked_date desc"])->returns(new Result([['marked_date' => "2000-01-01 00:00:00"]]));
        $this->dbMock->articleList->with($this->userId, $this->equalTo((new Context)->unread(true)->hidden(false)))->returns(new Result($unread));
        $this->dbMock->articleMark->returns(0);
        $exp = new JsonResponse($out);
        $this->assertMessage($exp, $this->req("api", ['unread_recently_read' => 1]));
        $this->dbMock->articleMark->calledWith($this->userId, ['read' => false], $this->equalTo((new Context)->unread(false)->markedSince("1999-12-31T23:59:45Z")->hidden(false)));
        $this->dbMock->articleList->with($this->userId, (new Context)->limit(1)->hidden(false), ["marked_date"], ["marked_date desc"])->returns(new Result([]));
        $this->assertMessage($exp, $this->req("api", ['unread_recently_read' => 1]));
        $this->dbMock->articleMark->once()->called(); // only called one time, above
    }

    public function testOutputToXml(): void {
        $this->hMock->processRequest->returns([
            'items'       => $this->articles['rest'],
            'total_items' => 1024,
        ]);
        $exp = new XmlResponse("<response><items><item><id>101</id><feed_id>8</feed_id><title>Article title 1</title><author></author><html>&lt;p&gt;Article content 1&lt;/p&gt;</html><url>http://example.com/1</url><is_saved>0</is_saved><is_read>0</is_read><created_on_time>946684800</created_on_time></item><item><id>102</id><feed_id>8</feed_id><title>Article title 2</title><author></author><html>&lt;p&gt;Article content 2&lt;/p&gt;</html><url>http://example.com/2</url><is_saved>0</is_saved><is_read>1</is_read><created_on_time>946771200</created_on_time></item><item><id>103</id><feed_id>9</feed_id><title>Article title 3</title><author></author><html>&lt;p&gt;Article content 3&lt;/p&gt;</html><url>http://example.com/3</url><is_saved>1</is_saved><is_read>0</is_read><created_on_time>946857600</created_on_time></item><item><id>104</id><feed_id>9</feed_id><title>Article title 4</title><author></author><html>&lt;p&gt;Article content 4&lt;/p&gt;</html><url>http://example.com/4</url><is_saved>1</is_saved><is_read>1</is_read><created_on_time>946944000</created_on_time></item><item><id>105</id><feed_id>10</feed_id><title>Article title 5</title><author></author><html>&lt;p&gt;Article content 5&lt;/p&gt;</html><url>http://example.com/5</url><is_saved>0</is_saved><is_read>0</is_read><created_on_time>947030400</created_on_time></item></items><total_items>1024</total_items></response>");
        $this->assertMessage($exp, $this->req("api=xml"));
    }

    public function testListFeedIcons(): void {
        $iconType = (new \ReflectionClassConstant(API::class, "GENERIC_ICON_TYPE"))->getValue();
        $iconData = (new \ReflectionClassConstant(API::class, "GENERIC_ICON_DATA"))->getValue();
        $this->dbMock->iconList->returns(new Result($this->v([
            ['id' => 42, 'type' => "image/svg+xml", 'data' => "<svg/>"],
            ['id' => 44, 'type' => null,            'data' => "IMAGE DATA"],
            ['id' => 47, 'type' => null,            'data' => null],
        ])));
        $exp = new JsonResponse(['favicons' => [
            ['id' => 0,  'data' => $iconType.",".$iconData],
            ['id' => 42, 'data' => "image/svg+xml;base64,PHN2Zy8+"],
            ['id' => 44, 'data' => "application/octet-stream;base64,SU1BR0UgREFUQQ=="],
        ]]);
        $this->assertMessage($exp, $this->req("api&favicons"));
    }

    public function testAnswerOptionsRequest(): void {
        $exp = new EmptyResponse(204, [
            'Allow'  => "POST",
            'Accept' => "application/x-www-form-urlencoded, multipart/form-data",
        ]);
        $this->assertMessage($exp, $this->req("api", "", "OPTIONS"));
    }
}
