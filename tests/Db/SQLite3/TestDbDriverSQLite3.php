<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestDbDriverSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected $c;

    function setUp() {
		$conf = new Conf();
		$conf->dbDriver = Db\SQLite3\Driver::class;
		$conf->dbSQLite3File = tempnam(sys_get_temp_dir(), 'ook');
		$this->data = new Test\RuntimeData($conf);
		$this->drv = new Db\SQLite3\Driver($this->data, true);
    }

    function tearDown() {
        unset($this->drv);
		unlink($this->data->conf->dbSQLite3File);
    }

	function testExecAValidStatement() {
		$this->assertTrue($this->drv->exec("CREATE TABLE test(id integer primary key)"));
	}

	function testExecAnInvalidStatement() {
		$this->assertException("engineErrorGeneral", "Db");
		$this->drv->exec("And the meek shall inherit the earth...");
	}

	function testExecMultipleStatements() {
		$this->assertTrue($this->drv->exec("CREATE TABLE test(id integer primary key); INSERT INTO test(id) values(2112)"));
		$ch = new \SQLite3($this->data->conf->dbSQLite3File);
		$this->assertEquals(2112, $ch->querySingle("SELECT id from test"));
	}

	function testExecTimeout() {
		$ch = new \SQLite3($this->data->conf->dbSQLite3File);
		$ch->exec("BEGIN EXCLUSIVE TRANSACTION");
		$this->assertException("general", "Db", "ExceptionTimeout");
		$this->drv->exec("CREATE TABLE test(id integer primary key)");
	}

	function testExecConstraintViolation() {
		$this->drv->exec("CREATE TABLE test(id integer not null)");
		$this->assertException("constraintViolation", "Db", "ExceptionInput");
		$this->drv->exec("INSERT INTO test(id) values(null)");
	}

	function testExecTypeViolation() {
		$this->drv->exec("CREATE TABLE test(id integer primary key)");
		$this->assertException("typeViolation", "Db", "ExceptionInput");
		$this->drv->exec("INSERT INTO test(id) values('ook')");
	}

	function testValidQuery() {
		$this->assertInstanceOf(Db\SQLite3\Result::class, $this->drv->query("SELECT 1"));
	}

	function testInvalidQuery() {
		$this->assertException("engineErrorGeneral", "Db");
		$this->drv->query("Apollo was astonished; Dionysus thought me mad");
	}

	function testQueryTimeout() {
		$ch = new \SQLite3($this->data->conf->dbSQLite3File);
		$ch->exec("BEGIN EXCLUSIVE TRANSACTION");
		$this->assertException("general", "Db", "ExceptionTimeout");
		$this->drv->query("CREATE TABLE test(id integer primary key)");
	}

	function testQueryConstraintViolation() {
		$this->drv->exec("CREATE TABLE test(id integer not null)");
		$this->assertException("constraintViolation", "Db", "ExceptionInput");
		$this->drv->query("INSERT INTO test(id) values(null)");
	}

	function testQueryTypeViolation() {
		$this->drv->exec("CREATE TABLE test(id integer primary key)");
		$this->assertException("typeViolation", "Db", "ExceptionInput");
		$this->drv->query("INSERT INTO test(id) values('ook')");
	}
}