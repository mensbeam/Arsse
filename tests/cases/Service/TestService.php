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
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\JKingWeb\Arsse\Service::class)]
class TestService extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $srv;

    public function setUp(): void {
        parent::setUp();
        self::setConf();
        Arsse::$db = \Phake::mock(Database::class);
        $this->srv = new Service;
    }

    public function testCheckIn(): void {
        $now = time();
        $this->srv->checkIn();
        \Phake::verify(Arsse::$db)->metaSet("service_last_checkin", \Phake::capture($then), "datetime");
        $this->assertTime($now, $then);
    }

    public function testReportHavingCheckedIn(): void {
        // the mock's metaGet() returns null by default
        $this->assertFalse(Service::hasCheckedIn());
        $interval = Arsse::$conf->serviceFrequency;
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        \Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        $this->assertTrue(Service::hasCheckedIn());
        $this->assertFalse(Service::hasCheckedIn());
    }

    public function testPerformPreCleanup(): void {
        $this->assertTrue(Service::cleanupPre());
        \Phake::verify(Arsse::$db)->subscriptionCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->iconCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->sessionCleanup(\Phake::anyParameters());
    }

    public function testPerformShortPostCleanup(): void {
        \Phake::when(Arsse::$db)->articleCleanup->thenReturn(0);
        $this->assertTrue(Service::cleanupPost());
        \Phake::verify(Arsse::$db)->articleCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db, \Phake::never())->driverMaintenance(\Phake::anyParameters());
    }

    public function testPerformFullPostCleanup(): void {
        \Phake::when(Arsse::$db)->articleCleanup->thenReturn(1);
        $this->assertTrue(Service::cleanupPost());
        \Phake::verify(Arsse::$db)->articleCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->driverMaintenance(\Phake::anyParameters());
    }

    public function testRefreshFeeds(): void {
        // set up mock database actions
        \Phake::when(Arsse::$db)->metaSet->thenReturn(true);
        \Phake::when(Arsse::$db)->subscriptionCleanup->thenReturn(true);
        \Phake::when(Arsse::$db)->sessionCleanup->thenReturn(true);
        \Phake::when(Arsse::$db)->articleCleanup->thenReturn(0);
        \Phake::when(Arsse::$db)->feedListStale->thenReturn([1,2,3]);
        // perform the test
        $d = \Phake::mock(\JKingWeb\Arsse\Service\Driver::class);
        $s = new \JKingWeb\Arsse\Test\Service($d);
        $this->assertInstanceOf(\DateTimeInterface::class, $s->watch(false));
        // verify invocations
        \Phake::verify($d)->queue(1, 2, 3);
        \Phake::verify($d)->exec(\Phake::anyParameters());
        \Phake::verify($d)->clean(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->subscriptionCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->iconCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->sessionCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->articleCleanup(\Phake::anyParameters());
        \Phake::verify(Arsse::$db)->metaSet("service_last_checkin", $this->anything(), "datetime");
    }

    public function testReloadTheService(): void {
        $u = Arsse::$user;
        $l = Arsse::$lang;
        $d = Arsse::$db;
        $o = Arsse::$obj;
        $c = Arsse::$conf;
        $this->srv->reload();
        $this->assertNotSame($u, Arsse::$user);
        $this->assertNotSame($l, Arsse::$lang);
        $this->assertNotSame($d, Arsse::$db);
        $this->assertNotSame($o, Arsse::$obj);
        $this->assertNotSame($c, Arsse::$conf);
    }
}
