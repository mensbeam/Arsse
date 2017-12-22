<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use JKingWeb\Arsse\Db\Statement;
use JKingWeb\Arsse\Db\PDOStatement;
use JKingWeb\Arsse\Db\SQLite3\PDODriver;

/**
 * @covers \JKingWeb\Arsse\Db\PDOStatement<extended>
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestStatement extends \JKingWeb\Arsse\Test\AbstractTest {

    protected $c;
    protected static $imp = \JKingWeb\Arsse\Db\PDOStatement::class;

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

    public function testConstructStatement() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
        $this->assertInstanceOf(Statement::class, new PDOStatement($this->c, $nativeStatement));
    }

    public function testBindMissingValue() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
        $s = new self::$imp($this->c, $nativeStatement);
        $val = $s->runArray()->getRow()['value'];
        $this->assertSame(null, $val);
    }

    public function testBindMultipleValues() {
        $exp = [
            'one' => "1",
            'two' => "2",
        ];
        $nativeStatement = $this->c->prepare("SELECT ? as one, ? as two");
        $s = new self::$imp($this->c, $nativeStatement, ["int", "int"]);
        $val = $s->runArray([1,2])->getRow();
        $this->assertSame($exp, $val);
    }

    public function testBindRecursively() {
        $exp = [
            'one'   => "1",
            'two'   => "2",
            'three' => "3",
            'four'  => "4",
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
