<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\MySQLPDO;

use JKingWeb\Arsse\Test\DatabaseInformation;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\PDOResult<extended>
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    protected static $implementation = "PDO MySQL";
    protected static $createMeta = "CREATE TABLE arsse_meta(`key` varchar(255) primary key not null, value text)";
    protected static $createTest = "CREATE TABLE arsse_test(id bigint auto_increment primary key)";
    protected static $insertDefault = "INSERT INTO arsse_test(id) values(default)";

    protected function makeResult(string $q): array {
        $set = static::$interface->query($q);
        return [static::$interface, $set];
    }
}