<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\Miniflux;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\REST\Miniflux\Token;
use JKingWeb\Arsse\Test\Result;

/** @covers \JKingWeb\Arsse\REST\Miniflux\Token<extended> */
class TestToken extends \JKingWeb\Arsse\Test\AbstractTest {
    protected const NOW = "2020-12-09T22:35:10.023419Z";
    protected const TOKEN = "Tk2o9YubmZIL2fm2w8Z4KlDEQJz532fNSOcTG0s2_xc=";

    protected $h;
    protected $transaction;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock database interface
        Arsse::$db = \Phake::mock(Database::class);
        $this->transaction = \Phake::mock(Transaction::class);
        \Phake::when(Arsse::$db)->begin->thenReturn($this->transaction);
        $this->h = new Token();
    }

    protected function v($value) {
        return $value;
    }

    public function testGenerateTokens(): void {
        \Phake::when(Arsse::$db)->tokenCreate->thenReturn("RANDOM TOKEN");
        $this->assertSame("RANDOM TOKEN", $this->h->tokenGenerate("ook", "Eek"));
        \Phake::verify(Arsse::$db)->tokenCreate("ook", "miniflux.login", \Phake::capture($token), null, "Eek");
        $this->assertRegExp("/^[A-Za-z0-9_\-]{43}=$/", $token);
    }

    public function testListTheTokensOfAUser(): void {
        $out = [
            ['id' => "TOKEN 1", 'data' => "Ook"],
            ['id' => "TOKEN 2", 'data' => "Eek"],
            ['id' => "TOKEN 3", 'data' => "Ack"],
        ];
        $exp = [
            ['label' => "Ook", 'id' => "TOKEN 1"],
            ['label' => "Eek", 'id' => "TOKEN 2"],
            ['label' => "Ack", 'id' => "TOKEN 3"],
        ];
        \Phake::when(Arsse::$db)->tokenList->thenReturn(new Result($this->v($out)));
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
        $this->assertSame($exp, $this->h->tokenList("john.doe@example.com"));
        \Phake::verify(Arsse::$db)->tokenList("john.doe@example.com", "miniflux.login");
    }

    public function testListTheTokensOfAMissingUser(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->h->tokenList("john.doe@example.com");
    }
}
