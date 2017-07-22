<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use JKingWeb\Arsse\Db\Transaction;
use Phake;

/** 
 * @covers \JKingWeb\Arsse\Db\Transaction */
class TestTransaction extends Test\AbstractTest {
    protected $drv;

    function setUp() {
        $this->clearData();
        $drv = Phake::mock(Db\SQLite3\Driver::class);
        Phake::when($drv)->savepointRelease->thenReturn(true);
        Phake::when($drv)->savepointUndo->thenReturn(true);
        Phake::when($drv)->savepointCreate->thenReturn(1)->thenReturn(2);
        $this->drv = $drv;
    }

    function testManipulateTransactions() {
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

    function testCloseTransactions() {
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

    function testIgnoreRollbackErrors() {
        Phake::when($this->drv)->savepointUndo->thenThrow(new Db\ExceptionSavepoint("stale"));
        $tr1 = new Transaction($this->drv);
        $tr2 = new Transaction($this->drv);
        unset($tr1, $tr2); // no exception should bubble up
        Phake::verify($this->drv)->savepointUndo(1);
        Phake::verify($this->drv)->savepointUndo(2);
    }
}