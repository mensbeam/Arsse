<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\MySQL;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\MySQL\Statement<extended>
 * @covers \JKingWeb\Arsse\Db\MySQL\ExceptionBuilder
 * @covers \JKingWeb\Arsse\Db\SQLState */
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\MySQL;

    protected function makeStatement(string $q, array $types = []): array {
        return [static::$interface, $q, $types];
    }

    protected function decorateTypeSyntax(string $value, string $type): string {
        switch ($type) {
            case "float":
                return (substr($value, -2) === ".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
            case "string":
                if (preg_match("<^char\((\d+)\)$>D", $value, $match)) {
                    return "'".\IntlChar::chr((int) $match[1])."'";
                }
                return $value;
            case "datetime":
                return "cast($value as datetime(0))";
            default:
                return $value;
        }
    }

    public function testBindLongString(): void {
        // this test requires some set-up to be effective
        static::$interface->query("CREATE TABLE arsse_test(`value` longtext not null) character set utf8mb4");
        // we'll use an unrealistic packet size of 1 byte to trigger special handling for strings which are too long for the maximum packet size
        $str = "long string";
        $s = new \JKingWeb\Arsse\Db\MySQL\Statement(static::$interface, "INSERT INTO arsse_test values(?)", ["str"], 1);
        $s->runArray([$str]);
        $s = new \JKingWeb\Arsse\Db\MySQL\Statement(static::$interface, "SELECT * from arsse_test", []);
        $val = $s->run()->getValue();
        $this->assertSame($str, $val);
    }
}
