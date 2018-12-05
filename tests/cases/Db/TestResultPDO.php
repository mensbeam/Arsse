<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Test\DatabaseInformation;

/**
 * @covers \JKingWeb\Arsse\Db\PDOResult<extended>
 */
class TestResultPDO extends \JKingWeb\Arsse\TestCase\Db\BaseResult {
    protected static $implementation;

    public static function setUpBeforeClass() {
        self::setConf();
        // we only need to test one PDO implementation (they all use the same result class), so we find the first usable one
        $drivers = DatabaseInformation::listPDO();
        self::$implementation = $drivers[0];
        foreach ($drivers as $driver) {
            $info = new DatabaseInformation($driver);
            $interface = ($info->interfaceConstructor)();
            if ($interface) {
                self::$implementation = $driver;
                break;
            }
        }
        unset($interface);
        unset($info);
        parent::setUpBeforeClass();
    }

    protected function makeResult(string $q): array {
        $set = static::$interface->query($q);
        $rows = $set->rowCount();
        $id = static::$interface->lastInsertID();
        return [$set, [$rows, $id]];
    }
}
