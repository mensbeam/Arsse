<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\MySQL;

use JKingWeb\Arsse\Db\MySQL\Driver as Driver;

/**
 * @group slow
 * @covers \JKingWeb\Arsse\Db\MySQL\Driver<extended>
 * @covers \JKingWeb\Arsse\Db\MySQL\ExceptionBuilder */
class TestCreation extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        if (!Driver::requirementsMet()) {
            $this->markTestSkipped("MySQL extension not loaded");
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
