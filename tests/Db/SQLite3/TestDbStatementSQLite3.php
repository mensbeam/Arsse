<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestDbStatementSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected $c;
	protected $s;

    function setUp() {
		date_default_timezone_set("UTC");
        $c = new \SQLite3(":memory:");
        $c->enableExceptions(true);
		$s = $c->prepare("SELECT ? as value");
        $this->c = $c;
		$this->s = $s;
    }

    function tearDown() {
		try {$this->s->close();} catch(\Exception $e) {}
        $this->c->close();
		unset($this->s);
        unset($this->c);
    }

	function testConstructStatement() {
		$this->assertInstanceOf(Db\StatementSQLite3::class, new Db\StatementSQLite3($this->c, $this->s));
	}

	function testBindMissingValue() {
		$s = new Db\StatementSQLite3($this->c, $this->s);
		$val = $s->runArray()->get()['value'];
		$this->assertSame(null, $val);
	}

	function testBindNull() {
		$exp = [
			"null"      => null,
			"integer"   => null,
			"float"     => null,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => null,
			"text"      => null,
			"boolean"   => null,
		];
		$s = new Db\StatementSQLite3($this->c, $this->s);
		$types = array_unique(Db\Statement::TYPES);
		foreach($types as $type) {
			$s->rebindArray([$type]);
			$val = $s->runArray([null])->get()['value'];
			$this->assertSame($exp[$type], $val);
		}
	}

	function testBindInteger() {
		$exp = [
		"null"      => null,
		"integer"   => 2112,
		"float"     => 2112.0,
		"date"      => date('Y-m-d', 2112),
		"time"      => date('h:i:sP', 2112),
		"datetime"  => date('Y-m-d h:i:sP', 2112),
		"binary"    => "2112",
		"text"      => "2112",
		"boolean"   => 1,
		];
		$s = new Db\StatementSQLite3($this->c, $this->s);
		$types = array_unique(Db\Statement::TYPES);
		foreach($types as $type) {
			$s->rebindArray([$type]);
			$val = $s->runArray([2112])->get()['value'];
			$this->assertSame($exp[$type], $val, "Type $type failed comparison.");
		}
	}
	
}