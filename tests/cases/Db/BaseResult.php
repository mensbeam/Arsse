<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Test\DatabaseInformation;

abstract class BaseResult extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $dbInfo;
    protected static $interface;
    protected $resultClass;
    protected $stringOutput;

    abstract protected function makeResult(string $q): array;

    public static function setUpBeforeClass() {
        // establish a clean baseline
        static::clearData();
        static::$dbInfo = new DatabaseInformation(static::$implementation);
        static::setConf();
        static::$interface = (static::$dbInfo->interfaceConstructor)();
    }
    
    public function setUp() {
        self::clearData();
        self::setConf();
        if (!static::$interface) {
            $this->markTestSkipped(static::$implementation." database driver not available");
        }
        // completely clear the database
        (static::$dbInfo->razeFunction)(static::$interface);
        $this->resultClass = static::$dbInfo->resultClass;
        $this->stringOutput = static::$dbInfo->stringOutput;
    }

    public function tearDown() {
        if (static::$interface) {
            // completely clear the database
            (static::$dbInfo->razeFunction)(static::$interface);
        }
        self::clearData();
    }

    public static function tearDownAfterClass() {
        static::$implementation = null;
        static::$dbInfo = null;
        self::clearData();
    }

    public function testConstructResult() {
        $this->assertInstanceOf(Result::class, new $this->resultClass(...$this->makeResult("SELECT 1")));
    }

    public function testGetChangeCountAndLastInsertId() {
        $this->makeResult("CREATE TABLE arsse_meta(key varchar(255) primary key not null, value text)");
        $out = $this->makeResult("INSERT INTO arsse_meta(key,value) values('test', 1)");
        $rows = $out[1][0];
        $id = $out[1][1];
        $r = new $this->resultClass(...$out);
        $this->assertSame((int) $rows, $r->changes());
        $this->assertSame((int) $id, $r->lastId());
    }

    public function testIterateOverResults() {
        $exp = [0 => 1, 1 => 2, 2 => 3];
        $exp = $this->stringOutput ? $this->stringify($exp) : $exp;
        foreach (new $this->resultClass(...$this->makeResult("SELECT 1 as col union select 2 as col union select 3 as col")) as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertSame($exp, $rows);
    }

    public function testIterateOverResultsTwice() {
        $exp = [0 => 1, 1 => 2, 2 => 3];
        $exp = $this->stringOutput ? $this->stringify($exp) : $exp;
        $result = new $this->resultClass(...$this->makeResult("SELECT 1 as col union select 2 as col union select 3 as col"));
        foreach ($result as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertSame($exp, $rows);
        $this->assertException("resultReused", "Db");
        foreach ($result as $row) {
            $rows[] = $row['col'];
        }
    }

    public function testGetSingleValues() {
        $exp = [1867, 1970, 2112];
        $exp = $this->stringOutput ? $this->stringify($exp) : $exp;
        $test = new $this->resultClass(...$this->makeResult("SELECT 1867 as year union select 1970 as year union select 2112 as year"));
        $this->assertSame($exp[0], $test->getValue());
        $this->assertSame($exp[1], $test->getValue());
        $this->assertSame($exp[2], $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetFirstValuesOnly() {
        $exp = [1867, 1970, 2112];
        $exp = $this->stringOutput ? $this->stringify($exp) : $exp;
        $test = new $this->resultClass(...$this->makeResult("SELECT 1867 as year, 19 as century union select 1970 as year, 20 as century union select 2112 as year, 22 as century"));
        $this->assertSame($exp[0], $test->getValue());
        $this->assertSame($exp[1], $test->getValue());
        $this->assertSame($exp[2], $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetRows() {
        $exp = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new $this->resultClass(...$this->makeResult("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track"));
        $this->assertSame($exp[0], $test->getRow());
        $this->assertSame($exp[1], $test->getRow());
        $this->assertSame(null, $test->getRow());
    }

    public function testGetAllRows() {
        $exp = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new $this->resultClass(...$this->makeResult("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track"));
        $this->assertEquals($exp, $test->getAll());
    }
}
