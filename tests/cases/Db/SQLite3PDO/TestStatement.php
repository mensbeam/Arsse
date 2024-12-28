<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\JKingWeb\Arsse\Db\PDOStatement::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PDOError::class)]
#[CoversClass(\JKingWeb\Arsse\Db\SQLState::class)]
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\SQLite3PDO;

    protected function makeStatement(string $q, array $types = []): array {
        return [static::$interface, $q, $types];
    }

    protected static function decorateTypeSyntax(string $value, string $type): string {
        if ($type === "float") {
            return (substr($value, -2) === ".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
        } else {
            return $value;
        }
    }
}
