<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;


class TestDbDriverSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    protected $data;
    protected $drv;
    protected $ch;

    function setUp() {
        $this->clearData();
        $conf = new Conf();
        $conf->dbDriver = Db\SQLite3\Driver::class;
        $conf->dbSQLite3File = tempnam(sys_get_temp_dir(), 'ook');
        Data::$conf = $conf;
        $this->drv = new Db\SQLite3\Driver(true);
        $this->ch = new \SQLite3(Data::$conf->dbSQLite3File);
    }

    function tearDown() {
        unset($this->drv);
        unset($this->ch);
        unlink(Data::$conf->dbSQLite3File);
        $this->clearData();
    }

    function testFetchDriverName() {
        $class = Data::$conf->dbDriver;
        $this->assertTrue(strlen($class::driverName()) > 0);
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
        $this->assertEquals(2112, $this->ch->querySingle("SELECT id from test"));
    }

    function testExecTimeout() {
        $this->ch->exec("BEGIN EXCLUSIVE TRANSACTION");
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

    function testMakeAValidQuery() {
        $this->assertInstanceOf(Db\SQLite3\Result::class, $this->drv->query("SELECT 1"));
    }

    function testMakeAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->query("Apollo was astonished; Dionysus thought me mad");
    }

    function testQueryTimeout() {
        $this->ch->exec("BEGIN EXCLUSIVE TRANSACTION");
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

    function testPrepareAValidQuery() {
        $s = $this->drv->prepare("SELECT ?, ?", "int", "int");
        $this->assertInstanceOf(Db\SQLite3\Statement::class, $s);
    }

    function testPrepareAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $s = $this->drv->prepare("This is an invalid query", "int", "int");
    }

    function testCreateASavepoint() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
    }

    function testReleaseASavepoint() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointRelease());
        $this->assertException("invalid", "Db", "ExceptionSavepoint");
        $this->drv->savepointRelease();
    }

    function testUndoASavepoint() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointUndo());
        $this->assertException("invalid", "Db", "ExceptionSavepoint");
        $this->drv->savepointUndo();
    }

    function testManipulateSavepoints() {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
        $this->assertEquals(4, $this->drv->savepointCreate());
        $this->assertEquals(5, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointUndo(3));
        $this->assertEquals(false, $this->drv->savepointRelease(4));
        $this->assertEquals(6, $this->drv->savepointCreate());
        $this->assertEquals(false, $this->drv->savepointRelease(5));
        $this->assertEquals(true, $this->drv->savepointRelease(6));
        $this->assertEquals(3, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointRelease(2));
        $this->assertException("stale", "Db", "ExceptionSavepoint");
        $this->drv->savepointRelease(2);
    }

    function testBeginATransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
    }

    function testCommitATransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->ch->querySingle($select));
    }

    function testRollbackATransaction() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
    }

    function testBeginChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
    }

    function testCommitChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2->commit();
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr1->commit();
        $this->assertEquals(2, $this->ch->querySingle($select));
    }

    function testCommitChainedTransactionsOutOfOrder() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr1->commit();
        $this->assertEquals(2, $this->ch->querySingle($select));
        $tr2->commit();
    }

    function testRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
    }

    function testRollbackChainedTransactionsOutOfOrder() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
    }

    function testPartiallyRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM test";
        $insert = "INSERT INTO test(id) values(null)";
        $this->drv->exec("CREATE TABLE test(id integer primary key)");
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->ch->querySingle($select));
        $tr1->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->ch->querySingle($select));
    }

    function testFetchSchemaVersion() {
        $this->assertSame(0, $this->drv->schemaVersion());
        $this->drv->exec("PRAGMA user_version=1");
        $this->assertSame(1, $this->drv->schemaVersion());
        $this->drv->exec("PRAGMA user_version=2");
        $this->assertSame(2, $this->drv->schemaVersion());

    }

    function testManipulateAdvisoryLock() {
        $this->assertTrue($this->drv->unlock());
        $this->assertFalse($this->drv->isLocked());
        $this->assertTrue($this->drv->lock());
        $this->assertFalse($this->drv->isLocked());
        $this->drv->exec("CREATE TABLE arsse_settings(key primary key, value, type) without rowid; PRAGMA user_version=1");
        $this->assertTrue($this->drv->lock());
        $this->assertTrue($this->drv->isLocked());
        $this->assertFalse($this->drv->lock());
        $this->drv->exec("PRAGMA user_version=0");
        $this->assertFalse($this->drv->isLocked());
        $this->assertTrue($this->drv->lock());
        $this->assertFalse($this->drv->isLocked());
        $this->drv->exec("PRAGMA user_version=1");
        $this->assertTrue($this->drv->isLocked());
        $this->assertTrue($this->drv->unlock());
        $this->assertFalse($this->drv->isLocked());
    }
}