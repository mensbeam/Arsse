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
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\ImportExport\Exception as ImportException;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\FreshRSS\Reader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\ResponseInterface;

abstract class TestReader extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\REST\Reader\Common;

    protected const NOW = "2020-12-21T23:09:17.189065Z";
    /** @var Reader|\PHPUnit\Framework\MockObject\MockObject|null */
    protected $h = null;

    abstract protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface;
    abstract public function testIssuePostTokens(bool $existing): void;
    abstract public static function provideTokenChecks(): iterable;

    public function setUpTest(string $class): void {
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
        \Phake::when(Arsse::$db)->tokenLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("reader.post", "12345", "john.doe@example.com")->thenReturn([]);
        // create the reader class, with authentication stubbed out; for mysterious reasons Phake does not work reliably when mocking this class
        $this->h = $this->createPartialMock($class, ["authenticate", "shouldChallenge", "now"]);
        $this->h->method("authenticate")->willReturn(true);
        $this->h->method("shouldChallenge")->willReturn(false);
        $this->h->method("now")->willReturn(new \DateTimeImmutable(self::NOW));
    }

    #[DataProvider("provideInvalidCalls")]
    public function testMakeInvalidCalls(string $url, string $method, ResponseInterface $exp): void {
        // make sure any valid calls don't actually proceed past authentication
        $this->h->method("authenticate")->willReturn(false);
        $this->h->method("shouldChallenge")->willReturn(true);
        // perform the test
        $act = $this->req($method, $url);
        $this->assertMessage($exp, $act);
    }

    public static function provideInvalidCalls(): iterable {
        $r404 = HTTP::respEmpty(404);
        $r405G = HTTP::respEmpty(405, ['Allow' => "GET"]);
        $r405P = HTTP::respEmpty(405, ['Allow' => "POST"]);
        $r405B = HTTP::respEmpty(405, ['Allow' => "GET, POST"]);
        return [
            ["/bogus",                 "GET", $r404],
            ["/user-info",             "ACK", $r405G],
            ["/edit-tag",              "ACK", $r405P],
            ["/stream/items/contents", "ACK", $r405B],
        ];
    }

    #[DataProvider("provideOptionCalls")]
    public function testMakeOptionCalls(string $url, ResponseInterface $exp): void {
        // OPTIONS requests should succeed regardless of authentication
        $this->h->method("authenticate")->willReturn(false);
        $this->h->method("shouldChallenge")->willReturn(true);
        // perform the test
        $act = $this->req("OPTIONS", $url);
        $this->assertMessage($exp, $act);
    }

    public static function provideOptionCalls(): iterable {
        $r404 = HTTP::respEmpty(404);
        $r405G = HTTP::respEmpty(204, ['Allow' => "GET",       'Accept' => "x-www-form-urlencoded"]);
        $r405P = HTTP::respEmpty(204, ['Allow' => "POST",      'Accept' => "x-www-form-urlencoded"]);
        $r405B = HTTP::respEmpty(204, ['Allow' => "GET, POST", 'Accept' => "x-www-form-urlencoded"]);
        return [
            ["/bogus",                 $r404],
            ["/user-info",             $r405G],
            ["/edit-tag",              $r405P],
            ["/stream/items/contents", $r405B],
            ["/subscription/import",   HTTP::respEmpty(204, ['Allow' => "POST", 'Accept' => "application/xml, text/xml, text/x-opml"])],
        ];
    }

    #[DataProvider("provideAuthentications")]
    public function testAuthenticate(?string $basicAuthResult, bool $sessionEnforced, $authorization, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        // set up a mock handler for POST token creation which always succeeds
        $this->h = $this->createPartialMock(Reader::class, ["tokenCreate"]);
        $this->h->method("tokenCreate")->willReturn(HTTP::respText("TOKEN\n"));
        // set up mock protocol-level authentication
        \Phake::when(Arsse::$db)->tokenLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("reader.login", "OPEN_SESAME")->thenReturn(['user' => $user]);
        // confirm that the user name is not currently set
        $this->assertNull(Arsse::$user->id);
        // perform the test
        $req = $this->serverRequest("GET", "/api/greader.php/reader/api/0/token", "/api/greader.php/reader/api/0", ['Authorization' => $authorization], [], null, "", [], $basicAuthResult);
        Arsse::$conf->userSessionEnforced = $sessionEnforced;
        $act = $this->h->dispatch($req);
        $this->assertMessage($exp, $act);
    }

    public static function provideAuthentications(): iterable {
        self::clearData(); // initializes string formatter
        $user = "john.doe@example.com";
        $success = HTTP::respText("TOKEN\n");
        return [
            [null,  true,  "GoogleLogin auth=OPEN_SESAME",   $success],
            [null,  true,  "GoogleLogin  auth=OPEN_SESAME ", $success],
            [$user, false, null,                             $success],
            ["",    false, "GoogleLogin auth=OPEN_SESAME",   self::respError("401", 401)],
            [$user, true,  null,                             self::respError("401", 401, ['WWW-Authenticate' => "GoogleLogin"])],
            [null,  false, null,                             self::respError("401", 401, ['WWW-Authenticate' => ["GoogleLogin", 'Basic realm="The Advanced RSS Environment", charset="UTF-8"']])],
            [null,  true,  "GoogleLogin auth=BOGUS",         self::respError("401", 401, ['WWW-Authenticate' => "GoogleLogin"])],
            [null,  false, "GoogleLogin auth=BOGUS",         self::respError("401", 401, ['WWW-Authenticate' => ["GoogleLogin", 'Basic realm="The Advanced RSS Environment", charset="UTF-8"']])],
        ];
    }

    #[DataProvider("provideTokenChecks")]
    public function testCheckPostTokens(string $class, string $target, string $body, ResponseInterface $exp): void {
        // set up mocks for all the functions which require token authentication
        $functions = ["tagDisable", "tagEdit", "streamMark", "tagRename", "subscriptionEdit", "subscriptionAdd"];
        $this->h = $this->createPartialMock($class, array_merge($functions, ["authenticate", "shouldChallenge"]));
        $this->h->method("authenticate")->willReturn(true);
        $this->h->method("shouldChallenge")->willReturn(false);
        foreach ($functions as $f) {
            $this->h->method($f)->willReturn(HTTP::respText("OK"));
        }
        // perform the test
        $user = "john.doe@example.com";
        $act = $this->req("POST", $target, $body, $user);
        $this->assertMessage($exp, $act);
    }

    #[DataProvider("provideMarkings")]
    public function testMarkArticles(string $body, ?array $data, ?Context $c, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
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
            ["i=7&i=8&a=user/-/state/com.google/reading-list&T=12345",     null,                 null,                           $success],
            ["i=7&i=8&a=user/-/state/org.freshrss/important&T=12345",      null,                 null,                           $success],
            ["i=7&i=8&a=user/-/state/ca.jking/bogus&T=12345",              null,                 null,                           self::respError(["InvalidStream", "user/-/state/ca.jking/bogus"])],
            ["i=7&i=8&a=not-a-state&T=12345",                              null,                 null,                           self::respError(["InvalidStream", "not-a-state"])],
            ["a=user/-/state/com.google/read&T=12345",                     null,                 null,                           self::respError(["ParameterRequired", "i"])],
            ["i=9&T=12345",                                                null,                 null,                           self::respError(["ParameterRequiredOneOfTwo", "a", "r"])],
            ["i=1&i=2&i=&a=user/-/state/com.google/read&T=12345",          ['read' => true],     (new Context)->articles([1,2]), $success],
        ];
    }

    public function testMarkArticlesMultipleWays(): void {
        $user = "john.doe@example.com";
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

    #[DataProvider("provideStreamMarkings")]
    public function testMarkStreamsAsRead(string $data, ?Context $context, bool $success, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        if ($success) {
            \Phake::when(Arsse::$db)->articleMark(\Phake::anyParameters())->thenReturn(1);
        } else {
            \Phake::when(Arsse::$db)->articleMark(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        }
        $act = $this->req("POST", "/mark-all-as-read", $data, $user);
        $this->assertMessage($exp, $act);
        if ($context) {
            \Phake::verify(Arsse::$db)->articleMark($user, ['read' => true], $context);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleMark(\Phake::anyParameters());
        }
    }

    public static function provideStreamMarkings(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        return [
            ["T=12345&s=feed/1&ts=0000000",          (new Context)->subscription(1)->modifiedRange(null, "1970-01-01T00:00:00"), true,  $success],
            ["T=12345&s=feed/1&ts=0",                (new Context)->subscription(1)->modifiedRange(null, "1970-01-01T00:00:00"), true,  $success],
            ["T=12345&s=feed/1&ts=1784195407000000", (new Context)->subscription(1)->modifiedRange(null, "2026-07-16T09:50:07"), true,  $success],
            ["T=12345&s=feed/1&ts=1784195407",       (new Context)->subscription(1)->modifiedRange(null, "1970-01-01T00:29:44"), true,  $success],
            ["T=12345&s=feed/1",                     (new Context)->subscription(1),                                             true,  $success],
            ["T=12345&s=feed/1&ts=1784195407",       (new Context)->subscription(1)->modifiedRange(null, "1970-01-01T00:29:44"), false, self::respError(new ExceptionInput("subjectMissing"))],
            ["T=12345&ts=1784195407000000",          null,                                                                       true,  self::respError(["ParameterRequired", "s"])],
        ];
    }

    #[DataProvider("provideLabellings")]
    public function testModifyArticleLabels(string $body, ?array $data, ?string $addLabel, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        $labels = [
            ['id' => 1, 'name' => "Ook"],
            ['id' => 2, 'name' => "Eek"],
            ['id' => 3, 'name' => "Ack"],
        ];
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
            ["T=56789&i=1&i=2&r=user/-/label/Boop",   null,                                                                    null,   self::respError("401", 401, ['X-Reader-Google-Bad-Token' => "true"])],
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

    #[DataProvider("provideQuickadds")]
    public function testQuickAddASubscription(string $body, ?string $url, ?int $id, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->subscriptionAdd(\Phake::anyParameters())->thenThrow(new FeedException("subscriptionNotFound"));
        \Phake::when(Arsse::$db)->subscriptionAdd($user, "http://example.com/", true)->thenThrow(new ExceptionInput("constraintViolation"));
        \Phake::when(Arsse::$db)->subscriptionAdd($user, "http://example.net/", true)->thenReturn(3);
        \Phake::when(Arsse::$db)->subscriptionAdd($user, "http://example.org/", true)->thenReturn(4);
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet($user, 3)->thenReturn(['id' => 3, 'url' => "https://example.net/atom", 'title' => "Ook"]);
        \Phake::when(Arsse::$db)->subscriptionPropertiesGet($user, 4)->thenReturn(['id' => 4, 'url' => "https://example.org/rss",  'title' => null]);
        $act = $this->req("POST", "/subscription/quickadd", $body, $user);
        $this->assertMessage($exp, $act);
        if ($url) {
            \Phake::verify(Arsse::$db)->subscriptionAdd($user, $url, true);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionAdd(\Phake::anyParameters());
        }
        if ($id) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesGet($user, $id);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesGet(\Phake::anyParameters());
        }
    }

    public static function provideQuickadds(): iterable {
        self::clearData(); // initializes string formatter
        return [
            ["T=12345&quickadd=http://example.com/",      "http://example.com/", null, HTTP::respJson(['numResults' => 0, 'query' => "http://example.com/", 'error' => Arsse::$lang->msg("API.Reader.Error.DuplicateSubscription", ['url' => "http://example.com/"])], 400)],
            ["T=12345&quickadd=http://example.biz/",      "http://example.biz/", null, HTTP::respJson(['numResults' => 0, 'query' => "http://example.biz/", 'error' => (new FeedException("subscriptionNotFound"))->getMessage()], 400)],
            ["T=12345&quickadd=http://example.net/",      "http://example.net/", 3,    HTTP::respJson(['numResults' => 1, 'query' => "https://example.net/atom", 'streamId' => "feed/3", 'streamName' => "Ook"], 200)],
            ["T=12345&quickadd=http://example.org/",      "http://example.org/", 4,    HTTP::respJson(['numResults' => 1, 'query' => "https://example.org/rss", 'streamId' => "feed/4", 'streamName' => ""], 200)],
            ["T=12345&quickadd=feed/http://example.net/", "http://example.net/", 3,    HTTP::respJson(['numResults' => 1, 'query' => "https://example.net/atom", 'streamId' => "feed/3", 'streamName' => "Ook"], 200)],
            ["T=12345&quickadd=feed/http://example.org/", "http://example.org/", 4,    HTTP::respJson(['numResults' => 1, 'query' => "https://example.org/rss", 'streamId' => "feed/4", 'streamName' => ""], 200)],
            ["T=12345",                                   null,                  null, self::respError(["ParameterRequired", "quickadd"])],
        ];
    }

    #[DataProvider("provideSubscriptionEdits")]
    public function testEditASubscription(string $body, ?string $urlReserve, ?string $urlLookup, ?int $idEdit, ?int $idUnsub, ?string $title, array $tagAdditions, array $tagCreations, array $tagRemovals, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->subscriptionReserve(\Phake::anyParameters())->thenThrow(new FeedException("subscriptionNotFound"));
        \Phake::when(Arsse::$db)->subscriptionReserve($user, "http://example.com/", true)->thenReturn(1);
        \Phake::when(Arsse::$db)->subscriptionReserve($user, "http://example.org/", true)->thenThrow(new ExceptionInput("constraintViolation"));
        \Phake::when(Arsse::$db)->subscriptionRemove(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionRemove($user, 2112)->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->subscriptionLookup($user, "http://example.biz/")->thenReturn(2112);
        \Phake::when(Arsse::$db)->subscriptionPropertiesSet(\Phake::anyParameters())->thenReturn(true);
        \Phake::when(Arsse::$db)->tagList(\Phake::anyParameters())->thenReturn(new Result([
            ['id' => 1, 'name' => "Foo"],
            ['id' => 2, 'name' => "Bar"],
            ['id' => 3, 'name' => "Baz"],
        ]));
        \Phake::when(Arsse::$db)->tagAdd(\Phake::anyParameters())->thenReturn(0);
        foreach ($tagCreations as $tagId => $tagName) {
            \Phake::when(Arsse::$db)->tagAdd($user, ['name' => $tagName])->thenReturn($tagId);
        }
        \Phake::when(Arsse::$db)->tagSubscriptionsSet(\Phake::anyParameters())->thenReturn(1);
        \Phake::when(Arsse::$db)->tagSubscriptionsSet($user, "Ack", [$idEdit], Database::ASSOC_REMOVE, true)->thenThrow(new ExceptionInput("subjectMissing"));
        $act = $this->req("POST", "/subscription/edit", $body, $user);
        $this->assertMessage($exp, $act);
        if ($urlReserve) {
            \Phake::verify(Arsse::$db)->subscriptionReserve($user, $urlReserve, true);
            if ($idEdit) {
                \Phake::verify(Arsse::$db)->subscriptionReveal($user, $idEdit);
            } else {
                \Phake::verify(Arsse::$db, \Phake::never())->subscriptionReveal(\Phake::anyParameters());
            }
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionReserve(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionReveal(\Phake::anyParameters());
        }
        if ($urlLookup) {
            \Phake::verify(Arsse::$db)->subscriptionLookup($user, $urlLookup);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionLookup(\Phake::anyParameters());
        }
        if ($title) {
            if ($urlReserve) {
                \Phake::verify(Arsse::$db)->subscriptionPropertiesSet($user, $idEdit, ['title' => $title], true);
            } else {
                \Phake::verify(Arsse::$db)->subscriptionPropertiesSet($user, $idEdit, ['title' => $title]);
            }
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesSet(\Phake::anyParameters());
        }
        if ($tagAdditions || $tagRemovals) {
            if ($tagAdditions) {
                \Phake::verify(Arsse::$db)->tagList($user, true);
                foreach ($tagAdditions as $tagId) {
                    \Phake::verify(Arsse::$db)->tagSubscriptionsSet($user, $tagId, [$idEdit]);
                }
                if ($tagCreations) {
                    foreach ($tagCreations as $tagId => $tagName) {
                        \Phake::verify(Arsse::$db)->tagAdd($user, ['name' => $tagName]);
                    }
                } else {
                    \Phake::verify(Arsse::$db, \Phake::never())->tagAdd(\Phake::anyParameters());
                }
            } else {
                \Phake::verify(Arsse::$db, \Phake::never())->tagList(\Phake::anyParameters());
                \Phake::verify(Arsse::$db, \Phake::never())->tagAdd(\Phake::anyParameters());
            }
            foreach ($tagRemovals as $tagName) {
                \Phake::verify(Arsse::$db)->tagSubscriptionsSet($user, $tagName, [$idEdit], Database::ASSOC_REMOVE, true);
            }
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->tagList(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->tagAdd(\Phake::anyParameters());
            \Phake::verify(Arsse::$db, \Phake::never())->tagSubscriptionsSet(\Phake::anyParameters());
        }
        \Phake::verify(Arsse::$db, \Phake::never())->tagAdd($user, ['name' => "Foo"]);
        \Phake::verify(Arsse::$db, \Phake::never())->tagAdd($user, ['name' => "Bar"]);
        \Phake::verify(Arsse::$db, \Phake::never())->tagAdd($user, ['name' => "Baz"]);
        if ($idUnsub) {
            \Phake::verify(Arsse::$db)->subscriptionRemove($user, $idUnsub);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionRemove(\Phake::anyParameters());
        }
    }

    public static function provideSubscriptionEdits(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        return [ // request body                                                                            reservation URL        lookup URL             edit ID  unsub ID  new title  add tags  create tags   remove tags     expected output
            ["T=12345&ac=subscribe&s=feed/http://example.com/&t=Ook&a=user/-/label/Eek&a=user/-/label/Foo", "http://example.com/", null,                  1,       null,     "Ook",     [4, 1],   [4 => "Eek"], [],             $success],
            ["T=12345&ac=subscribe&s=feed/http://example.com/&t=Ook",                                       "http://example.com/", null,                  1,       null,     "Ook",     [],       [],           [],             $success],
            ["T=12345&ac=subscribe&s=feed/http://example.org/&t=Eek",                                       "http://example.org/", null,                  null,    null,     null,      [],       [],           [],             self::respError(["DuplicateSubscription", 'url' => "http://example.org/"])],
            ["T=12345&ac=subscribe&s=feed/http://example.com/",                                             null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["ParameterRequired", "t"])],
            ["T=12345&ac=subscribe&s=http://example.com/&t=Ook",                                            null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "s", "http://example.com/"])],
            ["T=12345&ac=subscribe&s=feed/1&t=Ook",                                                         null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "s", "feed/1"])],
            ["T=12345&ac=subscribe&s=feed/http://example.com/&t=Ook&a=Foo",                                 null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "a", "Foo"])],
            ["T=12345&ac=unsubscribe&s=feed/2112",                                                          null,                  null,                  null,    2112,     null,      [],       [],           [],             $success],
            ["T=12345&ac=unsubscribe&s=feed/http://example.biz/",                                           null,                  "http://example.biz/", null,    2112,     null,      [],       [],           [],             $success],
            ["T=12345&ac=unsubscribe&s=http://example.biz/",                                                null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "s", "http://example.biz/"])],
            ["T=12345&ac=edit&s=feed/42&t=Ack",                                                             null,                  null,                  42,      null,     "Ack",     [],       [],           [],             $success],
            ["T=12345&ac=edit&s=feed/http://example.biz/&t=Ack",                                            null,                  "http://example.biz/", 2112,    null,     "Ack",     [],       [],           [],             $success],
            ["T=12345&ac=edit&s=feed/42&a=user/-/label/Bar&r=user/-/label/Ack&r=user/-/label/Foo",          null,                  null,                  42,      null,     null,      [2],      [],           ["Ack", "Foo"], $success],
            ["T=12345&ac=edit&s=feed/42&a=user/-/label/Ook",                                                null,                  null,                  42,      null,     null,      [6],      [6 => "Ook"], [],             $success],
            ["T=12345&ac=edit&s=42&a=user/-/label/Ook",                                                     null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "s", "42"])],
            ["T=12345&s=feed/http://example.com/&t=Ook&a=user/-/label/Eek&a=user/-/label/Foo",              null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["ParameterRequired", "ac"])],
            ["T=12345&ac=edit&t=Ook&a=user/-/label/Eek&a=user/-/label/Foo",                                 null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["ParameterRequired", "s"])],
            ["T=12345&ac=edit&s=42&a=user/-/label/Ook",                                                     null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "s", "42"])],
            ["T=12345&ac=bogus&s=feed/42&a=user/-/label/Ook",                                               null,                  null,                  null,    null,     null,      [],       [],           [],             self::respError(["InvalidValue", "ac", "bogus"])],
        ];
    }

    #[DataProvider("provideArticleSelections")]
    public function testSelectArticles(string $target, string $query, bool $asc, ?Context $c, array $fields): void {
        // NOTE: This test does not exercise failure modes, only the
        //   construction of article selection contexts and sorting modes
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->labelSummarize(\Phake::anyParameters())->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->subscriptionLookup($user, "http://example.com/")->thenReturn(1);
        \Phake::when(Arsse::$db)->subscriptionLookup($user, "http://example.net/")->thenReturn(2);
        \Phake::when(Arsse::$db)->subscriptionLookup($user, "http://example.org/")->thenReturn(3);
        $sort = $asc ? ["edition"] : ["edition desc"];
        $this->req("GET", "$target?$query", "", $user);
        if ($c) {
            \Phake::verify(Arsse::$db)->articleList($user, $c, $fields, $sort);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleList(\Phake::anyParameters());
        }
    }

    public static function provideArticleSelections(): iterable {
        $c = (new Context)->limit(20);
        $start = "2026-05-01 00:00:00";
        $startU = Date::transform($start, "unix", "sql");
        $end = "2026-05-31 23:59:59";
        $endU = Date::transform($end, "unix", "sql");
        $continuation = base64_encode("s=feed/1&xt=user/-/state/com.google/read&i=2112&r=o&n=200&ot=$startU&nt=$endU");
        // the stream ID is provided seaparately from the rest of the body because it is part of the URL for one of the routes
        $tests = [
            ["",                                                                            "",                                        false, $c],
            ["",                                                                            "r=o",                                     true,  $c],
            ["",                                                                            "n=2112",                                  false, (clone $c)->limit(2112)],
            ["",                                                                            "n=10001",                                 false, (clone $c)->limit(10000)],
            ["",                                                                            "n=0",                                     false, $c],
            ["",                                                                            "n=",                                      false, $c],
            ["",                                                                            "n=-1",                                    false, $c],
            ["",                                                                            "n=1",                                     false, (clone $c)->limit(1)],
            ["user/-/state/com.google/read",                                                "",                                        false, (clone $c)->unread(false)],
            ["user/-/state/com.google/unread",                                              "",                                        false, (clone $c)->unread(true)],
            ["user/-/state/com.google/kept-unread",                                         "",                                        false, (clone $c)->unread(true)],
            ["user/-/state/com.google/starred",                                             "",                                        false, (clone $c)->starred(true)],
            ["user/-/state/com.google/reading-list",                                        "",                                        false, $c],
            ["user/-/state/org.freshrss/main",                                              "",                                        false, $c],
            ["user/-/state/org.freshrss/important",                                         "",                                        false, null],
            ["user/-/label/Ook",                                                            "",                                        false, (clone $c)->orGroups([(new Context)->tagName("Ook")->labelName("Ook")])],
            ["feed/1",                                                                      "",                                        false, (clone $c)->subscription(1)],
            ["feed/http://example.com/",                                                    "",                                        false, (clone $c)->subscription(1)],
            ["splice/user/-/label/Ook|feed/1|feed/2",                                       "",                                        false, (clone $c)->orGroups([(new Context)->orGroups([(new Context)->tagName("Ook")->labelName("Ook")])->subscriptions([1, 2])])],
            ["splice/user/-/label/Ook|user/-/label/Eek|user/-/label/Ack",                   "",                                        false, (clone $c)->orGroups([(new Context)->orGroups([(new Context)->tagNames(["Ook", "Eek", "Ack"])->labelNames(["Ook", "Eek", "Ack"])])])],
            ["splice/feed/1|feed/1",                                                        "",                                        false, (clone $c)->orGroups([(new Context)->subscription(1)])],
            ["splice/feed/1|feed/2|feed/3",                                                 "",                                        false, (clone $c)->orGroups([(new Context)->subscriptions([1, 2, 3])])],
            ["splice/user/-/state/org.freshrss/important|user/-/state/com.google/broadcast", "",                                       false, null],
            ["user/-/state/com.google/read",                                                "it=user/-/state/com.google/unread",       false, null],
            ["user/-/state/com.google/read",                                                "it=user/-/state/com.google/kept-unread",  false, null],
            ["user/-/state/com.google/unread",                                              "it=user/-/state/com.google/read",         false, null],
            ["user/-/state/com.google/kept-unread",                                         "it=user/-/state/com.google/read",         false, null],
            ["feed/1",                                                                      "it=feed/2",                               false, null],
            ["",                                                                            "xt=user/-/state/com.google/read",         false, (clone $c)->not->unread(false)],
            ["",                                                                            "xt=user/-/state/com.google/unread",       false, (clone $c)->not->unread(true)],
            ["",                                                                            "xt=user/-/state/com.google/kept-unread",  false, (clone $c)->not->unread(true)],
            ["",                                                                            "xt=user/-/state/com.google/starred",      false, (clone $c)->not->starred(true)],
            ["",                                                                            "xt=user/-/state/com.google/reading-list", false, null],
            ["",                                                                            "xt=feed/2",                               false, (clone $c)->not->subscription(2)],
            ["",                                                                            "ot=$startU",                              false, (clone $c)->modifiedRange($start, null)],
            ["",                                                                            "nt=$endU",                                false, (clone $c)->modifiedRange(null, $end)],
            ["",                                                                            "ot=$startU&nt=$endU",                     false, (clone $c)->modifiedRange($start, $end)],
            ["",                                                                            "c=$continuation",                         true,  (clone $c)->limit(200)->subscription(1)->not->unread(false)->editionRange(2112, null)->modifiedRange($start, $end)],
        ];
        $allFields = ["id", 'edition', "modified_date", "published_date", "edited_date", "subscription", "subscription_url", "subscription_title", "unread", "starred", "author", "title", "url", "content", "media_url", "media_type"];
        $minFields = ["id", 'edition', "modified_date"];
        foreach ($tests as $k => [$stream, $query, $desc, $context]) {
            yield "#$k (IDs)"   => ["/stream/items/ids",        "s=$stream&$query", $desc, $context, $minFields];
            yield "#$k (query)" => ["/stream/contents",         "s=$stream&$query", $desc, $context, $allFields];
            yield "#$k (URL)"   => ["/stream/contents/$stream", $query,             $desc, $context, $allFields];
        }
    }

    public function testListArticles(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-02 00:00:00", 'subscription' => 1,  'subscription_url' => "http://example.com/", 'subscription_title' => "Sub 1",  'unread' => 1, 'starred' => 0, 'author' => "John Doe", 'title' => "Edition 65", 'url' => "http://example.com/65", 'content' => "Content 65", 'media_url' => null,                       'media_type' => null],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00", 'published_date' => "2000-01-04 00:00:00", 'edited_date' => "2000-01-04 00:00:00", 'subscription' => 12, 'subscription_url' => "http://example.org/", 'subscription_title' => "Sub 12", 'unread' => 0, 'starred' => 1, 'author' => null,       'title' => "Edition 32", 'url' => "http://example.com/32", 'content' => "Content 32", 'media_url' => "http://example.com/audio", 'media_type' => "audio/vorbis"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook",  'subscription' => 1],
            ['name' => "Dupe", 'subscription' => 12],
        ]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 1, true)->thenReturn(["Foo", "Bar"]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 11, true)->thenReturn(["Dupe"]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($user, 1)->thenReturn(["Alfa", "Bravo"]);
        $act = $this->req("GET", "/stream/contents/", "", $user);
        $exp = HTTP::respJson([
            'id' => "user/-/state/com.google/reading-list",
            'updated' => Date::transform(self::NOW, "unix"),
            'items' => [
                [
                    'id' => "tag:google.com,2005:reader/item/0000000000000001",
                    'crawlTimeMsec' => Date::transform($articles[0]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[0]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[0]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[0]['edited_date'], "unix"),
                    'title'         => $articles[0]['title'],
                    'canonical'     => [['href' => $articles[0]['url']]],
                    'alternate'     => [['href' => $articles[0]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/unread",
                        "user/-/state/com.google/kept-unread",
                        "user/-/label/Ook",
                        "user/-/label/Foo",
                        "user/-/label/Bar",
                        "Alfa",
                        "Bravo",
                    ],
                    'origin'       => [
                        'streamId' => "feed/1",
                        'htmlUrl'  => $articles[0]['subscription_url'],
                        'title'    => $articles[0]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[0]['content']],
                    'enclosure'    => [],
                    'author'       => $articles[0]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
                [
                    'id' => "tag:google.com,2005:reader/item/000000000000000b",
                    'crawlTimeMsec' => Date::transform($articles[1]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[1]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[1]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[1]['edited_date'], "unix"),
                    'title'         => $articles[1]['title'],
                    'canonical'     => [['href' => $articles[1]['url']]],
                    'alternate'     => [['href' => $articles[1]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/read",
                        "user/-/state/com.google/starred",
                        "user/-/label/Dupe",
                    ],
                    'origin'       => [
                        'streamId' => "feed/12",
                        'htmlUrl'  => $articles[1]['subscription_url'],
                        'title'    => $articles[1]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[1]['content']],
                    'enclosure'    => [['href' => $articles[1]['media_url'], 'type' => $articles[1]['media_type']]],
                    'author'       => $articles[1]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesWithNoEntries(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/contents/user/-/state/com.google/broadcast", "", $user);
        $exp = HTTP::respJson([
            'id' => "user/-/state/com.google/reading-list",
            'updated' => Date::transform(self::NOW, "unix"),
            'items' => [],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesWithBadStream(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/contents/bogus", "", $user);
        $exp = HTTP::respText('The supplied stream ID "bogus" is not valid.', 400);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesWithBadContinuation(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/contents/?c=!", "", $user);
        $exp = HTTP::respText('The supplied continuation string is not valid.', 400);
        $this->assertMessage($exp, $act);
    }

    public function testListItemIdentifiers(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00"],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        $act = $this->req("GET", "/stream/items/ids", "", $user);
        $exp = HTTP::respJson([
            'itemRefs' => [
                [
                    'id' => "1",
                    'timestampUsec' => Date::transform($articles[0]['modified_date'], "unix")."000000",
                ],
                [
                    'id' => "11",
                    'timestampUsec' => Date::transform($articles[1]['modified_date'], "unix")."000000",
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListItemIdentifiersWithNoEntries(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/items/ids?s=user/-/state/com.google/broadcast", "", $user);
        $exp = HTTP::respJson([
            'itemRefs' => [],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListItemIdentifiersWithBadStream(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/items/ids?s=bogus", "", $user);
        $exp = HTTP::respText('The supplied stream ID "bogus" is not valid.', 400);
        $this->assertMessage($exp, $act);
    }

    public function testListItemIdentifiersWithBadContinuation(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/items/ids?c=!", "", $user);
        $exp = HTTP::respText('The supplied continuation string is not valid.', 400);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesAsXml(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-02 00:00:00", 'subscription' => 1,  'subscription_url' => "http://example.com/", 'subscription_title' => "Sub 1",  'unread' => 1, 'starred' => 0, 'author' => "John Doe", 'title' => "Edition 65", 'url' => "http://example.com/65", 'content' => "Content 65", 'media_url' => null,                       'media_type' => null],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00", 'published_date' => "2000-01-04 00:00:00", 'edited_date' => "2000-01-04 00:00:00", 'subscription' => 12, 'subscription_url' => "http://example.org/", 'subscription_title' => "Sub 12", 'unread' => 0, 'starred' => 1, 'author' => null,       'title' => "Edition 32", 'url' => "http://example.com/32", 'content' => "Content 32", 'media_url' => "http://example.com/audio", 'media_type' => "audio/vorbis"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook",  'subscription' => 1],
            ['name' => "Dupe", 'subscription' => 12],
        ]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 1, true)->thenReturn(["Foo", "Bar"]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 11, true)->thenReturn(["Dupe"]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($user, 1)->thenReturn(["Alfa", "Bravo"]);
        $act = $this->req("GET", "/stream/contents/?output=xml", "", $user);
        $exp = <<<XML_FILE
<object>
  <string name="id">user/-/state/com.google/reading-list</string>
  <number name="updated">1608592157</number>
  <list name="items">
    <object>
      <string name="id">tag:google.com,2005:reader/item/0000000000000001</string>
      <string name="crawlTimeMsec">978307200000</string>
      <string name="timestampUsec">978307200000000</string>
      <number name="published">946684800</number>
      <number name="updated">946771200</number>
      <string name="title">Edition 65</string>
      <list name="canonical">
        <object>
          <string name="href">http://example.com/65</string>
        </object>
      </list>
      <list name="alternate">
        <object>
          <string name="href">http://example.com/65</string>
          <string name="type">text/html</string>
        </object>
      </list>
      <list name="categories">
        <string>user/-/state/com.google/reading-list</string>
        <string>user/-/state/org.freshrss/main</string>
        <string>user/-/state/com.google/unread</string>
        <string>user/-/state/com.google/kept-unread</string>
        <string>user/-/label/Ook</string>
        <string>user/-/label/Foo</string>
        <string>user/-/label/Bar</string>
        <string>Alfa</string>
        <string>Bravo</string>
      </list>
      <object name="origin">
        <string name="streamId">feed/1</string>
        <string name="htmlUrl">http://example.com/</string>
        <string name="title">Sub 1</string>
      </object>
      <object name="summary">
        <string name="content">Content 65</string>
      </object>
      <list name="enclosure"/>
      <string name="author">John Doe</string>
      <list name="linkingUsers"/>
      <list name="comments"/>
      <number name="commentsNum">-1</number>
      <list name="annotations"/>
    </object>
    <object>
      <string name="id">tag:google.com,2005:reader/item/000000000000000b</string>
      <string name="crawlTimeMsec">978652800000</string>
      <string name="timestampUsec">978652800000000</string>
      <number name="published">946944000</number>
      <number name="updated">946944000</number>
      <string name="title">Edition 32</string>
      <list name="canonical">
        <object>
          <string name="href">http://example.com/32</string>
        </object>
      </list>
      <list name="alternate">
        <object>
          <string name="href">http://example.com/32</string>
          <string name="type">text/html</string>
        </object>
      </list>
      <list name="categories">
        <string>user/-/state/com.google/reading-list</string>
        <string>user/-/state/org.freshrss/main</string>
        <string>user/-/state/com.google/read</string>
        <string>user/-/state/com.google/starred</string>
        <string>user/-/label/Dupe</string>
      </list>
      <object name="origin">
        <string name="streamId">feed/12</string>
        <string name="htmlUrl">http://example.org/</string>
        <string name="title">Sub 12</string>
      </object>
      <object name="summary">
        <string name="content">Content 32</string>
      </object>
      <list name="enclosure">
        <object>
          <string name="href">http://example.com/audio</string>
          <string name="type">audio/vorbis</string>
        </object>
      </list>
      <null name="author"/>
      <list name="linkingUsers"/>
      <list name="comments"/>
      <number name="commentsNum">-1</number>
      <list name="annotations"/>
    </object>
  </list>
</object>
XML_FILE;
        $exp = HTTP::respXml($exp);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesAsAtom(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-02 00:00:00", 'subscription' => 1,  'subscription_url' => "http://example.com/", 'subscription_title' => "Sub 1",  'unread' => 1, 'starred' => 0, 'author' => "John Doe", 'title' => "Edition 65", 'url' => "http://example.com/65", 'content' => "Content 65", 'media_url' => null,                       'media_type' => null],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00", 'published_date' => "2000-01-04 00:00:00", 'edited_date' => "2000-01-04 00:00:00", 'subscription' => 12, 'subscription_url' => "http://example.org/", 'subscription_title' => "Sub 12", 'unread' => 0, 'starred' => 1, 'author' => null,       'title' => "Edition 32", 'url' => "http://example.com/32", 'content' => "Content 32", 'media_url' => "http://example.com/audio", 'media_type' => "audio/vorbis"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook",  'subscription' => 1],
            ['name' => "Dupe", 'subscription' => 12],
        ]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 1, true)->thenReturn(["Foo", "Bar"]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 11, true)->thenReturn(["Dupe"]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($user, 1)->thenReturn(["Alfa", "Bravo"]);
        $act = $this->req("GET", "/stream/contents/?output=atom", "", $user);
        $exp = self::respError("AtomNotImplemented");
        $this->assertMessage($exp, $act);
    }

    #[DataProvider("provideContinuations")]
    public function testComputeContinuations(string $query, int $count, int $min, int $max, string $exp): void {
        $minUsed = false;
        $maxUsed = false;
        $rand = new \Random\Randomizer(new \Random\Engine\PcgOneseq128XslRr64);
        $articles = array_fill(0, $count, ['id' => 1, 'edition' => null, 'modified_date' => "2000-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-01 00:00:00", 'subscription' => 1, 'subscription_url' => "http://example.com/", 'subscription_title' => "Example", 'unread' => 0, 'starred' => 0, 'author' => "Example", 'title' => "Example", 'url' => "http://example.com/", 'content' => "Example", 'media_url' => null, 'media_type' => null]);
        for ($a = 0; $a < $count; $a++) {
            if (!$minUsed && $rand->getInt(0, 1)) {
                $n = $min;
                $minUsed = true;
            } elseif (!$maxUsed && $rand->getInt(0, 1)) {
                $n = $max;
                $maxUsed = true;
            } else {
                $n = $rand->getInt($min, $max);
            }
            $articles[$a]['edition'] = $n;
        }
        if (!$maxUsed) {
            $articles[0]['edition'] = $max;
        }
        if (!$minUsed) {
            $articles[sizeof($articles) - 1]['edition'] = $min;
        }
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        $act = json_decode($this->req("GET", $query, "", "john.doe@example.com")->getBody()->getContents(), true)['continuation'] ?? "";
        $this->assertSame($exp, base64_decode($act));
    }

    public static function provideContinuations(): iterable {
        $c = base64_encode("s=feed/42&i=5000");
        return [
            ["/stream/contents/?ot=100&nt=200", 20, 100,  200,  "nt=200&ot=100&i=99"],
            ["/stream/contents/?n=20&r=d",      20, 100,  200,  "i=99"],
            ["/stream/contents/?n=25&r=o",      25, 100,  200,  "n=25&r=o&i=201"],
            ["/stream/contents/?c=$c&n=10&r=o", 20, 2113, 4224, "s=feed%2F42&i=2112"],
            ["/stream/items/ids?ot=100&nt=200", 20, 100,  200,  "nt=200&ot=100&i=99"],
            ["/stream/items/ids?n=20&r=d",      20, 100,  200,  "i=99"],
            ["/stream/items/ids?n=25&r=o",      25, 100,  200,  "n=25&r=o&i=201"],
            ["/stream/items/ids?c=$c&n=10&r=o", 20, 2113, 4224, "s=feed%2F42&i=2112"],
        ];
    }

    #[TestWith(["GET"])]
    #[TestWith(["POST"])]
    public function testFetchSpecificArticles(string $method): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-02 00:00:00", 'subscription' => 1,  'subscription_url' => "http://example.com/", 'subscription_title' => "Sub 1",  'unread' => 1, 'starred' => 0, 'author' => "John Doe", 'title' => "Edition 65", 'url' => "http://example.com/65", 'content' => "Content 65", 'media_url' => null,                       'media_type' => null],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00", 'published_date' => "2000-01-04 00:00:00", 'edited_date' => "2000-01-04 00:00:00", 'subscription' => 12, 'subscription_url' => "http://example.org/", 'subscription_title' => "Sub 12", 'unread' => 0, 'starred' => 1, 'author' => null,       'title' => "Edition 32", 'url' => "http://example.com/32", 'content' => "Content 32", 'media_url' => "http://example.com/audio", 'media_type' => "audio/vorbis"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook",  'subscription' => 1],
            ['name' => "Dupe", 'subscription' => 12],
        ]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 1, true)->thenReturn(["Foo", "Bar"]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 11, true)->thenReturn(["Dupe"]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($user, 1)->thenReturn(["Alfa", "Bravo"]);
        $items = "i=1&i=tag:google.com,2005:reader/item/000000000000000B&i=0000000000000010";
        if ($method === "GET") {
            $act = $this->req("GET", "/stream/items/contents?$items", "", $user);
        } else {
            $act = $this->req("POST", "/stream/items/contents", $items, $user);
        }
        $exp = HTTP::respJson([
            'id' => "user/-/state/com.google/reading-list",
            'updated' => Date::transform(self::NOW, "unix"),
            'items' => [
                [
                    'id' => "tag:google.com,2005:reader/item/0000000000000001",
                    'crawlTimeMsec' => Date::transform($articles[0]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[0]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[0]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[0]['edited_date'], "unix"),
                    'title'         => $articles[0]['title'],
                    'canonical'     => [['href' => $articles[0]['url']]],
                    'alternate'     => [['href' => $articles[0]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/unread",
                        "user/-/state/com.google/kept-unread",
                        "user/-/label/Ook",
                        "user/-/label/Foo",
                        "user/-/label/Bar",
                        "Alfa",
                        "Bravo",
                    ],
                    'origin'       => [
                        'streamId' => "feed/1",
                        'htmlUrl'  => $articles[0]['subscription_url'],
                        'title'    => $articles[0]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[0]['content']],
                    'enclosure'    => [],
                    'author'       => $articles[0]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
                [
                    'id' => "tag:google.com,2005:reader/item/000000000000000b",
                    'crawlTimeMsec' => Date::transform($articles[1]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[1]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[1]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[1]['edited_date'], "unix"),
                    'title'         => $articles[1]['title'],
                    'canonical'     => [['href' => $articles[1]['url']]],
                    'alternate'     => [['href' => $articles[1]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/read",
                        "user/-/state/com.google/starred",
                        "user/-/label/Dupe",
                    ],
                    'origin'       => [
                        'streamId' => "feed/12",
                        'htmlUrl'  => $articles[1]['subscription_url'],
                        'title'    => $articles[1]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[1]['content']],
                    'enclosure'    => [['href' => $articles[1]['media_url'], 'type' => $articles[1]['media_type']]],
                    'author'       => $articles[1]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db)->articleList($user, (new Context)->articles([1, 11, 16]), ["id", 'edition', "modified_date", "published_date", "edited_date", "subscription", "subscription_url", "subscription_title", "unread", "starred", "author", "title", "url", "content", "media_url", "media_type"], ["edition desc"]);
    }

    public function testFetchArticlesWithBadIdentifier(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/items/contents?i=bogus", "", $user);
        $exp = HTTP::respText('The supplied item ID "bogus" is not valid.', 400);
        $this->assertMessage($exp, $act);
    }

    public function testFetchArticlesWithNoIdentifier(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/stream/items/contents", "", $user);
        $exp = self::respError(["ParameterRequired", "i"]);
        $this->assertMessage($exp, $act);
    }

    #[DataProvider("provideArticleCounts")]
    public function testCountArticles(string $query, ?Context $context, bool $latest, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result([['modified_date' => "2000-01-01 00:00:00"]]));
        \Phake::when(Arsse::$db)->articleCount(\Phake::anyParameters())->thenReturn(2112);
        $act = $this->req("GET", "/stream/items/count?$query", "", $user);
        $this->assertMessage($exp, $act);
        if ($context) {
            \Phake::verify(Arsse::$db)->articleCount($user, $context);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleCount(\Phake::anyParameters());
        }
        if ($latest) {
            \Phake::verify(Arsse::$db)->articleList($user, (clone $context)->limit(1), ["modified_date"], ["modified_date desc"]);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->articleList(\Phake::anyParameters());
        }
    }

    public static function provideArticleCounts(): iterable {
        self::clearData(); // initializes string formatter
        $c = new Context;
        return [
            ["s=feed/1",                                   (clone $c)->subscription(1), false, HTTP::respText("2112")],
            ["s=feed/1&a=true",                            (clone $c)->subscription(1), true,  HTTP::respText("2112#January 01, 2000")],
            ["",                                           null,                        false, self::respError(["ParameterRequired", "s"])],
            ["s=user/-/state/com.google/broadcast&a=true", null,                        false, HTTP::respText("0")],
        ];
    }

    public function testListSubscriptions(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->subscriptionList(\Phake::anyParameters())->thenReturn(new Result([
            ['id' => 42,    'title' => 'Ook!', 'url' => "http://ook.net/feed", 'source' => "http://ook.net", 'icon_url' => "http://ook.net/icon"],
            ['id' => 2112,  'title' => 'Eek!', 'url' => "http://eek.org/feed", 'source' => "http://eek.org", 'icon_url' => "http://eek.org/icon"],
            ['id' => 31337, 'title' => 'Ack!', 'url' => "http://ack.com/feed", 'source' => "http://ack.com", 'icon_url' => null],
        ]));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['id' => 1, 'name' => "Foo", 'subscription' => 2112],
            ['id' => 2, 'name' => "Bar", 'subscription' => 42],
            ['id' => 2, 'name' => "Bar", 'subscription' => 2112],

        ]));
        $exp = HTTP::respJson(['subscriptions' => [
            [
                'id'    => "feed/42",
                'title' => "Ook!",
                'categories' => [
                    ['id' => 'user/-/label/Bar', 'label' => "Bar"],
                ],
                'url' => "http://ook.net/feed",
                'htmlUrl' => "http://ook.net",
                'iconUrl' => "http://ook.net/icon",
                'frss:priority' => "main",
                'sortid' => "00000001",
            ],
            [
                'id'    => "feed/2112",
                'title' => "Eek!",
                'categories' => [
                    ['id' => 'user/-/label/Foo', 'label' => "Foo"],
                    ['id' => 'user/-/label/Bar', 'label' => "Bar"],
                ],
                'url' => "http://eek.org/feed",
                'htmlUrl' => "http://eek.org",
                'iconUrl' => "http://eek.org/icon",
                'frss:priority' => "main",
                'sortid' => "00000002",
            ],
            [
                'id'    => "feed/31337",
                'title' => "Ack!",
                'categories' => [],
                'url' => "http://ack.com/feed",
                'htmlUrl' => "http://ack.com",
                'iconUrl' => "https://example.test/freshrss/default.png",
                'frss:priority' => "main",
                'sortid' => "00000003",
            ],
        ]]);
        $act = $this->req("GET", "/subscription/list", "", $user);
        $this->assertMessage($exp, $act);
        \Phake::verify(Arsse::$db)->subscriptionList($user);
        \Phake::verify(Arsse::$db)->tagSummarize($user);
    }

    #[DataProvider("provideSubscriptionValidations")]
    public function testValidateSubscriptions(?string $stream, ?string $lookupIn, ?int $lookupOut, ?int $propertiesIn, ?array $propertiesOut, ResponseInterface $exp): void {
        $user = "john.doe@example.com";
        if ($lookupOut !== null) {
            \Phake::when(Arsse::$db)->subscriptionLookup(\Phake::anyParameters())->thenReturn($lookupOut);
        } else {
            \Phake::when(Arsse::$db)->subscriptionLookup(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        }
        if ($propertiesOut !== null) {
            \Phake::when(Arsse::$db)->subscriptionPropertiesGet(\Phake::anyParameters())->thenReturn($propertiesOut);
        } else {
            \Phake::when(Arsse::$db)->subscriptionPropertiesGet(\Phake::anyParameters())->thenThrow(new ExceptionInput("subjectMissing"));
        }
        $act = $this->req("GET", "/subscribed?s=$stream", "", $user);
        $this->assertMessage($exp, $act);
        if ($lookupIn !== null) {
            \Phake::verify(Arsse::$db)->subscriptionLookup($user, $lookupIn);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionLookup(\Phake::anyParameters());
        }
        if ($propertiesIn !== null) {
            \Phake::verify(Arsse::$db)->subscriptionPropertiesGet($user, $propertiesIn);
        } else {
            \Phake::verify(Arsse::$db, \Phake::never())->subscriptionPropertiesGet(\Phake::anyParameters());
        }
    }

    public static function provideSubscriptionValidations(): iterable {
        $true = HTTP::respText("true");
        $false = HTTP::respText("false");
        return [
            ["feed/1",                  null,                 null, 1,    [],   $true],
            ["feed/1",                  null,                 null, 1,    null, $false],
            ["feed/http://example.com", "http://example.com", 2112, null, null, $true],
            ["feed/http://example.com", "http://example.com", null, null, null, $false],
            ["feed/bogus",              null,                 null, null, null, self::respError(["InvalidStream", "feed/bogus"])],
            [null,                      null,                 null, null, null, self::respError(["ParameterRequired", "s"])],
        ];
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testImport(bool $success): void {
        $user = "john.doe@example.com";
        $opml = \Phake::mock(OPML::class);
        \Phake::when(Arsse::$obj)->get(OPML::class)->thenReturn($opml);
        if ($success) {
            \Phake::when($opml)->import(\Phake::anyParameters())->thenReturn(true);
            $exp = HTTP::respText("OK");
        } else {
            \Phake::when($opml)->import(\Phake::anyParameters())->thenThrow(new ImportException("invalidSyntax"));
            $exp = self::respError(new ImportException("invalidSyntax"));
        }
        $act = $this->req("POST", "/subscription/import", "IMPORT DATA", $user);
        $this->assertMessage($exp, $act);
        \Phake::verify($opml)->import($user, "IMPORT DATA");
    }

    public function testExport(): void {
        $user = "john.doe@example.com";
        $opml = \Phake::mock(OPML::class);
        \Phake::when(Arsse::$obj)->get(OPML::class)->thenReturn($opml);
        \Phake::when($opml)->export(\Phake::anyParameters())->thenReturn("<EXPORT_DATA/>");
        $exp = HTTP::respText("<EXPORT_DATA/>", 200, ['Content-Type' => "application/xml"]);
        $act = $this->req("GET", "/subscription/export", "", $user);
        $this->assertMessage($exp, $act);
        \Phake::verify($opml)->export($user);
    }
}
