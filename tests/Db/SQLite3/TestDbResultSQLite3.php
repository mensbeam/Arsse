<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestDbResultSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected $c;

    function setUp() {
        $c = new \SQLite3(":memory:");
        $c->enableExceptions(true);
        $this->c = $c;
    }

    function tearDown() {
        $this->c->close();
        unset($this->c);
    }

    function testConstructResult() {
        $set = $this->c->query("SELECT 1");
        $this->assertInstanceOf(Db\ResultSQLite3::class, new Db\ResultSQLite3($set));
    }

    function testGetChangeCount() {
        $this->c->query("CREATE TABLE test(col)");
        $set = $this->c->query("INSERT INTO test(col) values(1)");
        $rows = $this->c->changes();
        $this->assertEquals($rows, (new Db\ResultSQLite3($set,$rows))->changes());
    }

    function testIterateOverResults() {
        $set = $this->c->query("SELECT 1 as col union select 2 as col union select 3 as col");
        $rows = [];
        foreach(new Db\ResultSQLite3($set) as $row) {
            $rows[] = $row['col'];
        }
        $this->assertEquals([1,2,3], $rows);
    }

    function testIterateOverResultsTwice() {
        $set = $this->c->query("SELECT 1 as col union select 2 as col union select 3 as col");
        $rows = [];
        $test = new Db\ResultSQLite3($set);
        foreach($test as $row) {
            $rows[] = $row['col'];
        }
        foreach($test as $row) {
            $rows[] = $row['col'];
        }
        $this->assertEquals([1,2,3,1,2,3], $rows);
    }

    function testGetSingleValues() {
        $set = $this->c->query("SELECT 1867 as year union select 1970 as year union select 2112 as year");
        $test = new Db\ResultSQLite3($set);
        $this->assertEquals(1867, $test->getSingle());
        $this->assertEquals(1970, $test->getSingle());
        $this->assertEquals(2112, $test->getSingle());
        $this->assertSame(null, $test->getSingle());
    }

    function testGetRows() {
        $set = $this->c->query("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track");
        $rows = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new Db\ResultSQLite3($set);
        $this->assertEquals($rows[0], $test->get());
        $this->assertEquals($rows[1], $test->get());
        $this->assertSame(null, $test->get());
    }
}