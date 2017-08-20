<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use org\bovigo\vfs\vfsStream;


/** 
 * @covers \JKingWeb\Arsse\Db\SQLite3\Driver<extended>
 * @covers \JKingWeb\Arsse\Db\SQLite3\ExceptionBuilder */
class TestDbUpdateSQLite3 extends Test\AbstractTest {
    protected $data;
    protected $drv;
    protected $vfs;
    protected $base;

    const MINIMAL1 = "create table arsse_meta(key text primary key not null, value text); pragma user_version=1";
    const MINIMAL2 = "pragma user_version=2";

    function setUp(Conf $conf = null) {
        if(!extension_loaded("sqlite3")) {
            $this->markTestSkipped("SQLite extension not loaded");
        }
        $this->clearData();
        $this->vfs = vfsStream::setup("schemata", null, ['SQLite3' => []]);
        if(!$conf) {
            $conf = new Conf();
        }
        $conf->dbDriver = Db\SQLite3\Driver::class;
        $conf->dbSQLite3File = ":memory:";
        Arsse::$conf = $conf;
        $this->base = $this->vfs->url();
        $this->path = $this->base."/SQLite3/";
        $this->drv = new Db\SQLite3\Driver(true);
    }

    function tearDown() {
        unset($this->drv);
        unset($this->data);
        unset($this->vfs);
        $this->clearData();
    }

    function testLoadMissingFile() {
        $this->assertException("updateFileMissing", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    function testLoadUnreadableFile() {
        touch($this->path."0.sql");
        chmod($this->path."0.sql", 0000);
        $this->assertException("updateFileUnreadable", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    function testLoadCorruptFile() {
        file_put_contents($this->path."0.sql", "This is a corrupt file");
        $this->assertException("updateFileError", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    function testLoadIncompleteFile() {
        file_put_contents($this->path."0.sql", "create table arsse_meta(key text primary key not null, value text);");
        $this->assertException("updateFileIncomplete", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    function testLoadCorrectFile() {
        file_put_contents($this->path."0.sql", self::MINIMAL1);
        $this->drv->schemaUpdate(1, $this->base);
        $this->assertEquals(1, $this->drv->schemaVersion());
    }

    function testPerformPartialUpdate() {
        file_put_contents($this->path."0.sql", self::MINIMAL1);
        file_put_contents($this->path."1.sql", "");
        $this->assertException("updateFileIncomplete", "Db");
        try {
            $this->drv->schemaUpdate(2, $this->base);
        } catch(Exception $e) {
            $this->assertEquals(1, $this->drv->schemaVersion());
            throw $e;
        }
    }

    function testPerformSequentialUpdate() {
        file_put_contents($this->path."0.sql", self::MINIMAL1);
        file_put_contents($this->path."1.sql", self::MINIMAL2);
        $this->drv->schemaUpdate(2, $this->base);
        $this->assertEquals(2, $this->drv->schemaVersion());
    }

    function testPerformActualUpdate() {
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
        $this->assertEquals(Database::SCHEMA_VERSION, $this->drv->schemaVersion());
    }

    function testDeclineManualUpdate() {
        // turn auto-updating off
        $conf = new Conf();
        $conf->dbAutoUpdate = false;
        $this->setUp($conf);
        $this->assertException("updateManual", "Db");
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
    }

    function testDeclineDowngrade() {
        $this->assertException("updateTooNew", "Db");
        $this->drv->schemaUpdate(-1, $this->base);
    }
}