<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Statement;
use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Test\DatabaseInformation;

abstract class BaseDriver extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $dbInfo;
    protected static $interface;
    protected $drv;
    protected $create;
    protected $lock;
    protected $setVersion;
    protected static $conf = [
        'dbTimeoutExec' => 0.5,
        'dbSQLite3Timeout' => 0,
      //'dbSQLite3File' => "(temporary file)",
    ];

    public static function setUpBeforeClass() {
        // establish a clean baseline
        static::clearData();
        static::$dbInfo = new DatabaseInformation(static::$implementation);
        static::setConf(static::$conf);
        static::$interface = (static::$dbInfo->interfaceConstructor)();
    }
    
    public function setUp() {
        self::clearData();
        self::setConf(static::$conf);
        if (!static::$interface) {
            $this->markTestSkipped(static::$implementation." database driver not available");
        }
        // completely clear the database and ensure the schema version can easily be altered
        (static::$dbInfo->razeFunction)(static::$interface, [
            "CREATE TABLE arsse_meta(key varchar(255) primary key not null, value text)",
            "INSERT INTO arsse_meta(key,value) values('schema_version','0')",
        ]);
        // construct a fresh driver for each test
        $this->drv = new static::$dbInfo->driverClass;
    }

    public function tearDown() {
        // deconstruct the driver
        unset($this->drv);
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

    protected function exec($q): bool {
        // PDO implementation
        $q = (!is_array($q)) ? [$q] : $q;
        foreach ($q as $query) {
            static::$interface->exec((string) $query);
        }
        return true;
    }

    protected function query(string $q) {
        // PDO implementation
        return static::$interface->query($q)->fetchColumn();
    }

    # TESTS 
    
    public function testFetchDriverName() {
        $class = get_class($this->drv);
        $this->assertTrue(strlen($class::driverName()) > 0);
    }
    
    public function testFetchSchemaId() {
        $class = get_class($this->drv);
        $this->assertTrue(strlen($class::schemaID()) > 0);
    }

    public function testCheckCharacterSetAcceptability() {
        $this->assertTrue($this->drv->charsetAcceptable());
    }

    public function testExecAValidStatement() {
        $this->assertTrue($this->drv->exec($this->create));
    }

    public function testExecAnInvalidStatement() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->exec("And the meek shall inherit the earth...");
    }

    public function testExecMultipleStatements() {
        $this->assertTrue($this->drv->exec("$this->create; INSERT INTO arsse_test(id) values(2112)"));
        $this->assertEquals(2112, $this->query("SELECT id from arsse_test"));
    }

    public function testExecTimeout() {
        $this->exec($this->create);
        $this->exec($this->lock);
        $this->assertException("general", "Db", "ExceptionTimeout");
        $lock = is_array($this->lock) ? implode("; ",$this->lock) : $this->lock;
        $this->drv->exec($lock);
    }

    public function testExecConstraintViolation() {
        $this->drv->exec("CREATE TABLE arsse_test(id varchar(255) not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO arsse_test default values");
    }

    public function testExecTypeViolation() {
        $this->drv->exec($this->create);
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO arsse_test(id) values('ook')");
    }

    public function testMakeAValidQuery() {
        $this->assertInstanceOf(Result::class, $this->drv->query("SELECT 1"));
    }

    public function testMakeAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->query("Apollo was astonished; Dionysus thought me mad");
    }

    public function testQueryTimeout() {
        $this->exec($this->create);
        $this->exec($this->lock);
        $this->assertException("general", "Db", "ExceptionTimeout");
        $lock = is_array($this->lock) ? implode("; ",$this->lock) : $this->lock;
        $this->drv->exec($lock);
    }

    public function testQueryConstraintViolation() {
        $this->drv->exec("CREATE TABLE arsse_test(id integer not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO arsse_test default values");
    }

    public function testQueryTypeViolation() {
        $this->drv->exec($this->create);
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO arsse_test(id) values('ook')");
    }

    public function testPrepareAValidQuery() {
        $s = $this->drv->prepare("SELECT ?, ?", "int", "int");
        $this->assertInstanceOf(Statement::class, $s);
    }

    public function testPrepareAnInvalidQuery() {
        $this->assertException("engineErrorGeneral", "Db");
        $s = $this->drv->prepare("This is an invalid query", "int", "int")->run();
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
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testCommitATransaction() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->query($select));
    }

    public function testRollbackATransaction() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testBeginChainedTransactions() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testCommitChainedTransactions() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->commit();
        $this->assertEquals(0, $this->query($select));
        $tr1->commit();
        $this->assertEquals(2, $this->query($select));
    }

    public function testCommitChainedTransactionsOutOfOrder() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->commit();
        $this->assertEquals(2, $this->query($select));
        $tr2->commit();
    }

    public function testRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testRollbackChainedTransactionsOutOfOrder() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testPartiallyRollbackChainedTransactions() {
        $select = "SELECT count(*) FROM arsse_test";
        $insert = "INSERT INTO arsse_test default values";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query($insert);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->query($select));
    }

    public function testFetchSchemaVersion() {
        $this->assertSame(0, $this->drv->schemaVersion());
        $this->drv->exec(str_replace("#", "1", $this->setVersion));
        $this->assertSame(1, $this->drv->schemaVersion());
        $this->drv->exec(str_replace("#", "2", $this->setVersion));
        $this->assertSame(2, $this->drv->schemaVersion());
        // SQLite is unaffected by the removal of the metadata table; other backends are
        // in neither case should a query for the schema version produce an error, however
        $this->exec("DROP TABLE IF EXISTS arsse_meta");
        $exp = (static::$dbInfo->backend == "SQLite 3") ? 2 : 0;
        $this->assertSame($exp, $this->drv->schemaVersion());
    }

    public function testLockTheDatabase() {
        // PostgreSQL doesn't actually lock the whole database, only the metadata table
        // normally the application will first query this table to ensure the schema version is correct,
        // so the effect is usually the same
        $this->drv->savepointCreate(true);
        $this->assertException();
        $this->exec($this->lock);
    }

    public function testUnlockTheDatabase() {
        $this->drv->savepointCreate(true);
        $this->drv->savepointRelease();
        $this->drv->savepointCreate(true);
        $this->drv->savepointUndo();
        $this->assertTrue($this->exec(str_replace("#", "3", $this->setVersion)));
    }
}
