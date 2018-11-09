<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Db\PDOResult;
use JKingWeb\Arsse\Db\SQLite3\PDODriver;

/** 
 * @covers \JKingWeb\Arsse\Db\PDOResult<extended> 
 * @covers \JKingWeb\Arsse\Db\SQLite3\Result<extended>
 */
class TestResult extends \JKingWeb\Arsse\Test\AbstractTest {
    public function provideDrivers() {
        $drvSqlite3 = (function() {
            if (\JKingWeb\Arsse\Db\SQLite3\Driver::requirementsMet()) {
                $d = new \SQLite3(":memory:");
                $d->enableExceptions(true);
                return $d;
            }
        })();
        $drvPdo = (function() {
            if (\JKingWeb\Arsse\Db\SQLite3\PDODriver::requirementsMet()) {
                return new \PDO("sqlite::memory:", "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            }
        })();
        return [
            'SQLite 3' => [isset($drvSqlite3), false, \JKingWeb\Arsse\Db\SQLite3\Result::class, function(string $query) use($drvSqlite3) {
                $set = $drvSqlite3->query($query);
                $rows = $drvSqlite3->changes();
                $id = $drvSqlite3->lastInsertRowID();
                return [$set, [$rows, $id]];
            }],
            'PDO' => [isset($drvPdo), true, \JKingWeb\Arsse\Db\PDOResult::class, function(string $query) use($drvPdo) {
                $set = $drvPdo->query($query);
                $rows = $set->rowCount();
                $id = $drvPdo->lastInsertID();
                return [$set, [$rows, $id]];
            }],
        ];
    }

    /** @dataProvider provideDrivers */
    public function testConstructResult(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $this->assertInstanceOf(Result::class, new $class(...$func("SELECT 1")));
    }

    /** @dataProvider provideDrivers */
    public function testGetChangeCountAndLastInsertId(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $func("CREATE TABLE if not exists arsse_meta(key varchar(255) primary key not null, value text)");
        $out = $func("INSERT INTO arsse_meta(key,value) values('test', 1)");
        $rows = $out[1][0];
        $id = $out[1][1];
        $r = new $class(...$out);
        $this->assertSame((int) $rows, $r->changes());
        $this->assertSame((int) $id, $r->lastId());
    }

    /** @dataProvider provideDrivers */
    public function testIterateOverResults(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [0 => 1, 1 => 2, 2 => 3];
        $exp = $stringCoersion ? $this->stringify($exp) : $exp;
        foreach (new $class(...$func("SELECT 1 as col union select 2 as col union select 3 as col")) as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertSame($exp, $rows);
    }

    /** @dataProvider provideDrivers */
    public function testIterateOverResultsTwice(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [0 => 1, 1 => 2, 2 => 3];
        $exp = $stringCoersion ? $this->stringify($exp) : $exp;
        $result = new $class(...$func("SELECT 1 as col union select 2 as col union select 3 as col"));
        foreach ($result as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertSame($exp, $rows);
        $this->assertException("resultReused", "Db");
        foreach ($result as $row) {
            $rows[] = $row['col'];
        }
    }

    /** @dataProvider provideDrivers */
    public function testGetSingleValues(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [1867, 1970, 2112];
        $exp = $stringCoersion ? $this->stringify($exp) : $exp;
        $test = new $class(...$func("SELECT 1867 as year union select 1970 as year union select 2112 as year"));
        $this->assertSame($exp[0], $test->getValue());
        $this->assertSame($exp[1], $test->getValue());
        $this->assertSame($exp[2], $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    /** @dataProvider provideDrivers */
    public function testGetFirstValuesOnly(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [1867, 1970, 2112];
        $exp = $stringCoersion ? $this->stringify($exp) : $exp;
        $test = new $class(...$func("SELECT 1867 as year, 19 as century union select 1970 as year, 20 as century union select 2112 as year, 22 as century"));
        $this->assertSame($exp[0], $test->getValue());
        $this->assertSame($exp[1], $test->getValue());
        $this->assertSame($exp[2], $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    /** @dataProvider provideDrivers */
    public function testGetRows(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new $class(...$func("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track"));
        $this->assertSame($exp[0], $test->getRow());
        $this->assertSame($exp[1], $test->getRow());
        $this->assertSame(null, $test->getRow());
    }

    /** @dataProvider provideDrivers */
    public function testGetAllRows(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new $class(...$func("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track"));
        $this->assertEquals($exp, $test->getAll());
    }
}
