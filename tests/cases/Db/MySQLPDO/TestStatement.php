<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\MySQLPDO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\MySQL\PDOStatement::class)]
#[CoversClass(\JKingWeb\Arsse\Db\MySQL\ExceptionBuilder::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PDOError::class)]
#[CoversClass(\JKingWeb\Arsse\Db\SQLState::class)]
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\MySQLPDO;

    protected function makeStatement(string $q, array $types = []): array {
        return [static::$interface, $q, $types];
    }

    protected static function decorateTypeSyntax(string $value, string $type): string {
        switch ($type) {
            case "float":
                return (substr($value, -2) === ".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
            case "string":
                if (preg_match("<^char\((\d+)\)$>D", $value, $match)) {
                    return "'".\IntlChar::chr((int) $match[1])."'";
                }
                return $value;
            default:
                return $value;
        }
    }
}
