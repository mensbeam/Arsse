<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\Date;
use Phake;

/** @covers \JKingWeb\Arsse\Service */
class TestService extends Test\AbstractTest {
    protected $srv;

    public function setUp() {
        $this->clearData();
        Arsse::$conf = new Conf();
        Arsse::$db = Phake::mock(Database::class);
        $this->srv = new Service();
    }

    public function testComputeInterval() {
        $in = [
            Arsse::$conf->serviceFrequency,
            "PT2M",
            "PT5M",
            "P2M",
            "5M",
            "interval",
        ];
        foreach ($in as $index => $spec) {
            try {
                $exp = new \DateInterval($spec);
            } catch (\Exception $e) {
                $exp = new \DateInterval("PT2M");
            }
            Arsse::$conf->serviceFrequency = $spec;
            $this->assertEquals($exp, Service::interval(), "Interval #$index '$spec' was not correctly calculated");
        }
    }

    public function testCheckIn() {
        $now = time();
        $this->srv->checkIn();
        Phake::verify(Arsse::$db)->metaSet("service_last_checkin", Phake::capture($then), "datetime");
        $this->assertTime($now, $then);
    }

    public function testReportHavingCheckedIn() {
        // the mock's metaGet() returns null by default
        $this->assertFalse(Service::hasCheckedIn());
        $interval = Service::interval();
        $valid = (new \DateTimeImmutable("now", new \DateTimezone("UTC")))->sub($interval);
        $invalid = $valid->sub($interval)->sub($interval);
        Phake::when(Arsse::$db)->metaGet("service_last_checkin")->thenReturn(Date::transform($valid, "sql"))->thenReturn(Date::transform($invalid, "sql"));
        $this->assertTrue(Service::hasCheckedIn());
        $this->assertFalse(Service::hasCheckedIn());
    }
}
