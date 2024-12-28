<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Fever;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\Fever\API;
use Psr\Http\Message\ResponseInterface;

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

    protected static function v($value) {
        return $value;
    }

    protected function req($dataGet, $dataPost = "", string $method = "POST", ?string $type = null, string $target = "", ?string $user = null): ResponseInterface {
        $prefix = "/fever/";
        $url = $prefix.$target;
        $type = $type ?? "application/x-www-form-urlencoded";
        return $this->hMock->dispatch($this->serverRequest($method, $url, $prefix, [], [], $dataPost, $type, $dataGet, $user));
    }

    public function setUp(): void {
        self::clearData();
        self::setConf();
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        Arsse::$user->id = $this->userId;
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->tokenLookup->thenReturn(['user' => "john.doe@example.com"]);
        // instantiate the handler as a partial mock to simplify testing
        $this->hMock = \Phake::partialMock(API::class);
        \Phake::when($this->hMock)->baseResponse->thenReturn([]);
    }

    /** @dataProvider provideTokenAuthenticationRequests */
    public function testAuthenticateAUserToken(bool $httpRequired, bool $tokenEnforced, ?string $httpUser, array $dataPost, array $dataGet, ResponseInterface $exp): void {
        self::setConf([
            'userHTTPAuthRequired' => $httpRequired,
            'userSessionEnforced'  => $tokenEnforced,
        ], true);
        Arsse::$user->id = null;
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("fever.login", "validtoken")->thenReturn(['user' => "jane.doe@example.com"]);
        // test only the authentication process
        \Phake::when($this->hMock)->baseResponse->thenReturnCallback(function(bool $authenticated) {
            return ['auth' => (int) $authenticated];
        });
        \Phake::when($this->hMock)->processRequest->thenReturnCallback(function($out, $G, $P) {
            return $out;
        });
        $this->assertMessage($exp, $this->req($dataGet, $dataPost, "POST", null, "", $httpUser));
    }

    public static function provideTokenAuthenticationRequests(): iterable {
        $success = HTTP::respJson(['auth' => 1]);
        $failure = HTTP::respJson(['auth' => 0]);
        $denied = HTTP::respEmpty(401);
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
        \Phake::when(Arsse::$db)->tagList($this->userId)->thenReturn(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscriptions' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscriptions' => 2],
            ['id' => 3, 'name' => "Boring",      'subscriptions' => 0],
        ]));
        \Phake::when(Arsse::$db)->tagSummarize($this->userId)->thenReturn(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 3],
        ]));
        $exp = HTTP::respJson([
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
        \Phake::when(Arsse::$db)->subscriptionList($this->userId)->thenReturn(new Result([
            ['id' => 1, 'feed' => 5, 'title' => "Ankh-Morpork News", 'url' => "http://example.com/feed", 'source' => "http://example.com/", 'edited' => "2019-01-01 21:12:00", 'icon_url' => "http://example.com/favicon.ico", 'icon_id' => 42],
            ['id' => 2, 'feed' => 9, 'title' => "Ook, Ook Eek Ook!", 'url' => "http://example.net/feed", 'source' => "http://example.net/", 'edited' => "1988-06-24 12:21:00", 'icon_url' => "",                               'icon_id' => null],
            ['id' => 3, 'feed' => 1, 'title' => "The Last Soul",     'url' => "http://example.org/feed", 'source' => "http://example.org/", 'edited' => "1991-08-12 03:22:00", 'icon_url' => "http://example.org/favicon.ico", 'icon_id' => 42],
        ]));
        \Phake::when(Arsse::$db)->tagSummarize($this->userId)->thenReturn(new Result([
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 1],
            ['id' => 1, 'name' => "Fascinating", 'subscription' => 2],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 1],
            ['id' => 2, 'name' => "Interesting", 'subscription' => 3],
        ]));
        $exp = HTTP::respJson([
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
        \Phake::when(Arsse::$db)->articleList->thenReturn(new Result($this->articles['db']));
        \Phake::when(Arsse::$db)->articleCount($this->userId, (new Context)->hidden(false))->thenReturn(1024);
        $exp = HTTP::respJson([
            'items'       => $this->articles['rest'],
            'total_items' => 1024,
        ]);
        $this->assertMessage($exp, $this->req("api&$url"));
        \Phake::verify(Arsse::$db)->articleList($this->userId, $this->equalTo($c), $fields, $order);
    }

    public static function provideItemListContexts(): iterable {
        $c = (new Context)->limit(50);
        return [
            ["items", (clone $c)->hidden(false), false],
            ["items&group_ids=1,2,3,4", (clone $c)->tags([1,2,3,4])->hidden(false), false],
            ["items&feed_ids=1,2,3,4", (clone $c)->subscriptions([1,2,3,4])->hidden(false), false],
            ["items&with_ids=1,2,3,4", (clone $c)->articles([1,2,3,4]), false],
            ["items&since_id=1", (clone $c)->articleRange(2, null)->hidden(false), false],
            ["items&max_id=2", (clone $c)->articleRange(null, 1)->hidden(false), true],
            ["items&with_ids=1,2,3,4&max_id=6", (clone $c)->articles([1,2,3,4]), false],
            ["items&with_ids=1,2,3,4&since_id=6", (clone $c)->articles([1,2,3,4]), false],
            ["items&max_id=3&since_id=6", (clone $c)->articleRange(null, 2)->hidden(false), true],
            ["items&feed_ids=1,2,3,4&since_id=6", (clone $c)->subscriptions([1,2,3,4])->articleRange(7, null)->hidden(false), false],
        ];
    }

    public function testListItemIds(): void {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->starred(true)->hidden(false))->thenReturn(new Result($saved));
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->unread(true)->hidden(false))->thenReturn(new Result($unread));
        $exp = HTTP::respJson(['saved_item_ids' => "1,2,3"]);
        $this->assertMessage($exp, $this->req("api&saved_item_ids"));
        $exp = HTTP::respJson(['unread_item_ids' => "4,5,6"]);
        $this->assertMessage($exp, $this->req("api&unread_item_ids"));
    }

    public function testListHotLinks(): void {
        // hot links are not actually implemented, so an empty array should be all we get
        $exp = HTTP::respJson(['links' => []]);
        $this->assertMessage($exp, $this->req("api&links"));
    }

    /** @dataProvider provideMarkingContexts */
    public function testSetMarks(string $post, Context $c, array $data, array $out): void {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->starred(true)->hidden(false))->thenReturn(new Result($saved));
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->unread(true)->hidden(false))->thenReturn(new Result($unread));
        \Phake::when(Arsse::$db)->articleMark->thenReturn(0);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), (new Context)->article(2112))->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = HTTP::respJson($out);
        $this->assertMessage($exp, $this->req("api", $post));
        if ($c && $data) {
            \Phake::verify(Arsse::$db)->articleMark($this->userId, $data, $this->equalTo($c));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    /** @dataProvider provideMarkingContexts */
    public function testSetMarksWithQuery(string $get, Context $c, array $data, array $out): void {
        $saved = [['id' => 1],['id' => 2],['id' => 3]];
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->starred(true)->hidden(false))->thenReturn(new Result($saved));
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->unread(true)->hidden(false))->thenReturn(new Result($unread));
        \Phake::when(Arsse::$db)->articleMark->thenReturn(0);
        \Phake::when(Arsse::$db)->articleMark($this->userId, $this->anything(), (new Context)->article(2112))->thenThrow(new \JKingWeb\Arsse\Db\ExceptionInput("subjectMissing"));
        $exp = HTTP::respJson($out);
        $this->assertMessage($exp, $this->req("api&$get"));
        if ($c && $data) {
            \Phake::verify(Arsse::$db)->articleMark($this->userId, $data, $this->equalTo($c));
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideMarkingContexts(): iterable {
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
            ["mark=group&as=read&id=-1&before=946684800", (new Context)->not->folder(0)->markedRange(null, "2000-01-01T00:00:00Z"), $markRead, $listUnread],
            ["mark=item&as=unread", new Context, [], []],
            ["mark=item&id=6", new Context, [], []],
            ["as=unread&id=6", new Context, [], []],
        ];
    }

    /** @dataProvider provideInvalidRequests */
    public function testSendInvalidRequests(string $get, string $post, string $method, ?string $type, ResponseInterface $exp): void {
        $this->assertMessage($exp, $this->req($get, $post, $method, $type));
    }

    public static function provideInvalidRequests(): iterable {
        return [
            'Not an API request'        => ["",    "",                         "POST", null,                                                                                                HTTP::respEmpty(404)],
            'Wrong method'              => ["api", "",                         "PUT",  null,                                                                                                HTTP::respEmpty(405, ['Allow' => "OPTIONS,POST"])],
            'Non-standard method'       => ["api", "",                         "GET",  null,                                                                                                HTTP::respJson([])],
            'Wrong content type'        => ["api", '{"api_key":"validToken"}', "POST", "application/json",                                                                                  HTTP::respJson([])], // some clients send nonsensical content types; Fever seems to have allowed this
            'Non-standard content type' => ["api", '{"api_key":"validToken"}', "POST", "multipart/form-data; boundary=33b68964f0de4c1f-5144aa6caaa6e4a8-18bfaf416a1786c8-5c5053a45f221bc1", HTTP::respJson([])], // some clients send nonsensical content types; Fever seems to have allowed this
        ];
    }

    public function testMakeABaseQuery(): void {
        \Phake::when($this->hMock)->baseResponse->thenCallParent();
        \Phake::when($this->hMock)->logIn->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionRefreshed($this->userId)->thenReturn(new \DateTimeImmutable("2000-01-01T00:00:00Z"));
        $exp = HTTP::respJson([
            'api_version'            => API::LEVEL,
            'auth'                   => 1,
            'last_refreshed_on_time' => 946684800,
        ]);
        $this->assertMessage($exp, $this->req("api"));
        \Phake::when(Arsse::$db)->subscriptionRefreshed($this->userId)->thenReturn(null); // no subscriptions
        $exp = HTTP::respJson([
            'api_version'            => API::LEVEL,
            'auth'                   => 1,
            'last_refreshed_on_time' => null,
        ]);
        $this->assertMessage($exp, $this->req("api"));
        \Phake::when($this->hMock)->logIn->thenReturn(false);
        $exp = HTTP::respJson([
            'api_version' => API::LEVEL,
            'auth'        => 0,
        ]);
        $this->assertMessage($exp, $this->req("api"));
    }

    public function testUndoReadMarks(): void {
        $unread = [['id' => 4],['id' => 5],['id' => 6]];
        $out = ['unread_item_ids' => "4,5,6"];
        \Phake::when(Arsse::$db)->articleList($this->userId, $this->equalTo((new Context)->limit(1)->hidden(false)), ["marked_date"], ["marked_date desc"])->thenReturn(new Result([['marked_date' => "2000-01-01 00:00:00"]]));
        \Phake::when(Arsse::$db)->articleList($this->userId, $this->equalTo((new Context)->unread(true)->hidden(false)))->thenReturn(new Result($unread));
        \Phake::when(Arsse::$db)->articleMark->thenReturn(0);
        $exp = HTTP::respJson($out);
        $this->assertMessage($exp, $this->req("api", ['unread_recently_read' => 1]));
        \Phake::verify(Arsse::$db)->articleMark($this->userId, ['read' => false], $this->equalTo((new Context)->unread(false)->markedRange("1999-12-31T23:59:45Z", null)->hidden(false)));
        \Phake::when(Arsse::$db)->articleList($this->userId, (new Context)->limit(1)->hidden(false), ["marked_date"], ["marked_date desc"])->thenReturn(new Result([]));
        $this->assertMessage($exp, $this->req("api", ['unread_recently_read' => 1]));
        \Phake::verify(Arsse::$db, \Phake::times(1))->articleMark(\Phake::anyParameters()); // only called one time, above
    }

    public function testOutputToXml(): void {
        \Phake::when($this->hMock)->processRequest->thenReturn([
            'items'       => $this->articles['rest'],
            'total_items' => 1024,
        ]);
        $exp = HTTP::respXml("<response><items><item><id>101</id><feed_id>8</feed_id><title>Article title 1</title><author></author><html>&lt;p&gt;Article content 1&lt;/p&gt;</html><url>http://example.com/1</url><is_saved>0</is_saved><is_read>0</is_read><created_on_time>946684800</created_on_time></item><item><id>102</id><feed_id>8</feed_id><title>Article title 2</title><author></author><html>&lt;p&gt;Article content 2&lt;/p&gt;</html><url>http://example.com/2</url><is_saved>0</is_saved><is_read>1</is_read><created_on_time>946771200</created_on_time></item><item><id>103</id><feed_id>9</feed_id><title>Article title 3</title><author></author><html>&lt;p&gt;Article content 3&lt;/p&gt;</html><url>http://example.com/3</url><is_saved>1</is_saved><is_read>0</is_read><created_on_time>946857600</created_on_time></item><item><id>104</id><feed_id>9</feed_id><title>Article title 4</title><author></author><html>&lt;p&gt;Article content 4&lt;/p&gt;</html><url>http://example.com/4</url><is_saved>1</is_saved><is_read>1</is_read><created_on_time>946944000</created_on_time></item><item><id>105</id><feed_id>10</feed_id><title>Article title 5</title><author></author><html>&lt;p&gt;Article content 5&lt;/p&gt;</html><url>http://example.com/5</url><is_saved>0</is_saved><is_read>0</is_read><created_on_time>947030400</created_on_time></item></items><total_items>1024</total_items></response>");
        $this->assertMessage($exp, $this->req("api=xml"));
    }

    public function testListFeedIcons(): void {
        $iconType = (new \ReflectionClassConstant(API::class, "GENERIC_ICON_TYPE"))->getValue();
        $iconData = (new \ReflectionClassConstant(API::class, "GENERIC_ICON_DATA"))->getValue();
        \Phake::when(Arsse::$db)->iconList->thenReturn(new Result(self::v([
            ['id' => 42, 'type' => "image/svg+xml", 'data' => "<svg/>"],
            ['id' => 44, 'type' => null,            'data' => "IMAGE DATA"],
            ['id' => 47, 'type' => null,            'data' => null],
        ])));
        $exp = HTTP::respJson(['favicons' => [
            ['id' => 0,  'data' => $iconType.",".$iconData],
            ['id' => 42, 'data' => "image/svg+xml;base64,PHN2Zy8+"],
            ['id' => 44, 'data' => "application/octet-stream;base64,SU1BR0UgREFUQQ=="],
        ]]);
        $this->assertMessage($exp, $this->req("api&favicons"));
    }

    public function testAnswerOptionsRequest(): void {
        $exp = HTTP::respEmpty(204, [
            'Allow'  => "POST",
            'Accept' => "application/x-www-form-urlencoded, multipart/form-data",
        ]);
        $this->assertMessage($exp, $this->req("api", "", "OPTIONS"));
    }
}
