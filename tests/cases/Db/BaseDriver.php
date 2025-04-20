<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Db\Statement;
use JKingWeb\Arsse\Db\Result;

abstract class BaseDriver extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $insertDefaultValues = "INSERT INTO arsse_test default values";
    protected static $interface;
    protected $drv;
    protected $create;
    protected $lock;
    protected $setVersion;
    protected static $conf = [
        'dbTimeoutExec'    => 0.5,
        'dbTimeoutLock'    => 0.001,
        'dbSQLite3Timeout' => 0,
      //'dbSQLite3File' => "(temporary file)",
    ];

    public static function setUpBeforeClass(): void {
        // establish a clean baseline
        static::clearData();
        static::setConf(static::$conf);
        static::$interface = static::dbInterface();
    }

    public function setUp(): void {
        parent::setUp();
        self::setConf(static::$conf);
        if (!static::$interface) {
            $this->markTestSkipped(static::$implementation." database driver not available");
        }
        // completely clear the database and ensure the schema version can easily be altered
        static::dbRaze(static::$interface, [
            "CREATE TABLE arsse_meta(\"key\" varchar(255) primary key not null, value text)",
            "INSERT INTO arsse_meta(\"key\",value) values('schema_version','0')",
        ]);
        // construct a fresh driver for each test
        $this->drv = new static::$dbDriverClass();
    }

    public function tearDown(): void {
        // deconstruct the driver
        unset($this->drv);
        parent::tearDown();
    }

    public static function tearDownAfterClass(): void {
        if (static::$interface) {
            // completely clear the database
            static::dbRaze(static::$interface);
        }
        static::$interface = null;
        self::clearData(true);
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

    public function testFetchDriverName(): void {
        $class = get_class($this->drv);
        $this->assertTrue(strlen($class::driverName()) > 0);
    }

    public function testFetchSchemaId(): void {
        $class = get_class($this->drv);
        $this->assertTrue(strlen($class::schemaID()) > 0);
    }

    public function testCheckCharacterSetAcceptability(): void {
        $this->assertTrue($this->drv->charsetAcceptable());
    }

    public function testExecAValidStatement(): void {
        $this->assertTrue($this->drv->exec($this->create));
    }

    public function testExecAnInvalidStatement(): void {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->exec("And the meek shall inherit the earth...");
    }

    public function testExecMultipleStatements(): void {
        $this->assertTrue($this->drv->exec("$this->create; INSERT INTO arsse_test(id) values(2112)"));
        $this->assertEquals(2112, $this->query("SELECT id from arsse_test"));
    }

    public function testExecTimeout(): void {
        $this->exec($this->create);
        $this->exec($this->lock);
        $this->assertException("general", "Db", "ExceptionTimeout");
        $this->drv->exec("INSERT INTO arsse_meta(\"key\", value) values('lock', '1')");
    }

    public function testExecConstraintViolation(): void {
        $this->drv->exec("CREATE TABLE arsse_test(id varchar(255) not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->exec(static::$insertDefaultValues);
    }

    public function testExecTypeViolation(): void {
        $this->drv->exec($this->create);
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->exec("INSERT INTO arsse_test(id) values('ook')");
    }

    public function testMakeAValidQuery(): void {
        $this->assertInstanceOf(Result::class, $this->drv->query("SELECT 1"));
    }

    public function testMakeAnInvalidQuery(): void {
        $this->assertException("engineErrorGeneral", "Db");
        $this->drv->query("Apollo was astonished; Dionysus thought me mad");
    }

    public function testQueryConstraintViolation(): void {
        $this->drv->exec("CREATE TABLE arsse_test(id integer not null)");
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $this->drv->query(static::$insertDefaultValues);
    }

    public function testQueryTypeViolation(): void {
        $this->drv->exec($this->create);
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->drv->query("INSERT INTO arsse_test(id) values('ook')");
    }

    public function testPrepareAValidQuery(): void {
        $s = $this->drv->prepare("SELECT ?, ?", "int", "int");
        $this->assertInstanceOf(Statement::class, $s);
    }

    public function testPrepareAnInvalidQuery(): void {
        $this->assertException("engineErrorGeneral", "Db");
        $s = $this->drv->prepare("This is an invalid query", "int", "int")->run();
    }

    public function testCreateASavepoint(): void {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
    }

    public function testReleaseASavepoint(): void {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointRelease());
        $this->assertException("savepointInvalid", "Db");
        $this->drv->savepointRelease();
    }

    public function testUndoASavepoint(): void {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(true, $this->drv->savepointUndo());
        $this->assertException("savepointInvalid", "Db");
        $this->drv->savepointUndo();
    }

    public function testManipulateSavepoints(): void {
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

    public function testManipulateSavepointsSomeMore(): void {
        $this->assertEquals(1, $this->drv->savepointCreate());
        $this->assertEquals(2, $this->drv->savepointCreate());
        $this->assertEquals(3, $this->drv->savepointCreate());
        $this->assertEquals(4, $this->drv->savepointCreate());
        $this->assertTrue($this->drv->savepointRelease(2));
        $this->assertFalse($this->drv->savepointUndo(3));
        $this->assertException("savepointStale", "Db");
        $this->drv->savepointUndo(2);
    }

    public function testBeginATransaction(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testCommitATransaction(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->query($select));
    }

    public function testRollbackATransaction(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testBeginChainedTransactions(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testCommitChainedTransactions(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->commit();
        $this->assertEquals(0, $this->query($select));
        $tr1->commit();
        $this->assertEquals(2, $this->query($select));
    }

    public function testCommitChainedTransactionsOutOfOrder(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->commit();
        $this->assertEquals(2, $this->query($select));
        $tr2->commit();
    }

    public function testRollbackChainedTransactions(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testRollbackChainedTransactionsOutOfOrder(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->rollback();
        $this->assertEquals(0, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
    }

    public function testPartiallyRollbackChainedTransactions(): void {
        $select = "SELECT count(*) FROM arsse_test";
        $this->drv->exec($this->create);
        $tr1 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2 = $this->drv->begin();
        $this->drv->query(static::$insertDefaultValues);
        $this->assertEquals(2, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr2->rollback();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(0, $this->query($select));
        $tr1->commit();
        $this->assertEquals(1, $this->drv->query($select)->getValue());
        $this->assertEquals(1, $this->query($select));
    }

    public function testFetchSchemaVersion(): void {
        $this->assertSame(0, $this->drv->schemaVersion());
        $this->drv->exec(str_replace("#", "1", $this->setVersion));
        $this->assertSame(1, $this->drv->schemaVersion());
        $this->drv->exec(str_replace("#", "2", $this->setVersion));
        $this->assertSame(2, $this->drv->schemaVersion());
        // SQLite is unaffected by the removal of the metadata table; other backends are
        // in neither case should a query for the schema version produce an error, however
        $this->exec("DROP TABLE IF EXISTS arsse_meta");
        $exp = (static::$backend === "SQLite 3") ? 2 : 0;
        $this->assertSame($exp, $this->drv->schemaVersion());
    }

    public function testLockTheDatabase(): void {
        // PostgreSQL doesn't actually lock the whole database, only the metadata table
        // normally the application will first query this table to ensure the schema version is correct,
        // so the effect is usually the same
        $this->drv->savepointCreate(true);
        $this->assertException();
        $this->exec($this->lock);
    }

    public function testUnlockTheDatabase(): void {
        $this->drv->savepointCreate(true);
        $this->drv->savepointRelease();
        $this->drv->savepointCreate(true);
        $this->drv->savepointUndo();
        $this->assertTrue($this->exec(str_replace("#", "3", $this->setVersion)));
    }

    public function testProduceAStringLiteral(): void {
        $this->assertSame("'It''s a string!'", $this->drv->literalString("It's a string!"));
    }

    public function testPerformMaintenance(): void {
        // this performs maintenance in the absence of tables; see BaseUpdate.php for another test with tables
        $this->assertTrue($this->drv->maintenance());
    }

    public function testTranslateTokens(): void {
        $greatest = $this->drv->sqlToken("GrEatESt");
        $nocase = $this->drv->sqlToken("noCASE");
        $like = $this->drv->sqlToken("liKe");
        $integer = $this->drv->sqlToken("InTEGer");
        $asc = $this->drv->sqlToken("asc");
        $desc = $this->drv->sqlToken("desc");
        $least = $this->drv->sqlToken("leASt");

        $this->assertSame("NOT_A_TOKEN", $this->drv->sqlToken("NOT_A_TOKEN"));

        $this->assertSame("A", $this->drv->query("SELECT $least('Z', 'A')")->getValue());
        $this->assertSame("Z", $this->drv->query("SELECT $greatest('Z', 'A')")->getValue());
        $this->assertSame("Z", $this->drv->query("SELECT 'Z' collate $nocase")->getValue());
        $this->assertSame("Z", $this->drv->query("SELECT 'Z' where 'Z' $like 'z'")->getValue());
        $this->assertEquals(1, $this->drv->query("SELECT CAST((1=1) as $integer)")->getValue());
        $this->assertEquals([null, 1, 2], array_column($this->drv->query("SELECT 1 as t union select null as t union select 2 as t order by t $asc")->getAll(), "t"));
        $this->assertEquals([2, 1, null], array_column($this->drv->query("SELECT 1 as t union select null as t union select 2 as t order by t $desc")->getAll(), "t"));
    }
}
