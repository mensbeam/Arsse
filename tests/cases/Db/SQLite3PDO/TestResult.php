<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Db\PDOResult;
use JKingWeb\Arsse\Db\SQLite3\PDODriver;

/** @covers \JKingWeb\Arsse\Db\PDOResult<extended> */
class TestResult extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $c;

    public function setUp() {
        if (!PDODriver::requirementsMet()) {
            $this->markTestSkipped("PDO-SQLite extension not loaded");
        }
        $this->clearData();
        $c = new \PDO("sqlite::memory:", "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->c = $c;
    }

    public function tearDown() {
        unset($this->c);
        $this->clearData();
    }

    public function testConstructResult() {
        $set = $this->c->query("SELECT 1");
        $this->assertInstanceOf(Result::class, new PDOResult($set));
    }

    public function testGetChangeCountAndLastInsertId() {
        $this->c->query("CREATE TABLE test(col)");
        $set = $this->c->query("INSERT INTO test(col) values(1)");
        $rows = $set->rowCount();
        $id = $this->c->lastInsertID();
        $r = new PDOResult($set, [$rows,$id]);
        $this->assertSame((int) $rows, $r->changes());
        $this->assertSame((int) $id, $r->lastId());
    }

    public function testIterateOverResults() {
        $set = $this->c->query("SELECT 1 as col union select 2 as col union select 3 as col");
        $rows = [];
        foreach (new PDOResult($set) as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertSame([0 => "1", 1 => "2", 2 => "3"], $rows);
    }

    public function testIterateOverResultsTwice() {
        $set = $this->c->query("SELECT 1 as col union select 2 as col union select 3 as col");
        $rows = [];
        $test = new PDOResult($set);
        foreach ($test as $row) {
            $rows[] = $row['col'];
        }
        $this->assertSame(["1","2","3"], $rows);
        $this->assertException("resultReused", "Db");
        foreach ($test as $row) {
            $rows[] = $row['col'];
        }
    }

    public function testGetSingleValues() {
        $set = $this->c->query("SELECT 1867 as year union select 1970 as year union select 2112 as year");
        $test = new PDOResult($set);
        $this->assertEquals(1867, $test->getValue());
        $this->assertEquals(1970, $test->getValue());
        $this->assertEquals(2112, $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetFirstValuesOnly() {
        $set = $this->c->query("SELECT 1867 as year, 19 as century union select 1970 as year, 20 as century union select 2112 as year, 22 as century");
        $test = new PDOResult($set);
        $this->assertEquals(1867, $test->getValue());
        $this->assertEquals(1970, $test->getValue());
        $this->assertEquals(2112, $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetRows() {
        $set = $this->c->query("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track");
        $rows = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new PDOResult($set);
        $this->assertEquals($rows[0], $test->getRow());
        $this->assertEquals($rows[1], $test->getRow());
        $this->assertSame(null, $test->getRow());
    }

    public function testGetAllRows() {
        $set = $this->c->query("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track");
        $rows = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new PDOResult($set);
        $this->assertEquals($rows, $test->getAll());
    }
}
