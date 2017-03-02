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
		$s = new Db\StatementSQLite3($this->c, $this->s);
		$val = $s->runArray([null])->get()['value'];
		$this->assertSame(null, $val);
	}

	function testBindInteger() {
		$s = new Db\StatementSQLite3($this->c, $this->s, ["int"]);
		$val = $s->runArray([2112])->get()['value'];
		$this->assertSame(2112, $val);
	}
}