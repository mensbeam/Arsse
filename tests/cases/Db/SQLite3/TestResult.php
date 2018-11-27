<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

use JKingWeb\Arsse\Test\DatabaseInformation;

/** 
 * @covers \JKingWeb\Arsse\Db\SQLite3\Result<extended> 
 */
class TestResult extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    protected static $implementation = "SQLite 3";

    public static function tearDownAfterClass() {
        if (static::$interface) {
            static::$interface->close();
        }
        parent::tearDownAfterClass();
    }

    protected function makeResult(string $q): array {
        $set = static::$interface->query($q);
        $rows = static::$interface->changes();
        $id = static::$interface->lastInsertRowID();
        return [$set, [$rows, $id]];
    }
}
