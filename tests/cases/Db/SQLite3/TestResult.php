<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\Result<extended>
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\SQLite3;

    protected static $createMeta = "CREATE TABLE arsse_meta(key text primary key not null, value text) without rowid";
    protected static $createTest = "CREATE TABLE arsse_test(id integer primary key)";

    public static function tearDownAfterClass() {
        static::$interface->close();
        static::$interface = null;
        parent::tearDownAfterClass();
    }

    protected function makeResult(string $q): array {
        $set = static::$interface->query($q);
        $rows = static::$interface->changes();
        $id = static::$interface->lastInsertRowID();
        return [$set, [$rows, $id]];
    }
}
