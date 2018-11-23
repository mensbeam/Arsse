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
    protected static $firstAvailableDriver;

    public static function setUpBeforeClass() {
        self::setConf();
        // we only need to test one PDO implementation (they all use the same result class), so we find the first usable one
        $drivers = DatabaseInformation::listPDO();
        self::$firstAvailableDriver = $drivers[0];
        foreach ($drivers as $driver) {
            $info = new DatabaseInformation($driver);
            $interface = ($info->interfaceConstructor)();
            if ($interface) {
                self::$firstAvailableDriver = $driver;
                break;
            }
        }
    }
    
    public function setUp() {
        $this->implementation = self::$firstAvailableDriver;
        parent::setUp();
    }

    public function tearDown() {
        parent::tearDown();
        unset($this->interface);
    }

    protected function exec(string $q) {
        $this->interface->exec($q);
    }

    protected function makeResult(string $q): array {
        $set = $this->interface->query($q);
        $rows = $set->rowCount();
        $id = $this->interface->lastInsertID();
        return [$set, [$rows, $id]];
    }
}
