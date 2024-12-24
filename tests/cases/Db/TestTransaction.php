<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Transaction;
use JKingWeb\Arsse\Db\Exception;

/**
 * @covers \JKingWeb\Arsse\Db\Transaction */
class TestTransaction extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $drv;

    public function setUp(): void {
        parent::setUp();
        $drv = \Phake::mock(\JKingWeb\Arsse\Db\SQLite3\Driver::class);
        \Phake::when($drv)->savepointRelease->thenReturn(true);
        \Phake::when($drv)->savepointUndo->thenReturn(true);
        \Phake::when($drv)->savepointCreate->thenReturn(1, 2);
        $this->drv = $drv;
    }

    public function testManipulateTransactions(): void {
        $drv = $this->drv;
        $tr1 = new Transaction($drv);
        $tr2 = new Transaction($drv);
        \Phake::verify($this->drv, \Phake::times(2))->savepointCreate();
        $this->assertSame(1, $tr1->getIndex());
        $this->assertSame(2, $tr2->getIndex());
        unset($tr1);
        \Phake::verify($this->drv)->savepointUndo(1);
        unset($tr2);
        \Phake::verify($this->drv)->savepointUndo(2);
    }

    public function testCloseTransactions(): void {
        $drv = $this->drv;
        $tr1 = new Transaction($drv);
        $tr2 = new Transaction($drv);
        $this->assertTrue($tr1->isPending());
        $this->assertTrue($tr2->isPending());
        $tr1->commit();
        $this->assertFalse($tr1->isPending());
        $this->assertTrue($tr2->isPending());
        \Phake::verify($this->drv)->savepointRelease(1);
        $tr2->rollback();
        $this->assertFalse($tr1->isPending());
        $this->assertFalse($tr2->isPending());
        \Phake::verify($this->drv)->savepointUndo(2);
    }

    public function testIgnoreRollbackErrors(): void {
        \Phake::when($this->drv)->savepointUndo->thenThrow(new Exception("savepointStale"));
        $drv = $this->drv;
        $tr1 = new Transaction($drv);
        $tr2 = new Transaction($drv);
        unset($tr1, $tr2); // no exception should bubble up
        \Phake::verify($this->drv)->savepointUndo(1);
        \Phake::verify($this->drv)->savepointUndo(2);
    }
}
