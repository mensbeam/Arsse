<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use JKingWeb\NewsSync\Db\Statement;


class TestDbStatementSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\Db\BindingTests;

    protected $c;
	static protected $imp = Db\SQLite3\Statement::class;

    function setUp() {
		date_default_timezone_set("UTC");
        $c = new \SQLite3(":memory:");
        $c->enableExceptions(true);
        $this->c = $c;
    }

    function tearDown() {
		try {$this->s->close();} catch(\Exception $e) {}
        $this->c->close();
        unset($this->c);
    }

	protected function checkBinding($input, array $expectations) {
		$nativeStatement = $this->c->prepare("SELECT ? as value");
		$s = new self::$imp($this->c, $nativeStatement);
		$types = array_unique(Statement::TYPES);
		foreach($types as $type) {
			$s->rebindArray([$type]);
			$val = $s->runArray([$input])->getRow()['value'];
			$this->assertSame($expectations[$type], $val, "Type $type failed comparison.");
		}
	}

	function testConstructStatement() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
		$this->assertInstanceOf(Statement::class, new Db\SQLite3\Statement($this->c, $nativeStatement));
	}
	
	function testBindMissingValue() {
		$nativeStatement = $this->c->prepare("SELECT ? as value");
		$s = new self::$imp($this->c, $nativeStatement);
		$val = $s->runArray()->getRow()['value'];
		$this->assertSame(null, $val);
	}

    function testBindMultipleValues() {
        $exp = [
            'one' => 1,
            'two' => 2,
        ];
		$nativeStatement = $this->c->prepare("SELECT ? as one, ? as two");
		$s = new self::$imp($this->c, $nativeStatement, ["int", "int"]);
		$val = $s->runArray([1,2])->getRow();
		$this->assertSame($exp, $val);
    }

    function testBindWithoutType() {
        $nativeStatement = $this->c->prepare("SELECT ? as value");
		$this->assertException("paramTypeMissing", "Db");
		$s = new self::$imp($this->c, $nativeStatement, []);
		$s->runArray([1]);
    }

	function testViolateConstraint() {
		$this->c->exec("CREATE TABLE test(id integer not null)");
		$nativeStatement = $this->c->prepare("INSERT INTO test(id) values(?)");
		$s = new self::$imp($this->c, $nativeStatement, ["int"]);
		$this->assertException("constraintViolation", "Db", "ExceptionInput");
		$s->runArray([null]);
	}

	function testMismatchTypes() {
		$this->c->exec("CREATE TABLE test(id integer primary key)");
		$nativeStatement = $this->c->prepare("INSERT INTO test(id) values(?)");
		$s = new self::$imp($this->c, $nativeStatement, ["str"]);
		$this->assertException("typeViolation", "Db", "ExceptionInput");
		$s->runArray(['ook']);
	}
}