<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Transaction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversMethod;

#[CoversNothing]
class TestDatabase extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $db = null;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        try {
            $this->db = new Database;
        } catch (\JKingWeb\Arsse\Db\Exception $e) {
            $this->markTestSkipped("SQLite 3 database driver not available");
        }
    }

    public function tearDown(): void {
        $this->db = null;
        parent::tearDown();
    }

    protected function invoke(string $method, ...$arg) {
        $m = new \ReflectionMethod($this->db, $method);
        $m->setAccessible(true);
        return $m->invoke($this->db, ...$arg);
    }

    #[CoversMethod(Database::class, "generateIn")]
    #[DataProvider('provideInClauses')]
    public function testGenerateInClause(string $clause, array $values, array $inV, string $inT): void {
        $types = array_fill(0, sizeof($values), $inT);
        $exp = [$clause, $types, $values];
        $this->assertSame($exp, $this->invoke("generateIn", $inV, $inT));
    }

    public static function provideInClauses(): iterable {
        $l = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue() + 1;
        $strings = array_fill(0, $l, "");
        $ints = range(1, $l);
        $longString = str_repeat("0", (new \ReflectionClassConstant(Database::class, "LIMIT_SET_STRING_LENGTH"))->getValue() + 1);
        $params = implode(",", array_fill(0, $l, "?"));
        $intList = implode(",", $ints);
        $stringList = implode(",", array_fill(0, $l, "''"));
        return [
            ["null",               [],            [],                                   "str"],
            ["?",                  [1],           [1],                                  "int"],
            ["?",                  ["1"],         ["1"],                                "int"],
            ["?,?",                [null, null],  [null, null],                         "str"],
            ["null",               [],            array_fill(0, $l, null),              "str"],
            ["$intList",           [],            $ints,                                "int"],
            ["$intList,".($l + 1),   [],            array_merge($ints, [$l + 1]),           "int"],
            ["$intList,0",         [],            array_merge($ints, ["OOK"]),          "int"],
            ["$intList",           [],            array_merge($ints, [null]),           "int"],
            ["$stringList,''",     [],            array_merge($strings, [""]),          "str"],
            ["$stringList",        [],            array_merge($strings, [null]),        "str"],
            ["$stringList,?",      [$longString], array_merge($strings, [$longString]), "str"],
            ["$stringList,'A''s'", [],            array_merge($strings, ["A's"]),       "str"],
            ["$stringList,?",      ["???"],       array_merge($strings, ["???"]),       "str"],
            ["$params",            $ints,         $ints,                                "bool"],
        ];
    }

    #[CoversMethod(Database::class, "generateSearch")]
    #[DataProvider('provideSearchClauses')]
    public function testGenerateSearchClause(string $clause, array $values, array $inV, array $inC, bool $inAny): void {
        // this is not an exhaustive test; integration tests already cover the ins and outs of the functionality
        $types = array_fill(0, sizeof($values), "str");
        $exp = [$clause, $types, $values];
        $this->assertSame($exp, $this->invoke("generateSearch", $inV, $inC, $inAny));
    }

    public static function provideSearchClauses(): iterable {
        $setSize = (new \ReflectionClassConstant(Database::class, "LIMIT_SET_SIZE"))->getValue();
        $terms = array_fill(0, $setSize + 1, "a");
        $clause = array_fill(0, $setSize + 1, "test like '%a%' escape '^'");
        $longString = str_repeat("0", (new \ReflectionClassConstant(Database::class, "LIMIT_SET_STRING_LENGTH"))->getValue() + 1);
        return [
            ["test like ? escape '^'",                                    ["%a%"],           ["a"],                              ["test"],         true],
            ["(col1 like ? escape '^' or col2 like ? escape '^')",        ["%a%", "%a%"],    ["a"],                              ["col1", "col2"], true],
            ["(".implode(" or ", $clause).")",                            [],                $terms,                             ["test"],         true],
            ["(".implode(" and ", $clause).")",                           [],                $terms,                             ["test"],         false],
            ["(".implode(" or ", $clause)." or test like ? escape '^')",  ["%$longString%"], array_merge($terms, [$longString]), ["test"],         true],
            ["(".implode(" or ", $clause)." or test like ? escape '^')",  ["%Eh?%"],         array_merge($terms, ["Eh?"]),       ["test"],         true],
            ["(".implode(" or ", $clause)." or test like ? escape '^')",  ["%?%"],           array_merge($terms, ["?"]),         ["test"],         true],
        ];
    }

    #[CoversMethod(Database::class, "generateSet")]
    public function testGenerateSetClause(): void {
        $in = [
            'ook' => true,
            'ack' => false,
            'bar' => "Nimoy",
            'foo' => "Shatner",
        ];
        $valid = [
            'ook' => "bool",
            'eek' => "int",
            'foo' => "str",
            'bar' => "str",
        ];
        $exp = [
            '"ook" = ?, "foo" = ?, "bar" = ?',
            ["bool", "str", "str"],
            [true, "Shatner", "Nimoy"],
        ];
        $this->assertSame($exp, $this->invoke("generateSet", $in, $valid));
    }

    #[CoversMethod(Database::class, "begin")]
    public function testBeginATransaction(): void {
        $this->assertInstanceOf(Transaction::class, $this->invoke("begin"));
    }

    #[CoversMethod(Database::class, "caller")]
    public function testReportCallingMethod(): void {
        $this->assertSame("caller", $this->invoke("caller"));
    }
}
