<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\JKingWeb\Arsse\Db\SQLite3\Driver::class)]
class TestUpdate extends \JKingWeb\Arsse\TestCase\Db\BaseUpdate {
    use \JKingWeb\Arsse\Test\DatabaseDrivers\SQLite3;

    protected static $minimal1 = "create table arsse_meta(key text primary key not null, value text); pragma user_version=1";
    protected static $minimal2 = "pragma user_version=2";

    public static function tearDownAfterClass(): void {
        if (static::$interface) {
            static::$interface->close();
            static::$interface = null;
        }
        parent::tearDownAfterClass();
    }
}
