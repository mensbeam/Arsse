<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Reader;

use JKingWeb\Arsse\Arsse;
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
    protected const NOW = "2020-12-21T23:09:17.189065Z";
    protected $h = null;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create mock timestamps
        \Phake::when(Arsse::$obj)->get(\DateTimeImmutable::class)->thenReturn(new \DateTimeImmutable(self::NOW));
        // create a mock user manager
        Arsse::$user = \Phake::mock(User::class);
        \Phake::when(Arsse::$user)->auth->thenReturn(true);
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->begin->thenReturn(\Phake::mock(Transaction::class));
        \Phake::when(Arsse::$db)->tokenCreate->thenReturn("12345");
        // create the reader class, with authentication stubbed out
        $this->h = \Phake::partialMock(Reader::class);
        \Phake::when($this->h)->authenticate->thenReturn(true);
        \Phake::when($this->h)->shouldChallenge->thenReturn(false);
    }

    protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface {
        if (strlen((string) $user)) {
            Arsse::$user->id = $user;
        }
        return $this->h->dispatch($this->serverRequest($method, "/api/greader.php/reader/api/0".$target, "/api/greader.php/reader/api/0", [], [], $data, "application/x-www-form-urlencoded", [], $user));
    }

    public function testMarkAnArticleAsRead(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$db)->tokenLookup->thenThrow(new ExceptionInput("subjectMissing"));
        \Phake::when(Arsse::$db)->tokenLookup("reader.post", "12345", $user)->thenReturn([]);
        \Phake::when(Arsse::$db)->articleMark->thenReturn(1);
        $act = $this->req("POST", "/edit-tag", "i=1&i=2&a=user/-/state/com.google/read&T=12345", $user);
        $this->assertMessage(HTTP::respText("OK"), $act);
    }
}
