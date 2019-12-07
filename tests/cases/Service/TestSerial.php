<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Service;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service\Driver as DriverInterface;
use JKingWeb\Arsse\Service\Serial\Driver;

/** @covers \JKingWeb\Arsse\Service\Serial\Driver */
class TestSerial extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData();
        self::setConf();
        Arsse::$db = \Phake::mock(Database::class);
    }

    public function testConstruct() {
        $this->assertTrue(Driver::requirementsMet());
        $this->assertInstanceOf(DriverInterface::class, new Driver);
    }

    public function testFetchDriverName() {
        $this->assertTrue(strlen(Driver::driverName()) > 0);
    }

    public function testEnqueueFeeds() {
        $d = new Driver;
        $this->assertSame(3, $d->queue(1, 2, 3));
        $this->assertSame(5, $d->queue(4, 5));
        $this->assertSame(5, $d->clean());
        $this->assertSame(1, $d->queue(5));
    }

    public function testRefreshFeeds() {
        $d = new Driver;
        $d->queue(1, 4, 3);
        $this->assertSame(Arsse::$conf->serviceQueueWidth, $d->exec());
        \Phake::verify(Arsse::$db)->feedUpdate(1);
        \Phake::verify(Arsse::$db)->feedUpdate(4);
        \Phake::verify(Arsse::$db)->feedUpdate(3);
    }
}
