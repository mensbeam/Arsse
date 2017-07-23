<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;

trait SeriesMiscellany {

    function testListDrivers() {
        $exp = [
            'JKingWeb\\Arsse\\Db\\SQLite3\\Driver' => Arsse::$lang->msg("Driver.Db.SQLite3.Name"),
        ];
        $this->assertArraySubset($exp, Database::driverList());
    }

    function testInitializeDatabase() {
        $d = new Database();
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
    }

    function testManuallyInitializeDatabase() {
        $d = new Database(false);
        $this->assertSame(0, $d->driverSchemaVersion());
        $this->assertTrue($d->driverSchemaUpdate());
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
        $this->assertFalse($d->driverSchemaUpdate());
    }
}