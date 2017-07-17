<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use org\bovigo\vfs\vfsStream;


class TestDbUpdateSQLite3 extends Test\AbstractTest {
    protected $data;
    protected $drv;
    protected $vfs;
    protected $base;

    const MINIMAL1 = "create table arsse_meta(key text primary key not null, value text); pragma user_version=1";
    const MINIMAL2 = "pragma user_version=2";

    function setUp() {
        if(!extension_loaded("sqlite3")) {
            $this->markTestSkipped("SQLite extension not loaded");
        }
        $this->clearData();
        $this->vfs = vfsStream::setup("schemata", null, ['SQLite3' => []]);
        $conf = new Conf();
        $conf->dbDriver = Db\SQLite3\Driver::class;
        $conf->dbSchemaBase = $this->vfs->url();
        $this->base = $this->vfs->url()."/SQLite3/";
        $conf->dbSQLite3File = ":memory:";
        Arsse::$conf = $conf;
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
        $this->drv->schemaUpdate(1);
    }

    function testLoadUnreadableFile() {
        touch($this->base."0.sql");
        chmod($this->base."0.sql", 0000);
        $this->assertException("updateFileUnreadable", "Db");
        $this->drv->schemaUpdate(1);
    }

    function testLoadCorruptFile() {
        file_put_contents($this->base."0.sql", "This is a corrupt file");
        $this->assertException("updateFileError", "Db");
        $this->drv->schemaUpdate(1);
    }

    function testLoadIncompleteFile() {
        file_put_contents($this->base."0.sql", "create table arsse_meta(key text primary key not null, value text);");
        $this->assertException("updateFileIncomplete", "Db");
        $this->drv->schemaUpdate(1);
    }

    function testLoadCorrectFile() {
        file_put_contents($this->base."0.sql", self::MINIMAL1);
        $this->drv->schemaUpdate(1);
        $this->assertEquals(1, $this->drv->schemaVersion());
    }

    function testPerformPartialUpdate() {
        file_put_contents($this->base."0.sql", self::MINIMAL1);
        file_put_contents($this->base."1.sql", "");
        $this->assertException("updateFileIncomplete", "Db");
        try {
            $this->drv->schemaUpdate(2);
        } catch(Exception $e) {
            $this->assertEquals(1, $this->drv->schemaVersion());
            throw $e;
        }
    }

    function testPerformSequentialUpdate() {
        file_put_contents($this->base."0.sql", self::MINIMAL1);
        file_put_contents($this->base."1.sql", self::MINIMAL2);
        $this->drv->schemaUpdate(2);
        $this->assertEquals(2, $this->drv->schemaVersion());
    }

    function testPerformActualUpdate() {
        Arsse::$conf->dbSchemaBase = (new Conf())->dbSchemaBase;
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
        $this->assertEquals(Database::SCHEMA_VERSION, $this->drv->schemaVersion());
    }
}