<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\MySQLPDO;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\MySQL\PDOStatement<extended>
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestStatement extends \JKingWeb\Arsse\TestCase\Db\BaseStatement {
    protected static $implementation = "PDO MySQL";

    protected function makeStatement(string $q, array $types = []): array {
        return [static::$interface, $q, $types];
    }

    protected function decorateTypeSyntax(string $value, string $type): string {
        switch ($type) {
            case "float":
                return (substr($value, -2)==".0") ? "'".substr($value, 0, strlen($value) - 2)."'" : "'$value'";
            case "string":
                if (preg_match("<^char\((\d+)\)$>", $value, $match)) {
                    return "'".\IntlChar::chr((int) $match[1])."'";
                }
                return $value;
            default:
                return $value;
        }
    }
}
