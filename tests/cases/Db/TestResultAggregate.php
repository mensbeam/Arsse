<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\ResultAggregate;
use JKingWeb\Arsse\Test\Result;

/** @covers \JKingWeb\Arsse\Db\ResultAggregate<extended> */
class TestResultAggregate extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testGetChangeCountAndLastInsertId():void {
        $in = [
            new Result([], 3, 4),
            new Result([], 27, 10),
            new Result([], 12, 2112),
        ];
        $r = new ResultAggregate(...$in);
        $this->assertEquals(42, $r->changes());
        $this->assertEquals(2112, $r->lastId());
    }

    public function testIterateOverResults():void {
        $in = [
            new Result([['col' => 1]]),
            new Result([['col' => 2]]),
            new Result([['col' => 3]]),
        ];
        $rows = [];
        foreach (new ResultAggregate(...$in) as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertEquals([0 => 1, 1 => 2, 2 => 3], $rows);
    }

    public function testIterateOverResultsTwice():void {
        $in = [
            new Result([['col' => 1]]),
            new Result([['col' => 2]]),
            new Result([['col' => 3]]),
        ];
        $rows = [];
        $test = new ResultAggregate(...$in);
        foreach ($test as $row) {
            $rows[] = $row['col'];
        }
        $this->assertEquals([1,2,3], $rows);
        $this->assertException("resultReused", "Db");
        foreach ($test as $row) {
            $rows[] = $row['col'];
        }
    }

    public function testGetSingleValues():void {
        $test = new ResultAggregate(...[
            new Result([['year' => 1867]]),
            new Result([['year' => 1970]]),
            new Result([['year' => 2112]]),
        ]);
        $this->assertEquals(1867, $test->getValue());
        $this->assertEquals(1970, $test->getValue());
        $this->assertEquals(2112, $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetFirstValuesOnly():void {
        $test = new ResultAggregate(...[
            new Result([['year' => 1867, 'century' => 19]]),
            new Result([['year' => 1970, 'century' => 20]]),
            new Result([['year' => 2112, 'century' => 22]]),
        ]);
        $this->assertEquals(1867, $test->getValue());
        $this->assertEquals(1970, $test->getValue());
        $this->assertEquals(2112, $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetRows():void {
        $test = new ResultAggregate(...[
            new Result([['album' => '2112',             'track' => '2112']]),
            new Result([['album' => 'Clockwork Angels', 'track' => 'The Wreckers']]),
        ]);
        $rows = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $this->assertEquals($rows[0], $test->getRow());
        $this->assertEquals($rows[1], $test->getRow());
        $this->assertSame(null, $test->getRow());
    }

    public function testGetAllRows():void {
        $test = new ResultAggregate(...[
            new Result([['album' => '2112',             'track' => '2112']]),
            new Result([['album' => 'Clockwork Angels', 'track' => 'The Wreckers']]),
        ]);
        $rows = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $this->assertEquals($rows, $test->getAll());
    }
}
