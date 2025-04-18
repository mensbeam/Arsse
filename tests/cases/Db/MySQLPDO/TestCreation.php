<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\MySQLPDO;

use JKingWeb\Arsse\Db\MySQL\PDODriver as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\MySQL\PDODriver::class)]
class TestCreation extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        if (!Driver::requirementsMet()) {
            $this->markTestSkipped("PDO-MySQL extension not loaded");
        }
    }

    public function testFailToConnect(): void {
        // for the sake of simplicity we don't distinguish between failure modes, but the MySQL-supplied error messages do
        self::setConf([
            'dbMySQLHost' => "example.invalid",
        ]);
        $this->assertException("connectionFailure", "Db");
        new Driver;
    }
}
