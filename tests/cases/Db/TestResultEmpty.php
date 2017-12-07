<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

/** @covers \JKingWeb\Arsse\Db\ResultEmpty<extended> */
class TestResultEmpty extends Test\AbstractTest {
    public function testGetChangeCountAndLastInsertId() {
        $r = new Db\ResultEmpty;
        $this->assertEquals(0, $r->changes());
        $this->assertEquals(0, $r->lastId());
    }

    public function testIterateOverResults() {
        $rows = [];
        foreach (new Db\ResultEmpty as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertEquals([], $rows);
    }

    public function testGetSingleValues() {
        $test = new Db\ResultEmpty;
        $this->assertSame(null, $test->getValue());
    }

    public function testGetRows() {
        $test = new Db\ResultEmpty;
        $this->assertSame(null, $test->getRow());
    }

    public function testGetAllRows() {
        $test = new Db\ResultEmpty;
        $rows = [];
        $this->assertEquals($rows, $test->getAll());
    }
}
