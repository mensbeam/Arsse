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
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Reader::class)]
class TestReader extends \JKingWeb\Arsse\Test\AbstractTest {
    use \JKingWeb\Arsse\REST\Reader\Common;

    protected const NOW = "2020-12-21T23:09:17.189065Z";
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
        // create the reader class, with authentication stubbed out
        $this->h = \Phake::partialMock(Reader::class);
        \Phake::when($this->h)->authenticate(\Phake::anyParameters())->thenReturn(true);
        \Phake::when($this->h)->shouldChallenge(\Phake::anyParameters())->thenReturn(false);
    }

    protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface {
        if (strlen((string) $user)) {
            Arsse::$user->id = $user;
        }
        return $this->h->dispatch($this->serverRequest($method, "/api/greader.php/reader/api/0".$target, "/api/greader.php/reader/api/0", [], [], $data, "application/x-www-form-urlencoded", [], $user));
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
            ["i=3&i=4&r=user/-/state/com.google/kept-unread&T=12345",      ['read' => true],     (new Context)->articles([3,4]), $success],
            ["i=1&i=2&r=user/-/state/com.google/read&T=12345",             ['read' => false],    (new Context)->articles([1,2]), $success],
            ["i=3&i=4&a=user/-/state/com.google/kept-unread&T=12345",      ['read' => false],    (new Context)->articles([3,4]), $success],
            ["i=5&i=6&a=user/-/state/com.google/starred&T=12345",          ['starred' => true],  (new Context)->articles([5,6]), $success],
            ["i=5&i=6&r=user/-/state/com.google/starred&T=12345",          ['starred' => false], (new Context)->articles([5,6]), $success],
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
}
