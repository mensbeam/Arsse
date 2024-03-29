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
        parent::setUp();
        self::setConf();
        $this->dbMock = $this->mock(Database::class);
        Arsse::$db = $this->dbMock->get();
    }

    public function testConstruct(): void {
        $this->assertTrue(Driver::requirementsMet());
        $this->assertInstanceOf(DriverInterface::class, new Driver);
    }

    public function testFetchDriverName(): void {
        $this->assertTrue(strlen(Driver::driverName()) > 0);
    }

    public function testEnqueueFeeds(): void {
        $d = new Driver;
        $this->assertSame(3, $d->queue(1, 2, 3));
        $this->assertSame(5, $d->queue(4, 5));
        $this->assertSame(5, $d->clean());
        $this->assertSame(1, $d->queue(5));
    }

    public function testRefreshFeeds(): void {
        $d = new Driver;
        $d->queue(1, 4, 3);
        $this->assertSame(Arsse::$conf->serviceQueueWidth, $d->exec());
        $this->dbMock->feedUpdate->calledWith(1);
        $this->dbMock->feedUpdate->calledWith(4);
        $this->dbMock->feedUpdate->calledWith(3);
    }
}
