<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use JKingWeb\Arsse\Test\DatabaseInformation;

/**
 * @covers \JKingWeb\Arsse\Db\ResultPDO<extended>
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    protected static $implementation = "PDO SQLite 3";
    protected static $createMeta = "CREATE TABLE arsse_meta(key text primary key not null, value text) without rowid";
    protected static $createTest = "CREATE TABLE arsse_test(id integer primary key)";

    protected function makeResult(string $q): array {
        $set = static::$interface->query($q);
        return [static::$interface, $set];
    }
}
