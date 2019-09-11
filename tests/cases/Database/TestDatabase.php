<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Database;

/** @covers \JKingWeb\Arsse\Database */
class TestDatabase extends \JKingWeb\Arsse\Test\AbstractTest {
    static $db = null;
    
    public static function setUpBeforeClass() {
        self::clearData();
        self::setConf();
        self::$db = \Phake::makeVisible(\Phake::partialMock(Database::class));
    }

    public static function tearDownAfterClass() {
        self::$db = null;
        self::clearData();
    }

    /** @dataProvider provideInClauses */
    public function testGenerateInClause(string $clause, array $values, array $inV, string $inT) {
        $types = array_fill(0, sizeof($values), $inT);
        $exp = [$clause, $types, $values];
        $this->assertSame($exp, self::$db->generateIn($inV, $inT));
    }

    public function provideInClauses() {
        $l = Database::LIMIT_SET_SIZE + 1;
        $strings = array_fill(0, $l, "");
        $ints = range(1, $l);
        $longString = str_repeat("0", Database::LIMIT_SET_STRING_LENGTH + 1);
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
            ["$intList,".($l+1),   [],            array_merge($ints, [$l+1]),           "int"],
            ["$intList,0",         [],            array_merge($ints, ["OOK"]),          "int"],
            ["$intList",           [],            array_merge($ints, [null]),           "int"],
            ["$stringList,''",     [],            array_merge($strings, [""]),          "str"],
            ["$stringList",        [],            array_merge($strings, [null]),        "str"],
            ["$stringList,?",      [$longString], array_merge($strings, [$longString]), "str"],
            ["$stringList,'A''s'", [],            array_merge($strings, ["A's"]),       "str"],
            ["$params",            $ints,         $ints,                                "bool"],
        ];
    }
}
