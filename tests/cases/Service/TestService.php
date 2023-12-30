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
        parent::setUp();
        self::setConf();
        $this->dbMock = $this->mock(Database::class);
        Arsse::$db = $this->dbMock->get();
        $this->srv = new Service;
    }

    public function testCheckIn(): void {
        $now = time();
        $this->srv->checkIn();
        $this->dbMock->metaSet->calledWith("service_last_checkin", "~", "datetime");
        $then = $this->dbMock->metaSet->firstCall()->argument(1);
        $this->assertTime($now, $then);
    }

    public function testReportHavingCheckedIn(): void {
        // the mock's metaGet() returns null by default
        $this->assertFalse(Service::hasCheckedIn());
        $interval = Arsse::$conf->serviceFrequency;
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        $this->dbMock->metaGet->with("service_last_checkin")->returns(Date::transform($valid, "sql"), Date::transform($invalid, "sql"));
        Arsse::$db = $this->dbMock->get();
        $this->assertTrue(Service::hasCheckedIn());
        $this->assertFalse(Service::hasCheckedIn());
    }

    public function testPerformPreCleanup(): void {
        $this->assertTrue(Service::cleanupPre());
        $this->dbMock->subscriptionCleanup->called();
        $this->dbMock->iconCleanup->called();
        $this->dbMock->sessionCleanup->called();
    }

    public function testPerformShortPostCleanup(): void {
        $this->dbMock->articleCleanup->returns(0);
        Arsse::$db = $this->dbMock->get();
        $this->assertTrue(Service::cleanupPost());
        $this->dbMock->articleCleanup->Called();
        $this->dbMock->driverMaintenance->never()->called();
    }

    public function testPerformFullPostCleanup(): void {
        $this->dbMock->articleCleanup->returns(1);
        Arsse::$db = $this->dbMock->get();
        $this->assertTrue(Service::cleanupPost());
        $this->dbMock->articleCleanup->called();
        $this->dbMock->driverMaintenance->called();
    }

    public function testRefreshFeeds(): void {
        // set up mock database actions
        $this->dbMock->metaSet->returns(true);
        $this->dbMock->subscriptionCleanup->returns(true);
        $this->dbMock->sessionCleanup->returns(true);
        $this->dbMock->articleCleanup->returns(0);
        $this->dbMock->feedListStale->returns([1,2,3]);
        // perform the test
        Arsse::$db = $this->dbMock->get();
        $d = $this->mock(\JKingWeb\Arsse\Service\Driver::class);
        $s = new \JKingWeb\Arsse\Test\Service($d->get());
        $this->assertInstanceOf(\DateTimeInterface::class, $s->watch(false));
        // verify invocations
        $d->queue->calledWith(1, 2, 3);
        $d->exec->called();
        $d->clean->called();
        $this->dbMock->subscriptionCleanup->called();
        $this->dbMock->iconCleanup->called();
        $this->dbMock->sessionCleanup->called();
        $this->dbMock->articleCleanup->called();
        $this->dbMock->metaSet->calledWith("service_last_checkin", $this->anything(), "datetime");
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
