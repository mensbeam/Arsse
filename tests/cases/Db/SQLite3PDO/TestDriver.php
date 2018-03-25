<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\SQLite3\PDODriver;
use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Db\Statement;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\PDODriver<extended>
 * @covers \JKingWeb\Arsse\Db\PDODriver
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestDriver extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $data;
    protected $drv;
    protected $ch;

    public function setUp() {
        if (!PDODriver::requirementsMet()) {
            $this->markTestSkipped("PDO-SQLite extension not loaded");
        }
        $this->clearData();
        $conf = new Conf();
        Arsse::$conf = $conf;
        $conf->dbDriver = PDODriver::class;
        $conf->dbSQLite3Timeout = 0;
        $conf->dbSQLite3File = tempnam(sys_get_temp_dir(), 'ook');
        $this->drv = new PDODriver();
        $this->ch = new \PDO("sqlite:".Arsse::$conf->dbSQLite3File, "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public function tearDown() {
        unset($this->drv);
        unset($this->ch);
        if (isset(Arsse::$conf)) {
            unlink(Arsse::$conf->dbSQLite3File);
        }
        $this->clearData();
    }

    public function testFetchDriverName() {
        $class = Arsse::$conf->dbDriver;
        $this->assertTrue(strlen($class::driverName()) > 0);
    }

    public function testCheckCharacterSetAcceptability() {
        $this->assertTrue($this->drv->charsetAcceptable());
    }

    public function testExecAValidStatement() {
        $this->assertTrue($this->drv->exec("CREATE TABLE test(id integer primary key)"));
    }

    public function testExecAnInvalidStatement() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->exec("And the meek shall inherit the earth...");
    }

    public function testExecMultipleStatements() {
        $this->assertTrue($this->drv->exec("CREATE TABLE test(id integer primary key); INSERT INTO test(id) values(2112)"));
        $this->assertEquals(2112, $this->ch->query("SELECT id from test")->fetchColumn());
    }

    public function testExecTimeout() {
        $this->ch->exec("BEGIN EXCLUSIVE TRANSACTION");
        $this->assertException("general", "Db", "ExceptionTimeout");
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
    }

    public function testExecConstraintViolation() {
        $this->drv->exec("CREATE TABLE test(id integer not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO test(id) values(null)");
    }

    public function testExecTypeViolation() {
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO test(id) values('ook')");
    }

    public function testMakeAValidQuery() {
        $this->assertInstanceOf(Result::class, $this->drv->query("SELECT 1"));
    }

    public function testMakeAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->query("Apollo was astonished; Dionysus thought me mad");
    }

    public function testQueryTimeout() {
        $this->ch->exec("BEGIN EXCLUSIVE TRANSACTION");
        $this->assertException("general", "Db", "ExceptionTimeout");
        $this->drv->query("CREATE TABLE test(id integer primary key)");
    }

    public function testQueryConstraintViolation() {
        $this->drv->exec("CREATE TABLE test(id integer not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO test(id) values(null)");
    }

    public function testQueryTypeViolation() {
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO test(id) values('ook')");
    }

    public function testPrepareAValidQuery() {
        $s = $this->drv->prepare("SELECT ?, ?", "int", "int");
        $this->assertInstanceOf(Statement::class, $s);
    }

    public function testPrepareAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $s = $this->drv->prepare("This is an invalid query", "int", "int");
    }

    public function testCreateASavepoint() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
    }

    public function testReleaseASavepoint() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointRelease());
        $this->assertException("savepointInvalid", "Db");
        $this->drv->savepointRelease();
    }

    public function testUndoASavepoint() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointUndo());
        $this->assertException("savepointInvalid", "Db");
        $this->drv->savepointUndo();
    }

    public function testManipulateSavepoints() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
        $this->assertEquals(4, $this->drv->savepointCreate());
        $this->assertEquals(5, $this->drv->savepointCreate());
        $this->assertTrue($this->drv->savepointUndo(3));
        $this->assertFalse($this->drv->savepointRelease(4));
        $this->assertEquals(6, $this->drv->savepointCreate());
        $this->assertFalse($this->drv->savepointRelease(5));
        $this->assertTrue($this->drv->savepointRelease(6));
        $this->assertEquals(3, $this->drv->savepointCreate());
        $this->assertTrue($this->drv->savepointRelease(2));
        $this->assertException("savepointStale", "Db");
        $this->drv->savepointRelease(2);
    }

    public function testManipulateSavepointsSomeMore() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
        $this->assertEquals(4, $this->drv->savepointCreate());
        $this->assertTrue($this->drv->savepointRelease(2));
        $this->assertFalse($this->drv->savepointUndo(3));
        $this->assertException("savepointStale", "Db");
        $this->drv->savepointUndo(2);
    }

    public function testBeginATransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
    }

    public function testCommitATransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->ch->query($select)->fetchColumn());
    }

    public function testRollbackATransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
    }

    public function testBeginChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
    }

    public function testCommitChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2->commit();
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr1->commit();
        $this->assertEquals(2, $this->ch->query($select)->fetchColumn());
    }

    public function testCommitChainedTransactionsOutOfOrder() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr1->commit();
        $this->assertEquals(2, $this->ch->query($select)->fetchColumn());
        $tr2->commit();
    }

    public function testRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
    }

    public function testRollbackChainedTransactionsOutOfOrder() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
    }

    public function testPartiallyRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->query($select)->fetchColumn());
        $tr1->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->ch->query($select)->fetchColumn());
    }

    public function testFetchSchemaVersion() {
        $this->assertSame(0, $this->drv->schemaVersion());
        $this->drv->exec("PRAGMA user_version=1");
        $this->assertSame(1, $this->drv->schemaVersion());
        $this->drv->exec("PRAGMA user_version=2");
        $this->assertSame(2, $this->drv->schemaVersion());
    }

    public function testLockTheDatabase() {
        $this->drv->savepointCreate(true);
        $this->ch->exec("PRAGMA busy_timeout = 0");
        $this->assertException();
        $this->ch->exec("CREATE TABLE test(id integer primary key)");
    }

    public function testUnlockTheDatabase() {
        $this->drv->savepointCreate(true);
        $this->drv->savepointRelease();
        $this->drv->savepointCreate(true);
        $this->drv->savepointUndo();
        $this->assertSame(0, $this->ch->exec("CREATE TABLE test(id integer primary key)"));
    }
}