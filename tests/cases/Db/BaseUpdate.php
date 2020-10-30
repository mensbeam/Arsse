<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\Exception;
use org\bovigo\vfs\vfsStream;

class BaseUpdate extends \JKingWeb\Arsse\Test\AbstractTest {
    protected static $interface;
    protected $drv;
    protected $vfs;
    protected $base;
    protected $path;

    public static function setUpBeforeClass(): void {
        // establish a clean baseline
        static::clearData();
        static::setConf();
        static::$interface = static::dbInterface();
    }

    public function setUp(): void {
        if (!static::$interface) {
            $this->markTestSkipped(static::$implementation." database driver not available");
        }
        self::clearData();
        self::setConf();
        // construct a fresh driver for each test
        $this->drv = new static::$dbDriverClass;
        $schemaId = (get_class($this->drv))::schemaID();
        // set up a virtual filesystem for schema files
        $this->vfs = vfsStream::setup("schemata", null, [$schemaId => []]);
        $this->base = $this->vfs->url();
        $this->path = $this->base."/$schemaId/";
        // completely clear the database
        static::dbRaze(static::$interface);
    }

    public function tearDown(): void {
        // deconstruct the driver
        unset($this->drv);
        unset($this->path, $this->base, $this->vfs);
        self::clearData();
    }

    public static function tearDownAfterClass(): void {
        if (static::$interface) {
            // completely clear the database
            static::dbRaze(static::$interface);
        }
        static::$interface = null;
        self::clearData();
    }

    public function testLoadMissingFile(): void {
        $this->assertException("updateFileMissing", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadUnreadableFile(): void {
        touch($this->path."0.sql");
        chmod($this->path."0.sql", 0000);
        $this->assertException("updateFileUnreadable", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadCorruptFile(): void {
        file_put_contents($this->path."0.sql", "This is a corrupt file");
        $this->assertException("updateFileError", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadIncompleteFile(): void {
        file_put_contents($this->path."0.sql", "create table arsse_meta(\"key\" varchar(255) primary key not null, value text);");
        $this->assertException("updateFileIncomplete", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadEmptyFile(): void {
        file_put_contents($this->path."0.sql", "");
        $this->assertException("updateFileIncomplete", "Db");
        $this->drv->schemaUpdate(1, $this->base);
    }

    public function testLoadCorrectFile(): void {
        file_put_contents($this->path."0.sql", static::$minimal1);
        $this->drv->schemaUpdate(1, $this->base);
        $this->assertEquals(1, $this->drv->schemaVersion());
    }

    public function testPerformPartialUpdate(): void {
        file_put_contents($this->path."0.sql", static::$minimal1);
        file_put_contents($this->path."1.sql", "UPDATE arsse_meta set value = '1' where \"key\" = 'schema_version'");
        $this->assertException("updateFileIncomplete", "Db");
        try {
            $this->drv->schemaUpdate(2, $this->base);
        } catch (Exception $e) {
            $this->assertEquals(1, $this->drv->schemaVersion());
            throw $e;
        }
    }

    public function testPerformSequentialUpdate(): void {
        file_put_contents($this->path."0.sql", static::$minimal1);
        file_put_contents($this->path."1.sql", static::$minimal2);
        $this->drv->schemaUpdate(2, $this->base);
        $this->assertEquals(2, $this->drv->schemaVersion());
    }

    public function testPerformActualUpdate(): void {
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
        $this->assertEquals(Database::SCHEMA_VERSION, $this->drv->schemaVersion());
    }

    public function testDeclineManualUpdate(): void {
        // turn auto-updating off
        Arsse::$conf->dbAutoUpdate = false;
        $this->assertException("updateManual", "Db");
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
    }

    public function testDeclineDowngrade(): void {
        $this->assertException("updateTooNew", "Db");
        $this->drv->schemaUpdate(-1, $this->base);
    }

    public function testPerformMaintenance(): void {
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
        $this->assertTrue($this->drv->maintenance());
    }
    
    public function testUpdateTo7(): void {
        $this->drv->schemaUpdate(6);
        $this->drv->exec(<<<QUERY_TEXT
            INSERT INTO arsse_users values('a', 'xyz');
            INSERT INTO arsse_users values('b', 'abc');
            INSERT INTO arsse_folders(owner,name) values('a', '1');
            INSERT INTO arsse_folders(owner,name) values('b', '2');
QUERY_TEXT
        );
        $this->drv->schemaUpdate(7);
        $exp = [
            ['id' => "a", 'password' => "xyz", 'num' => 1],
            ['id' => "b", 'password' => "abc", 'num' => 2],
        ];
        $this->assertEquals($exp, $this->drv->query("SELECT id, password, num from arsse_users")->getAll());
        $this->assertSame(2, (int) $this->drv->query("SELECT count(*) from arsse_folders")->getValue());
    }
}
