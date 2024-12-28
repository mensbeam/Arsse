<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use PHPUnit\Framework\Attributes\CoversMethod;

trait SeriesMiscellany {
    protected function setUpSeriesMiscellany(): void {
        static::setConf([
            'dbDriver' => static::$dbDriverClass,
        ]);
    }

    protected function tearDownSeriesMiscellany(): void {
    }

    #[CoversMethod(Database::class, "__construct")]
    #[CoversMethod(Database::class, "driverSchemaVersion")]
    #[CoversMethod(Database::class, "driverSchemaUpdate")]
    public function testInitializeDatabase(): void {
        static::dbRaze(static::$drv);
        $d = new Database(true);
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
    }

    #[CoversMethod(Database::class, "__construct")]
    #[CoversMethod(Database::class, "driverSchemaVersion")]
    #[CoversMethod(Database::class, "driverSchemaUpdate")]
    public function testManuallyInitializeDatabase(): void {
        static::dbRaze(static::$drv);
        $d = new Database(false);
        $this->assertSame(0, $d->driverSchemaVersion());
        $this->assertTrue($d->driverSchemaUpdate());
        $this->assertSame(Database::SCHEMA_VERSION, $d->driverSchemaVersion());
        $this->assertFalse($d->driverSchemaUpdate());
    }

    #[CoversMethod(Database::class, "driverCharsetAcceptable")]
    public function testCheckCharacterSetAcceptability(): void {
        $this->assertIsBool(Arsse::$db->driverCharsetAcceptable());
    }

    #[CoversMethod(Database::class, "driverMaintenance")]
    public function testPerformMaintenance(): void {
        $this->assertTrue(Arsse::$db->driverMaintenance());
    }
}
