<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\PostgreSQL;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\Statement<extended>
 * @covers \JKingWeb\Arsse\Db\PostgreSQL\Dispatch<extended> */
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    use \JKingWeb\Arsse\TestCase\DatabaseDrivers\PostgreSQL;

    protected function makeStatement(string $q, array $types = []): array {
        return [static::$interface, $q, $types];
    }

    protected function decorateTypeSyntax(string $value, string $type): string {
        switch ($type) {
            case "float":
                return (substr($value, -2) === ".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
            case "string":
                if (preg_match("<^char\((\d+)\)$>", $value, $match)) {
                    return "U&'\\+".str_pad(dechex((int) $match[1]), 6, "0", \STR_PAD_LEFT)."'";
                }
                return $value;
            default:
                return $value;
        }
    }

    public static function tearDownAfterClass() {
        if (static::$interface) {
            static::dbRaze(static::$interface);
            @pg_close(static::$interface);
            static::$interface = null;
        }
        parent::tearDownAfterClass();
    }
}
