<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3;

use JKingWeb\Arsse\Db\Statement;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\Statement<extended>
 * @covers \JKingWeb\Arsse\Db\SQLite3\ExceptionBuilder */
class TestStatement extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $c;
    protected static $imp = \JKingWeb\Arsse\Db\SQLite3\Statement::class;

    public function setUp() {
        if (!\JKingWeb\Arsse\Db\SQLite3\Driver::requirementsMet()) {
            $this->markTestSkipped("SQLite extension not loaded");
        }
        $this->clearData();
        $c = new \SQLite3(":memory:");
        $c->enableExceptions(true);
        $this->c = $c;
    }

    public function tearDown() {
        $this->c->close();
        unset($this->c);
    }

    protected function checkBinding($input, array $expectations, bool $strict = false) {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
        $s = new self::$imp($this->c, $nativeStatement);
        $types = array_unique(Statement::TYPES);
        foreach ($types as $type) {
            $s->rebindArray([$strict ? "strict $type" : $type]);
            $val = $s->runArray([$input])->getRow()['value'];
            $this->assertSame($expectations[$type], $val, "Binding from type $type failed comparison.");
            $s->rebind(...[$strict ? "strict $type" : $type]);
            $val = $s->run(...[$input])->getRow()['value'];
            $this->assertSame($expectations[$type], $val, "Binding from type $type failed comparison.");
        }
    }

    /** @dataProvider provideBindings */
    public function testBindATypedValue($value, $type, $exp) {
        $typeStr = "'".str_replace("'", "''", $type)."'";
        $nativeStatement = $this->c->prepare(
            "SELECT (
                    (CASE WHEN substr($typeStr, 0, 7) <> 'strict ' then null else 1 end) is null 
                    and ? is null
            ) or (
                    $exp = ?
            ) as pass"
        );
        $s = new self::$imp($this->c, $nativeStatement);
        $s->rebindArray([$type, $type]);
        $act = (bool) $s->run(...[$value, $value])->getRow()['pass'];
        $this->assertTrue($act);
    }

    public function provideBindings() {
        $dateMutable = new \DateTime("Noon Today", new \DateTimezone("America/Toronto"));
        $dateImmutable = new \DateTimeImmutable("Noon Today", new \DateTimezone("America/Toronto"));
        $dateUTC = new \DateTime("@".$dateMutable->getTimestamp(), new \DateTimezone("UTC"));
        return [
        /* input,  type,              expected binding as SQL fragment */
            [null, "integer",         "null"],
            [null, "float",           "null"],
            [null, "string",          "null"],
            [null, "binary",          "null"],
            [null, "datetime",        "null"],
            [null, "boolean",         "null"],
            [null, "strict integer",  "0"],
            [null, "strict float",    "0.0"],
            [null, "strict string",   "''"],
            [null, "strict binary",   "x''"],
            [null, "strict datetime", "'1970-01-01 00:00:00'"],
            [null, "strict boolean",  "0"],
            // true
            [true, "integer",         "1"],
            [true, "float",           "1.0"],
            [true, "string",          "'1'"],
            [true, "binary",          "x'31'"],
            [true, "datetime",        "null"],
            [true, "boolean",         "1"],
            [true, "strict integer",  "1"],
            [true, "strict float",    "1.0"],
            [true, "strict string",   "'1'"],
            [true, "strict binary",   "x'31'"],
            [true, "strict datetime", "'1970-01-01 00:00:01'"],
            [true, "strict boolean",  "1"],
            // false
            [false, "integer",         "0"],
            [false, "float",           "0.0"],
            [false, "string",          "''"],
            [false, "binary",          "x''"],
            [false, "datetime",        "null"],
            [false, "boolean",         "0"],
            [false, "strict integer",  "0"],
            [false, "strict float",    "0.0"],
            [false, "strict string",   "''"],
            [false, "strict binary",   "x''"],
            [false, "strict datetime", "'1970-01-01 00:00:00'"],
            [false, "strict boolean",  "0"],
            // integer
            [2112, "integer",         "2112"],
            [2112, "float",           "2112.0"],
            [2112, "string",          "'2112'"],
            [2112, "binary",          "x'32313132'"],
            [2112, "datetime",        "'1970-01-01 00:35:12'"],
            [2112, "boolean",         "1"],
            [2112, "strict integer",  "2112"],
            [2112, "strict float",    "2112.0"],
            [2112, "strict string",   "'2112'"],
            [2112, "strict binary",   "x'32313132'"],
            [2112, "strict datetime", "'1970-01-01 00:35:12'"],
            [2112, "strict boolean",  "1"],
            // integer zero
            [0, "integer",         "0"],
            [0, "float",           "0.0"],
            [0, "string",          "'0'"],
            [0, "binary",          "x'30'"],
            [0, "datetime",        "'1970-01-01 00:00:00'"],
            [0, "boolean",         "0"],
            [0, "strict integer",  "0"],
            [0, "strict float",    "0.0"],
            [0, "strict string",   "'0'"],
            [0, "strict binary",   "x'30'"],
            [0, "strict datetime", "'1970-01-01 00:00:00'"],
            [0, "strict boolean",  "0"],
            // float
            [2112.99, "integer",         "2112"],
            [2112.99, "float",           "2112.99"],
            [2112.99, "string",          "'2112.99'"],
            [2112.99, "binary",          "x'323131322e3939'"],
            [2112.99, "datetime",        "'1970-01-01 00:35:12'"],
            [2112.99, "boolean",         "1"],
            [2112.99, "strict integer",  "2112"],
            [2112.99, "strict float",    "2112.99"],
            [2112.99, "strict string",   "'2112.99'"],
            [2112.99, "strict binary",   "x'323131322e3939'"],
            [2112.99, "strict datetime", "'1970-01-01 00:35:12'"],
            [2112.99, "strict boolean",  "1"],
            // float zero
            [0.0, "integer",         "0"],
            [0.0, "float",           "0.0"],
            [0.0, "string",          "'0'"],
            [0.0, "binary",          "x'30'"],
            [0.0, "datetime",        "'1970-01-01 00:00:00'"],
            [0.0, "boolean",         "0"],
            [0.0, "strict integer",  "0"],
            [0.0, "strict float",    "0.0"],
            [0.0, "strict string",   "'0'"],
            [0.0, "strict binary",   "x'30'"],
            [0.0, "strict datetime", "'1970-01-01 00:00:00'"],
            [0.0, "strict boolean",  "0"],
            // ASCII string
            ["Random string", "integer",         "0"],
            ["Random string", "float",           "0.0"],
            ["Random string", "string",          "'Random string'"],
            ["Random string", "binary",          "x'52616e646f6d20737472696e67'"],
            ["Random string", "datetime",        "null"],
            ["Random string", "boolean",         "1"],
            ["Random string", "strict integer",  "0"],
            ["Random string", "strict float",    "0.0"],
            ["Random string", "strict string",   "'Random string'"],
            ["Random string", "strict binary",   "x'52616e646f6d20737472696e67'"],
            ["Random string", "strict datetime", "'1970-01-01 00:00:00'"],
            ["Random string", "strict boolean",  "1"],
            // UTF-8 string
            ["é", "integer",         "0"],
            ["é", "float",           "0.0"],
            ["é", "string",          "char(233)"],
            ["é", "binary",          "x'c3a9'"],
            ["é", "datetime",        "null"],
            ["é", "boolean",         "1"],
            ["é", "strict integer",  "0"],
            ["é", "strict float",    "0.0"],
            ["é", "strict string",   "char(233)"],
            ["é", "strict binary",   "x'c3a9'"],
            ["é", "strict datetime", "'1970-01-01 00:00:00'"],
            ["é", "strict boolean",  "1"],
            // binary string
            [chr(233).chr(233), "integer",         "0"],
            [chr(233).chr(233), "float",           "0.0"],
            [chr(233).chr(233), "string",          "'".chr(233).chr(233)."'"],
            [chr(233).chr(233), "binary",          "x'e9e9'"],
            [chr(233).chr(233), "datetime",        "null"],
            [chr(233).chr(233), "boolean",         "1"],
            [chr(233).chr(233), "strict integer",  "0"],
            [chr(233).chr(233), "strict float",    "0.0"],
            [chr(233).chr(233), "strict string",   "'".chr(233).chr(233)."'"],
            [chr(233).chr(233), "strict binary",   "x'e9e9'"],
            [chr(233).chr(233), "strict datetime", "'1970-01-01 00:00:00'"],
            [chr(233).chr(233), "strict boolean",  "1"],
            // ISO 8601 date string
            ["2017-01-09T13:11:17", "integer",         "2017"],
            ["2017-01-09T13:11:17", "float",           "2017.0"],
            ["2017-01-09T13:11:17", "string",          "'2017-01-09T13:11:17'"],
            ["2017-01-09T13:11:17", "binary",          "x'323031372d30312d30395431333a31313a3137'"],
            ["2017-01-09T13:11:17", "datetime",        "'2017-01-09 13:11:17'"],
            ["2017-01-09T13:11:17", "boolean",         "1"],
            ["2017-01-09T13:11:17", "strict integer",  "2017"],
            ["2017-01-09T13:11:17", "strict float",    "2017.0"],
            ["2017-01-09T13:11:17", "strict string",   "'2017-01-09T13:11:17'"],
            ["2017-01-09T13:11:17", "strict binary",   "x'323031372d30312d30395431333a31313a3137'"],
            ["2017-01-09T13:11:17", "strict datetime", "'2017-01-09 13:11:17'"],
            ["2017-01-09T13:11:17", "strict boolean",  "1"],
            // arbitrary date string
            ["Today", "integer",         "0"],
            ["Today", "float",           "0.0"],
            ["Today", "string",          "'Today'"],
            ["Today", "binary",          "x'546f646179'"],
            ["Today", "datetime",        "'".date_create("Today", new \DateTimezone("UTC"))->format("Y-m-d H:i:s")."'"],
            ["Today", "boolean",         "1"],
            ["Today", "strict integer",  "0"],
            ["Today", "strict float",    "0.0"],
            ["Today", "strict string",   "'Today'"],
            ["Today", "strict binary",   "x'546f646179'"],
            ["Today", "strict datetime", "'".date_create("Today", new \DateTimezone("UTC"))->format("Y-m-d H:i:s")."'"],
            ["Today", "strict boolean",  "1"],
            // mutable date object
            [$dateMutable, "integer",         $dateUTC->getTimestamp()],
            [$dateMutable, "float",           $dateUTC->getTimestamp().".0"],
            [$dateMutable, "string",          "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateMutable, "binary",          "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            [$dateMutable, "datetime",        "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateMutable, "boolean",         "1"],
            [$dateMutable, "strict integer",  $dateUTC->getTimestamp()],
            [$dateMutable, "strict float",    $dateUTC->getTimestamp().".0"],
            [$dateMutable, "strict string",   "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateMutable, "strict binary",   "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            [$dateMutable, "strict datetime", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateMutable, "strict boolean",  "1"],
            // immutable date object
            [$dateImmutable, "integer",         $dateUTC->getTimestamp()],
            [$dateImmutable, "float",           $dateUTC->getTimestamp().".0"],
            [$dateImmutable, "string",          "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateImmutable, "binary",          "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            [$dateImmutable, "datetime",        "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateImmutable, "boolean",         "1"],
            [$dateImmutable, "strict integer",  $dateUTC->getTimestamp()],
            [$dateImmutable, "strict float",    $dateUTC->getTimestamp().".0"],
            [$dateImmutable, "strict string",   "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateImmutable, "strict binary",   "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            [$dateImmutable, "strict datetime", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            [$dateImmutable, "strict boolean",  "1"],
        ];
    }
    
    public function testConstructStatement() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
        $this->assertInstanceOf(Statement::class, new \JKingWeb\Arsse\Db\SQLite3\Statement($this->c, $nativeStatement));
    }

    public function testBindMissingValue() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
        $s = new self::$imp($this->c, $nativeStatement);
        $val = $s->runArray()->getRow()['value'];
        $this->assertSame(null, $val);
    }

    public function testBindMultipleValues() {
        $exp = [
            'one' => 1,
            'two' => 2,
        ];
        $nativeStatement = $this->c->prepare("SELECT ? as one, ? as two");
        $s = new self::$imp($this->c, $nativeStatement, ["int", "int"]);
        $val = $s->runArray([1,2])->getRow();
        $this->assertSame($exp, $val);
    }

    public function testBindRecursively() {
        $exp = [
            'one'   => 1,
            'two'   => 2,
            'three' => 3,
            'four'  => 4,
        ];
        $nativeStatement = $this->c->prepare("SELECT ? as one, ? as two, ? as three, ? as four");
        $s = new self::$imp($this->c, $nativeStatement, ["int", ["int", "int"], "int"]);
        $val = $s->runArray([1, [2, 3], 4])->getRow();
        $this->assertSame($exp, $val);
    }

    public function testBindWithoutType() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
        $this->assertException("paramTypeMissing", "Db");
        $s = new self::$imp($this->c, $nativeStatement, []);
        $s->runArray([1]);
    }

    public function testViolateConstraint() {
        $this->c->exec("CREATE TABLE test(id integer not null)");
        $nativeStatement = $this->c->prepare("INSERT INTO test(id) values(?)");
        $s = new self::$imp($this->c, $nativeStatement, ["int"]);
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $s->runArray([null]);
    }

    public function testMismatchTypes() {
        $this->c->exec("CREATE TABLE test(id integer primary key)");
        $nativeStatement = $this->c->prepare("INSERT INTO test(id) values(?)");
        $s = new self::$imp($this->c, $nativeStatement, ["str"]);
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $s->runArray(['ook']);
    }
}
