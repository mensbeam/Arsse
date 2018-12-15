<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Db\Exception;
use Phake;

/**
 * @covers \JKingWeb\Arsse\Db\Transaction */
class TestTransaction extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $drv;

    public function setUp() {
        self::clearData();
        $drv = Phake::mock(\JKingWeb\Arsse\Db\SQLite3\Driver::class);
        Phake::when($drv)->savepointRelease->thenReturn(true);
        Phake::when($drv)->savepointUndo->thenReturn(true);
        Phake::when($drv)->savepointCreate->thenReturn(1)->thenReturn(2);
        $this->drv = $drv;
    }

    public function testManipulateTransactions() {
        $tr1 = new Transaction($this->drv);
        $tr2 = new Transaction($this->drv);
        Phake::verify($this->drv, Phake::times(2))->savepointCreate;
        $this->assertSame(1, $tr1->getIndex());
        $this->assertSame(2, $tr2->getIndex());
        unset($tr1);
        Phake::verify($this->drv)->savepointUndo(1);
        unset($tr2);
        Phake::verify($this->drv)->savepointUndo(2);
    }

    public function testCloseTransactions() {
        $tr1 = new Transaction($this->drv);
        $tr2 = new Transaction($this->drv);
        $this->assertTrue($tr1->isPending());
        $this->assertTrue($tr2->isPending());
        $tr1->commit();
        $this->assertFalse($tr1->isPending());
        $this->assertTrue($tr2->isPending());
        Phake::verify($this->drv)->savepointRelease(1);
        $tr2->rollback();
        $this->assertFalse($tr1->isPending());
        $this->assertFalse($tr2->isPending());
        Phake::verify($this->drv)->savepointUndo(2);
    }

    public function testIgnoreRollbackErrors() {
        Phake::when($this->drv)->savepointUndo->thenThrow(new Exception("savepointStale"));
        $tr1 = new Transaction($this->drv);
        $tr2 = new Transaction($this->drv);
        unset($tr1, $tr2); // no exception should bubble up
        Phake::verify($this->drv)->savepointUndo(1);
        Phake::verify($this->drv)->savepointUndo(2);
    }
}
