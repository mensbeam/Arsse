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
        $drv = $this->mock(\JKingWeb\Arsse\Db\SQLite3\Driver::class);
        $drv->savepointRelease->returns(true);
        $drv->savepointUndo->returns(true);
        $drv->savepointCreate->returns(1, 2);
        $this->drv = $drv;
    }

    public function testManipulateTransactions(): void {
        $drv = $this->drv->get();
        $tr1 = new Transaction($drv);
        $tr2 = new Transaction($drv);
        $this->drv->savepointCreate->twice()->called();
        $this->assertSame(1, $tr1->getIndex());
        $this->assertSame(2, $tr2->getIndex());
        unset($tr1);
        $this->drv->savepointUndo->calledWith(1);
        unset($tr2);
        $this->drv->savepointUndo->calledWith(2);
    }

    public function testCloseTransactions(): void {
        $drv = $this->drv->get();
        $tr1 = new Transaction($drv);
        $tr2 = new Transaction($drv);
        $this->assertTrue($tr1->isPending());
        $this->assertTrue($tr2->isPending());
        $tr1->commit();
        $this->assertFalse($tr1->isPending());
        $this->assertTrue($tr2->isPending());
        $this->drv->savepointRelease->calledWith(1);
        $tr2->rollback();
        $this->assertFalse($tr1->isPending());
        $this->assertFalse($tr2->isPending());
        $this->drv->savepointUndo->calledWith(2);
    }

    public function testIgnoreRollbackErrors(): void {
        $this->drv->savepointUndo->throws(new Exception("savepointStale"));
        $drv = $this->drv->get();
        $tr1 = new Transaction($drv);
        $tr2 = new Transaction($drv);
        unset($tr1, $tr2); // no exception should bubble up
        $this->drv->savepointUndo->calledWith(1);
        $this->drv->savepointUndo->calledWith(2);
    }
}
