<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;

trait SeriesMiscellany {
    protected function setUpSeriesMiscellany(): void {
        static::setConf([
            'dbDriver' => static::$dbDriverClass,
        ]);
    }

    protected function tearDownSeriesMiscellany(): void {
    }

    /**
     * @covers \JKingWeb\Arsse\Database::__construct
     * @covers \JKingWeb\Arsse\Database::driverSchemaVersion
     * @covers \JKingWeb\Arsse\Database::driverSchemaUpdate
     */
    public function testInitializeDatabase(): void {
        static::dbRaze(static::$drv);
        $d = new Database(true);
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
    }

    /**
     * @covers \JKingWeb\Arsse\Database::__construct
     * @covers \JKingWeb\Arsse\Database::driverSchemaVersion
     * @covers \JKingWeb\Arsse\Database::driverSchemaUpdate
     */
    public function testManuallyInitializeDatabase(): void {
        static::dbRaze(static::$drv);
        $d = new Database(false);
        $this->assertSame(0, $d->driverSchemaVersion());
        $this->assertTrue($d->driverSchemaUpdate());
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
        $this->assertFalse($d->driverSchemaUpdate());
    }

    /** @covers \JKingWeb\Arsse\Database::driverCharsetAcceptable */
    public function testCheckCharacterSetAcceptability(): void {
        $this->assertIsBool(Arsse::$db->driverCharsetAcceptable());
    }

    /** @covers \JKingWeb\Arsse\Database::driverMaintenance */
    public function testPerformMaintenance(): void {
        $this->assertTrue(Arsse::$db->driverMaintenance());
    }
}
