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

    protected $transaction;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        // create a mock database interface
        $this->dbMock = $this->mock(Database::class);
        $this->transaction = $this->mock(Transaction::class);
        $this->dbMock->begin->returns($this->transaction);
    }

    protected function prepTest(): Token {
        Arsse::$db = $this->dbMock->get();
        // instantiate the handler
        return new Token;
    }

    protected function v($value) {
        return $value;
    }

    public function testGenerateTokens(): void {
        $this->dbMock->tokenCreate->returns("RANDOM TOKEN");
        $this->assertSame("RANDOM TOKEN", $this->prepTest()->tokenGenerate("ook", "Eek"));
        $this->dbMock->tokenCreate->calledWith("ook", "miniflux.login", "~", null, "Eek");
        $token = $this->dbMock->tokenCreate->firstCall()->argument(2);
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
        $this->dbMock->tokenList->returns(new Result($this->v($out)));
        $this->dbMock->userExists->returns(true);
        $this->assertSame($exp, $this->prepTest()->tokenList("john.doe@example.com"));
        $this->dbMock->tokenList->calledWith("john.doe@example.com", "miniflux.login");
    }

    public function testListTheTokensOfAMissingUser(): void {
        $this->dbMock->userExists->returns(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->prepTest()->tokenList("john.doe@example.com");
    }
}
