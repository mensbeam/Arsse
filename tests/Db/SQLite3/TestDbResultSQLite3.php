<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;


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
        $this->assertInstanceOf(Db\Result::class, new Db\SQLite3\Result($set));
    }

    function testGetChangeCountAndLastInsertId() {
        $this->c->query("CREATE TABLE test(col)");
        $set = $this->c->query("INSERT INTO test(col) values(1)");
        $rows = $this->c->changes();
        $id = $this->c->lastInsertRowID();
        $r = new Db\SQLite3\Result($set,[$rows,$id]);
        $this->assertEquals($rows, $r->changes());
        $this->assertEquals($id, $r->lastId());
    }

    function testIterateOverResults() {
        $set = $this->c->query("SELECT 1 as col union select 2 as col union select 3 as col");
        $rows = [];
        foreach(new Db\SQLite3\Result($set) as $row) {
            $rows[] = $row['col'];
        }
        $this->assertEquals([1,2,3], $rows);
    }

    function testIterateOverResultsTwice() {
        $set = $this->c->query("SELECT 1 as col union select 2 as col union select 3 as col");
        $rows = [];
        $test = new Db\SQLite3\Result($set);
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
        $test = new Db\SQLite3\Result($set);
        $this->assertEquals(1867, $test->getValue());
        $this->assertEquals(1970, $test->getValue());
        $this->assertEquals(2112, $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    function testGetFirstValuesOnly() {
        $set = $this->c->query("SELECT 1867 as year, 19 as century union select 1970 as year, 20 as century union select 2112 as year, 22 as century");
        $test = new Db\SQLite3\Result($set);
        $this->assertEquals(1867, $test->getValue());
        $this->assertEquals(1970, $test->getValue());
        $this->assertEquals(2112, $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    function testGetRows() {
        $set = $this->c->query("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track");
        $rows = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new Db\SQLite3\Result($set);
        $this->assertEquals($rows[0], $test->getRow());
        $this->assertEquals($rows[1], $test->getRow());
        $this->assertSame(null, $test->getRow());
        $this->assertEquals($rows, $test->getAll());
    }
}