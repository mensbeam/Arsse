<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Service;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Misc\Date;

/** @covers \JKingWeb\Arsse\Service */
class TestService extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $srv;

    public function setUp() {
        self::clearData();
        self::setConf();
        Arsse::$db = \Phake::mock(Database::class);
        $this->srv = new Service();
    }

    public function testCheckIn() {
        $now = time();
        $this->srv->checkIn();
        \Phake::verify(Arsse::$db)->metaSet("service_last_checkin", \Phake::capture($then), "datetime");
        $this->assertTime($now, $then);
    }

    public function testReportHavingCheckedIn() {
        // the mock's metaGet() returns null by default
        $this->assertFalse(Service::hasCheckedIn());
        $interval = Arsse::$conf->serviceFrequency;
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        \Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        $this->assertTrue(Service::hasCheckedIn());
        $this->assertFalse(Service::hasCheckedIn());
    }
}
