<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\ResultEmpty;

/** @covers \JKingWeb\Arsse\Db\ResultEmpty<extended> */
class TestResultEmpty extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testGetChangeCountAndLastInsertId():void {
        $r = new ResultEmpty;
        $this->assertEquals(0, $r->changes());
        $this->assertEquals(0, $r->lastId());
    }

    public function testIterateOverResults():void {
        $rows = [];
        foreach (new ResultEmpty as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertEquals([], $rows);
    }

    public function testGetSingleValues():void {
        $test = new ResultEmpty;
        $this->assertSame(null, $test->getValue());
    }

    public function testGetRows():void {
        $test = new ResultEmpty;
        $this->assertSame(null, $test->getRow());
    }

    public function testGetAllRows():void {
        $test = new ResultEmpty;
        $rows = [];
        $this->assertEquals($rows, $test->getAll());
    }
}
