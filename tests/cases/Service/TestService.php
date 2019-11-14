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

    public function setUp(): void {
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

    public function testPerformPreCleanup() {
        $this->assertTrue(Service::cleanupPre());
        \Phake::verify(Arsse::$db)->feedCleanup();
        \Phake::verify(Arsse::$db)->sessionCleanup();
    }

    public function testPerformShortPostCleanup() {
        \Phake::when(Arsse::$db)->articleCleanup()->thenReturn(0);
        $this->assertTrue(Service::cleanupPost());
        \Phake::verify(Arsse::$db)->articleCleanup();
        \Phake::verify(Arsse::$db, \Phake::times(0))->driverMaintenance();
    }

    public function testPerformFullPostCleanup() {
        \Phake::when(Arsse::$db)->articleCleanup()->thenReturn(1);
        $this->assertTrue(Service::cleanupPost());
        \Phake::verify(Arsse::$db)->articleCleanup();
        \Phake::verify(Arsse::$db)->driverMaintenance();
    }

    public function testRefreshFeeds() {
        // set up mock database actions
        \Phake::when(Arsse::$db)->metaSet->thenReturn(true);
        \Phake::when(Arsse::$db)->feedCleanup->thenReturn(true);
        \Phake::when(Arsse::$db)->sessionCleanup->thenReturn(true);
        \Phake::when(Arsse::$db)->articleCleanup->thenReturn(0);
        \Phake::when(Arsse::$db)->feedListStale->thenReturn([1,2,3]);
        // perform the test
        $d = \Phake::mock(\JKingWeb\Arsse\Service\Driver::class);
        $s = new \JKingWeb\Arsse\Test\Service($d);
        $this->assertInstanceOf(\DateTimeInterface::class, $s->watch(false));
        // verify invocations
        \Phake::verify($d)->queue(1, 2, 3);
        \Phake::verify($d)->exec();
        \Phake::verify($d)->clean();
        \Phake::verify(Arsse::$db)->feedCleanup();
        \Phake::verify(Arsse::$db)->sessionCleanup();
        \Phake::verify(Arsse::$db)->articleCleanup();
        \Phake::verify(Arsse::$db)->metaSet("service_last_checkin", $this->anything(), "datetime");
    }
}
