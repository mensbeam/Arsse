<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Reader::class)]
class TestReader extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\REST\Reader\Common;

    protected const NOW = "2020-12-21T23:09:17.189065Z";
    /** @var Reader|\Phake\IMock|null */
    protected $h = null;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create mock timestamps
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth(\Phake::anyParameters())->thenReturn(true);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin(\Phake::anyParameters())->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->tokenCreate(\Phake::anyParameters())->thenReturn("12345");
        // create the reader class, with authentication stubbed out; for mysterious reasons Phake does not work reliably when mocking this class
        $this->h = $this->createPartialMock(Reader::class, ["authenticate", "shouldChallenge", "now"]);
        $this->h->method("authenticate")->willReturn(true);
        $this->h->method("shouldChallenge")->willReturn(false);
        $this->h->method("now")->willReturn(new \DateTimeImmutable(self::NOW));
    }

    protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface {
        if (strlen((string) $user)) {
            Arsse::$user->id = $user;
        }
        return $this->h->dispatch($this->serverRequest($method, "/api/greader.php/reader/api/0".$target, "/api/greader.php/reader/api/0", ['Accept' => "application/json"], [], $data, "application/x-www-form-urlencoded", [], $user));
    }

    #[DataProvider("provideMarkings")]
    public function testMarkArticles(string $body, ?array $data, ?Context $c, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->tokenLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("reader.post", "12345", $user)->thenReturn([]);
        \Phake::when(Arsse::$db)->articleMark(\Phake::anyParameters())->thenReturn(1);
        $act = $this->req("POST", "/edit-tag", $body, $user);
        $this->assertMessage($exp, $act);
        if ($data && $c) {
            \Phake::verify(Arsse::$db)->articleMark($user, $data, $c);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideMarkings(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        return [
            ["i=1&i=2&a=user/-/state/com.google/read&T=12345",             ['read' => true],     (new Context)->articles([1,2]), $success],
            ["i=3&i=4&r=user/-/state/com.google/unread&T=12345",           ['read' => true],     (new Context)->articles([3,4]), $success],
            ["i=3&i=4&r=user/-/state/com.google/kept-unread&T=12345",      ['read' => true],     (new Context)->articles([3,4]), $success],
            ["i=1&i=2&r=user/-/state/com.google/read&T=12345",             ['read' => false],    (new Context)->articles([1,2]), $success],
            ["i=3&i=4&a=user/-/state/com.google/unread&T=12345",           ['read' => false],    (new Context)->articles([3,4]), $success],
            ["i=3&i=4&a=user/-/state/com.google/kept-unread&T=12345",      ['read' => false],    (new Context)->articles([3,4]), $success],
            ["i=5&i=6&a=user/-/state/com.google/starred&T=12345",          ['starred' => true],  (new Context)->articles([5,6]), $success],
            ["i=5&i=6&r=user/-/state/com.google/starred&T=12345",          ['starred' => false], (new Context)->articles([5,6]), $success],
            ["i=7&i=8&a=user/-/state/org.freshrss/important&T=12345",      null,                 null,                           $success],
            ["i=7&i=8&a=user/-/state/ca.jking/bogus&T=12345",              null,                 null,                           self::respError(["InvalidStream", "user/-/state/ca.jking/bogus"])],
            ["i=7&i=8&a=not-a-state&T=12345",                              null,                 null,                           self::respError(["InvalidStream", "not-a-state"])],
            ["a=user/-/state/com.google/read&T=12345",                     null,                 null,                           self::respError(["ParameterRequired", "i"])],
            ["i=9&T=12345",                                                null,                 null,                           self::respError(["ParameterRequiredOneOfTwo", "a", "r"])],
            ["i=1&i=2&i=&a=user/-/state/com.google/read&T=12345",          ['read' => true],     (new Context)->articles([1,2]), $success],
            ["i=1&a=user/-/state/com.google/read",                         null,                 null,                           self::respError("TokenRequired", 400)],
        ];
    }

    public function testMarkArticlesMultipleWays(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->tokenLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("reader.post", "12345", $user)->thenReturn([]);
        \Phake::when(Arsse::$db)->articleMark(\Phake::anyParameters())->thenReturn(1);
        $body = "T=12345&i=1&i=2&r=user/-/state/com.google/starred&a=user/-/state/com.google/read";
        $c = (new Context)->articles([1, 2]);
        $act = $this->req("POST", "/edit-tag", $body, $user);
        $this->assertMessage(HTTP::respText("OK"), $act);
        \Phake::inOrder(
            \Phake::verify(Arsse::$db)->articleMark($user, ['read' => true], $c),
            \Phake::verify(Arsse::$db)->articleMark($user, ['starred' => false], $c),
        );
    }

    #[DataProvider("provideLabellings")]
    public function testModifyArticleLabels(string $body, ?array $data, ?string $addLabel, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        $labels = [
            ['id' => 1, 'name' => "Ook"],
            ['id' => 2, 'name' => "Eek"],
            ['id' => 3, 'name' => "Ack"],
        ];
        \Phake::when(Arsse::$db)->tokenLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("reader.post", "12345", $user)->thenReturn([]);
        \Phake::when(Arsse::$db)->labelList(\Phake::anyParameters())->thenReturn(new Result($labels));
        \Phake::when(Arsse::$db)->labelAdd(\Phake::anyParameters())->thenReturn(4);
        \Phake::when(Arsse::$db)->labelArticlesSet(\Phake::anyParameters())->thenReturn(1);
        $act = $this->req("POST", "/edit-tag", $body, $user);
        $this->assertMessage($exp, $act);
        if ($data) {
            \Phake::verify(Arsse::$db)->labelList($user, true);
            \Phake::verify(Arsse::$db)->labelArticlesSet($user, ...$data);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->labelList(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
        if ($addLabel) {
            \Phake::verify(Arsse::$db)->labelAdd($user, ['name' => $addLabel]);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->labelAdd(\Phake::anyParameters());
        }
    }

    public static function provideLabellings(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        return [
            ["T=12345&i=1&i=2&a=user/-/label/Ook",    ["Ook", (new Context)->articles([1 ,2]), Database::ASSOC_ADD, true],     null,   $success],
            ["T=12345&i=1&i=2&a=user/2112/label/Ook", ["Ook", (new Context)->articles([1 ,2]), Database::ASSOC_ADD, true],     null,   $success],
            ["T=12345&i=1&i=2&a=user/-/label/Boop",   ["Boop", (new Context)->articles([1 ,2]), Database::ASSOC_ADD, true],    "Boop", $success],
            ["T=12345&i=1&i=2&r=user/-/label/Boop",   ["Boop", (new Context)->articles([1 ,2]), Database::ASSOC_REMOVE, true], null,   $success],
        ];
    }

    public function testListTags(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$user)->propertiesGet(\Phake::anyParameters())->thenReturn([
            'num' => 2112,
        ]);
        \Phake::when(Arsse::$db)->tagList(\Phake::anyParameters())->thenReturn(new Result([
            ['id' => 1, 'name' => "Foo"],
            ['id' => 2, 'name' => "Bar"],
            ['id' => 3, 'name' => "Baz"],
        ]));
        \Phake::when(Arsse::$db)->labelList(\Phake::anyParameters())->thenReturn(new Result([
            ['id' => 1, 'name' => "Ook", 'articles' => 10, 'read' => 3],
            ['id' => 2, 'name' => "Eek", 'articles' => 20, 'read' => 2],
            ['id' => 3, 'name' => "Ack", 'articles' => 30, 'read' => 1],
        ]));
        $act = $this->req("GET", "/tag/list", "", $user);
        $exp = HTTP::respJson(['tags' => [
            ['id' => "user/2112/state/com.google/starred",      'sortid' => "00000000"],
            ['id' => "user/2112/state/com.google/reading-list", 'sortid' => "00000001"],
            ['id' => "user/2112/state/org.freshrss/main",       'sortid' => "00000002"],
            ['id' => "user/2112/state/org.freshrss/important",  'sortid' => "00000003"],
            ['id' => "user/2112/label/Foo",                     'sortid' => "00000004", 'type' => "folder"],
            ['id' => "user/2112/label/Bar",                     'sortid' => "00000005", 'type' => "folder"],
            ['id' => "user/2112/label/Baz",                     'sortid' => "00000006", 'type' => "folder"],
            ['id' => "user/2112/label/Ook",                     'sortid' => "00000007", 'type' => "tag", 'unread_count' => 7],
            ['id' => "user/2112/label/Eek",                     'sortid' => "00000008", 'type' => "tag", 'unread_count' => 18],
            ['id' => "user/2112/label/Ack",                     'sortid' => "00000009", 'type' => "tag", 'unread_count' => 29],
        ]]);
        $this->assertMessage($exp, $act);
    }

    #[DataProvider("provideTagRenamings")]
    public function testRenameTags(string $body, ?string $oldName, ?string $newName, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->tagPropertiesSet(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tagPropertiesSet($user, "Ook", ['name' => "Foo"], true)->thenReturn(true);
        \Phake::when(Arsse::$db)->tagPropertiesSet($user, "Eek", ['name' => "Bar"], true)->thenReturn(true);
        \Phake::when(Arsse::$db)->labelPropertiesSet(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->labelPropertiesSet($user, "Ook", ['name' => "Foo"], true)->thenReturn(true);
        $act = $this->req("POST", "/rename-tag", $body, $user);
        $this->assertMessage($exp, $act);
        if ($oldName && $newName) {
            \Phake::verify(Arsse::$db)->tagPropertiesSet($user, $oldName, ['name' => $newName], true);
            \Phake::verify(Arsse::$db)->labelPropertiesSet($user, $oldName, ['name' => $newName], true);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->tagPropertiesSet(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->labelPropertiesSet(\Phake::anyParameters());
        }
    }

    public static function provideTagRenamings(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        return [
            ["T=12345&s=user/-/label/Ook&dest=user/-/label/Foo",    "Ook", "Foo", $success],
            ["T=12345&s=user/-/label/Eek&dest=user/-/label/Bar",    "Eek", "Bar", $success],
            ["T=12345&s=user/-/label/Ack&dest=user/-/label/Baz",    "Ack", "Baz", self::respError(new ExceptionInput("subjectMissing"))],
            ["T=12345&s=user/2112/label/Ook&dest=user/-/label/Foo", "Ook", "Foo", $success],
            ["T=12345&s=user/2112/label/Eek&dest=user/-/label/Bar", "Eek", "Bar", $success],
            ["T=12345&s=user/2112/label/Ack&dest=user/-/label/Baz", "Ack", "Baz", self::respError(new ExceptionInput("subjectMissing"))],
            ["T=12345&t=Ook&dest=user/-/label/Foo",                 "Ook", "Foo", $success],
            ["T=12345&t=Eek&dest=user/-/label/Bar",                 "Eek", "Bar", $success],
            ["T=12345&t=Ack&dest=user/-/label/Baz",                 "Ack", "Baz", self::respError(new ExceptionInput("subjectMissing"))],
            ["T=12345&dest=user/-/label/Foo",                       null,  null,  self::respError(["ParameterRequiredOneOfTwo", "s", "t"])],
            ["T=12345&t=Ook",                                       null,  null,  self::respError(["ParameterRequired", "dest"])],
            ["T=12345&t=Ook&dest=Foo",                              null,  null,  self::respError(["InvalidStream", "Foo"])],
            ["T=12345&s=Ook&dest=user/-/label/Foo",                 null,  null,  self::respError(["InvalidStream", "Ook"])],
            ["t=Ook&dest=user/-/label/Foo",                         null,  null,  self::respError("TokenRequired", 400)],
        ];
    }

    #[DataProvider("provideTagRemovals")]
    public function testRemoveTags(string $body, ?string $removed, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->tagRemove(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tagRemove($user, "Ook", true)->thenReturn(true);
        \Phake::when(Arsse::$db)->tagRemove($user, "Eek", true)->thenReturn(true);
        \Phake::when(Arsse::$db)->labelRemove(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->labelRemove($user, "Ook", true)->thenReturn(true);
        $act = $this->req("POST", "/disable-tag", $body, $user);
        $this->assertMessage($exp, $act);
        if ($removed) {
            \Phake::verify(Arsse::$db)->tagRemove($user, $removed, true);
            \Phake::verify(Arsse::$db)->labelRemove($user, $removed, true);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->tagRemove(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->labelRemove(\Phake::anyParameters());
        }
    }

    public static function provideTagRemovals(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        return [
            ["T=12345&s=user/-/label/Ook", "Ook", $success],
            ["T=12345&s=user/-/label/Eek", "Eek", $success],
            ["T=12345&s=user/-/label/Ack", "Ack", self::respError(new ExceptionInput("subjectMissing"))],
            ["T=12345&t=Ook",              "Ook", $success],
            ["T=12345&t=Eek",              "Eek", $success],
            ["T=12345&t=Ack",              "Ack", self::respError(new ExceptionInput("subjectMissing"))],
            ["T=12345",                    null,  self::respError(["ParameterRequiredOneOfTwo", "s", "t"])],
            ["T=12345&s=Ook",              null,  self::respError(["InvalidStream", "Ook"])],
            ["s=user/-/label/Ook",         null,  self::respError("TokenRequired", 400)],
        ];
    }

    public function testListFriends(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$user)->propertiesGet(\Phake::anyParameters())->thenReturn([
            'num' => 2112,
        ]);
        $act = $this->req("GET", "/friend/list", "", $user);
        $exp = HTTP::respJson(['friends' => [
            [
                'userIds' => ["2112"],
                'profileIds' => ["2112"],
                'contactId' => "-1",
                'stream' => "user/2112/state/com.google/broadcast",
                'flags' => 1,
                'displayName' => "john.doe@example.com",
                'givenName' => "john.doe@example.com",
                'n' => "",
                'p' => "",
                'hasSharedItemsOnProfile' => false,
            ],
        ]]);
        $this->assertMessage($exp, $act);
    }

    public function testListPrefs(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/preference/list", "", $user);
        $exp = HTTP::respJson( [
            'prefs' => [
                [
                    'id' => "lhn-prefs",
                    'value' => '{"subscriptions":{"ssa":"true"}}',
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListPrefsStream(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/preference/stream/list", "", $user);
        $exp = HTTP::respJson( [
            'streamprefs' => new \stdClass,
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testGetUserInfo(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$user)->propertiesGet(\Phake::anyParameters())->thenReturn([
            'num' => 2112,
        ]);
        $act = $this->req("GET", "/user-info", "", $user);
        $exp = HTTP::respJson([
            'userName' => "john.doe@example.com",
            'userEmail' => "",
            'userId' => "2112",
            'userProfileId' => "2112",
            'isBloggerUser' => false,
            'signupTimeSec' => 1608592157,
            'isMultiLoginEnabled' => false,
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListUnreadCounts(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$user)->propertiesGet(\Phake::anyParameters())->thenReturn([
            'num' => 2112,
        ]);
        \Phake::when(Arsse::$db)->subscriptionList(\Phake::anyParameters())->thenReturn(new Result([
            ['id' => 1, 'unread' => 5,  'article_modified' => "2026-01-01 00:00:00"],
            ['id' => 2, 'unread' => 12, 'article_modified' => "2026-01-02 00:00:00"],
            ['id' => 3, 'unread' => 0,  'article_modified' => "2026-01-03 00:00:00"],
        ]));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook", 'subscription' => 3],
            ['name' => "Ook", 'subscription' => 1],
            ['name' => "Eek", 'subscription' => 2],
            ['name' => "Ack", 'subscription' => 3],
        ]));
        \Phake::when(Arsse::$db)->labelList(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Foo", 'articles' => 12, 'read' => 2,  'article_modified' => "2026-01-15 00:00:00"],
            ['name' => "Bar", 'articles' => 36, 'read' => 30, 'article_modified' => "2026-02-15 00:00:00"],
            ['name' => "Baz", 'articles' => 0,  'read' => 0,  'article_modified' => null],
        ]));
        $act = $this->req("GET", "/unread-count", "", $user);
        $exp = HTTP::respJson([
            'max' => 17,
            'unreadcounts' => [
                ['id' => "feed/1",                                  'count' => 5,  'newestItemTimestampUsec' => "1767225600000000"],
                ['id' => "feed/2",                                  'count' => 12, 'newestItemTimestampUsec' => "1767312000000000"],
                ['id' => "feed/3",                                  'count' => 0,  'newestItemTimestampUsec' => "1767398400000000"],
                ['id' => "user/2112/label/Ook",                     'count' => 5,  'newestItemTimestampUsec' => "1767398400000000"],
                ['id' => "user/2112/label/Eek",                     'count' => 12, 'newestItemTimestampUsec' => "1767312000000000"],
                ['id' => "user/2112/label/Ack",                     'count' => 0,  'newestItemTimestampUsec' => "1767398400000000"],
                ['id' => "user/2112/label/Foo",                     'count' => 10, 'newestItemTimestampUsec' => "1768435200000000"],
                ['id' => "user/2112/label/Bar",                     'count' => 6,  'newestItemTimestampUsec' => "1771113600000000"],
                ['id' => "user/2112/label/Baz",                     'count' => 0,  'newestItemTimestampUsec' => null],
                ['id' => "user/2112/state/com.google/reading-list", 'count' => 17, 'newestItemTimestampUsec' => "1767398400000000"]
            ]
        ]);
        $this->assertMessage($exp, $act);
    }
}
