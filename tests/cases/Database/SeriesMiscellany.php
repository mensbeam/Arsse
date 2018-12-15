<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;

trait SeriesMiscellany {
    protected function setUpSeriesMiscellany() {
        static::setConf([
            'dbDriver' => static::$dbInfo->driverClass,
        ]);
    }

    protected function tearDownSeriesMiscellany() {
    }

    public function testListDrivers() {
        $exp = [
            'JKingWeb\\Arsse\\Db\\SQLite3\\Driver' => Arsse::$lang->msg("Driver.Db.SQLite3.Name"),
        ];
        $this->assertArraySubset($exp, Database::driverList());
    }

    public function testInitializeDatabase() {
        (static::$dbInfo->razeFunction)(static::$drv);
        $d = new Database(true);
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
    }

    public function testManuallyInitializeDatabase() {
        (static::$dbInfo->razeFunction)(static::$drv);
        $d = new Database(false);
        $this->assertSame(0, $d->driverSchemaVersion());
        $this->assertTrue($d->driverSchemaUpdate());
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
        $this->assertFalse($d->driverSchemaUpdate());
    }

    public function testCheckCharacterSetAcceptability() {
        $this->assertInternalType("bool", Arsse::$db->driverCharsetAcceptable());
    }
}
