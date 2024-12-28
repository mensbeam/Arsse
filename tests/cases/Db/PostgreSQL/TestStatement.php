<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\PostgreSQL;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\PostgreSQL\Statement::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PostgreSQL\Dispatch::class)]
#[CoversClass(\JKingWeb\Arsse\Db\SQLState::class)]
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\PostgreSQL;

    protected function makeStatement(string $q, array $types = []): array {
        return [static::$interface, $q, $types];
    }

    protected static function decorateTypeSyntax(string $value, string $type): string {
        switch ($type) {
            case "float":
                return (substr($value, -2) === ".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
            case "string":
                if (preg_match("<^char\((\d+)\)$>D", $value, $match)) {
                    return "U&'\\+".str_pad(dechex((int) $match[1]), 6, "0", \STR_PAD_LEFT)."'";
                }
                return $value;
            case "binary":
                if ($value[0] === "x") {
                    return "'\\x".substr($value, 2)."::bytea";
                }
                // no break;
            default:
                return $value;
        }
    }

    public static function tearDownAfterClass(): void {
        if (static::$interface) {
            static::dbRaze(static::$interface);
            @pg_close(static::$interface);
            static::$interface = null;
        }
        parent::tearDownAfterClass();
    }
}
