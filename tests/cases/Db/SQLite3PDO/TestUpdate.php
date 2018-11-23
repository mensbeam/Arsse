<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\SQLite3\PDODriver;
use org\bovigo\vfs\vfsStream;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\PDODriver<extended>
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestUpdate extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $data;
    protected $drv;
    protected $vfs;
    protected $base;

    const MINIMAL1 = "create table arsse_meta(key text primary key not null, value text); pragma user_version=1";
    const MINIMAL2 = "pragma user_version=2";

    public function setUp(array $conf = []) {
        if (!PDODriver::requirementsMet()) {
            $this->markTestSkipped("PDO-SQLite extension not loaded");
        }
        self::clearData();
        $this->vfs = vfsStream::setup("schemata", null, ['SQLite3' => []]);
        $conf['dbDriver'] = PDODriver::class;
        self::setConf($conf);
        $this->base = $this->vfs->url();
        $this->path = $this->base."/SQLite3/";
        $this->drv = new PDODriver();
    }

    public function tearDown() {
        unset($this->drv);
        unset($this->data);
        unset($this->vfs);
        self::clearData();
    }

    public function testLoadMissingFile() {
        $this->assertException("updateFileMissing", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadUnreadableFile() {
        touch($this->path."0.sql");
        chmod($this->path."0.sql", 0000);
        $this->assertException("updateFileUnreadable", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadCorruptFile() {
        file_put_contents($this->path."0.sql", "This is a corrupt file");
        $this->assertException("updateFileError", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadIncompleteFile() {
        file_put_contents($this->path."0.sql", "create table arsse_meta(key text primary key not null, value text);");
        $this->assertException("updateFileIncomplete", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadEmptyFile() {
        file_put_contents($this->path."0.sql", "");
        $this->assertException("updateFileIncomplete", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadCorrectFile() {
        file_put_contents($this->path."0.sql", self::MINIMAL1);
        $this->drv->schemaUpdate(1, $this->base);
        $this->assertEquals(1, $this->drv->schemaVersion());
    }

    public function testPerformPartialUpdate() {
        file_put_contents($this->path."0.sql", self::MINIMAL1);
        file_put_contents($this->path."1.sql", " ");
        $this->assertException("updateFileIncomplete", "Db");
        try {
            $this->drv->schemaUpdate(2, $this->base);
        } catch (Exception $e) {
            $this->assertEquals(1, $this->drv->schemaVersion());
            throw $e;
        }
    }

    public function testPerformSequentialUpdate() {
        file_put_contents($this->path."0.sql", self::MINIMAL1);
        file_put_contents($this->path."1.sql", self::MINIMAL2);
        $this->drv->schemaUpdate(2, $this->base);
        $this->assertEquals(2, $this->drv->schemaVersion());
    }

    public function testPerformActualUpdate() {
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
        $this->assertEquals(Database::SCHEMA_VERSION, $this->drv->schemaVersion());
    }

    public function testDeclineManualUpdate() {
        // turn auto-updating off
        $this->setUp(['dbAutoUpdate' => false]);
        $this->assertException("updateManual", "Db");
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
    }

    public function testDeclineDowngrade() {
        $this->assertException("updateTooNew", "Db");
        $this->drv->schemaUpdate(-1, $this->base);
    }
}
