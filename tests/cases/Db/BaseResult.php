<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Result;

abstract class BaseResult extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $insertDefault = "INSERT INTO arsse_test default values";
    protected static $interface;
    protected $resultClass;

    abstract protected function makeResult(string $q): array;

    public static function setUpBeforeClass(): void {
        // establish a clean baseline
        static::clearData();
        static::setConf();
        static::$interface = static::dbInterface();
    }

    public function setUp(): void {
        self::clearData();
        self::setConf();
        if (!static::$interface) {
            $this->markTestSkipped(static::$implementation." database driver not available");
        }
        // completely clear the database
        static::dbRaze(static::$interface);
        $this->resultClass = static::$dbResultClass;
    }

    public function tearDown(): void {
        self::clearData();
    }

    public static function tearDownAfterClass(): void {
        if (static::$interface) {
            // completely clear the database
            static::dbRaze(static::$interface);
        }
        static::$interface = null;
        self::clearData();
    }

    public function testConstructResult(): void {
        $this->assertInstanceOf(Result::class, new $this->resultClass(...$this->makeResult("SELECT 1")));
    }

    public function testGetChangeCountAndLastInsertId(): void {
        $this->makeResult(static::$createMeta);
        $r = new $this->resultClass(...$this->makeResult("INSERT INTO arsse_meta(\"key\",value) values('test', 1)"));
        $this->assertSame(1, $r->changes());
        $this->assertSame(0, $r->lastId());
    }

    public function testGetChangeCountAndLastInsertIdBis(): void {
        $this->makeResult(static::$createTest);
        $r = new $this->resultClass(...$this->makeResult(static::$insertDefault));
        $this->assertSame(1, $r->changes());
        $this->assertSame(1, $r->lastId());
        $r = new $this->resultClass(...$this->makeResult(static::$insertDefault));
        $this->assertSame(1, $r->changes());
        $this->assertSame(2, $r->lastId());
    }

    public function testIterateOverResults(): void {
        $exp = [0 => 1, 1 => 2, 2 => 3];
        $exp = static::$stringOutput ? $this->stringify($exp) : $exp;
        foreach (new $this->resultClass(...$this->makeResult("SELECT 1 as col union select 2 as col union select 3 as col")) as $index => $row) {
            $rows[$index] = $row['col'];
        }
        $this->assertSame($exp, $rows);
    }

    public function testIterateOverResultsTwice(): void {
        $exp = [0 => 1, 1 => 2, 2 => 3];
        $exp = static::$stringOutput ? $this->stringify($exp) : $exp;
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

    public function testGetSingleValues(): void {
        $exp = [1867, 1970, 2112];
        $exp = static::$stringOutput ? $this->stringify($exp) : $exp;
        $test = new $this->resultClass(...$this->makeResult("SELECT 1867 as year union all select 1970 as year union all select 2112 as year"));
        $this->assertSame($exp[0], $test->getValue());
        $this->assertSame($exp[1], $test->getValue());
        $this->assertSame($exp[2], $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetFirstValuesOnly(): void {
        $exp = [1867, 1970, 2112];
        $exp = static::$stringOutput ? $this->stringify($exp) : $exp;
        $test = new $this->resultClass(...$this->makeResult("SELECT 1867 as year, 19 as century union all select 1970 as year, 20 as century union all select 2112 as year, 22 as century"));
        $this->assertSame($exp[0], $test->getValue());
        $this->assertSame($exp[1], $test->getValue());
        $this->assertSame($exp[2], $test->getValue());
        $this->assertSame(null, $test->getValue());
    }

    public function testGetRows(): void {
        $exp = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new $this->resultClass(...$this->makeResult("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track"));
        $this->assertSame($exp[0], $test->getRow());
        $this->assertSame($exp[1], $test->getRow());
        $this->assertSame(null, $test->getRow());
    }

    public function testGetAllRows(): void {
        $exp = [
            ['album' => '2112',             'track' => '2112'],
            ['album' => 'Clockwork Angels', 'track' => 'The Wreckers'],
        ];
        $test = new $this->resultClass(...$this->makeResult("SELECT '2112' as album, '2112' as track union select 'Clockwork Angels' as album, 'The Wreckers' as track"));
        $this->assertEquals($exp, $test->getAll());
    }
}
