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

abstract class BaseUpdate extends \JKingWeb\Arsse\Test\AbstractTest {
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
        parent::setUp();
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

    /** @depends testPerformActualUpdate */
    public function testPerformMaintenance(): void {
        $this->drv->schemaUpdate(Database::SCHEMA_VERSION);
        $this->assertTrue($this->drv->maintenance());
    }

    public function testUpdateTo7(): void {
        $this->drv->schemaUpdate(6);
        $this->drv->exec(
            <<<QUERY_TEXT
            INSERT INTO arsse_users values
                ('a', 'xyz'), 
                ('b', 'abc');
            INSERT INTO arsse_folders(owner,name) values
                ('a', '1'), 
                ('b', '2');
            INSERT INTO arsse_feeds(id,scrape,url,favicon) values
                (1, 1, 'http://example.com/', 'http://example.com/icon'), 
                (2, 0, 'http://example.org/', 'http://example.org/icon'), 
                (3, 0, 'https://example.com/', 'http://example.com/icon'), 
                (4, 0, 'http://example.net/', null);
            INSERT INTO arsse_subscriptions(id,owner,feed) values
                (1, 'a', 1),
                (2, 'b', 1),
                (3, 'a', 2),
                (4, 'b', 2);
QUERY_TEXT
        );
        $this->drv->schemaUpdate(7);
        $exp = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["a", "xyz", 1],
                    ["b", "abc", 2],
                ]
            ],
            'arsse_folders' => [
                'columns' => ["owner", "name"],
                'rows'    => [
                    ["a", "1"],
                    ["b", "2"],
                ]
            ],
            'arsse_icons' => [
                'columns' => ["id", "url"],
                'rows'    => [
                    [1, "http://example.com/icon"],
                    [2, "http://example.org/icon"],
                ]
            ],
            'arsse_feeds' => [
                'columns' => ["url", "icon"],
                'rows'    => [
                    ["http://example.com/", 1],
                    ["http://example.org/", 2],
                    ["https://example.com/", 1],
                    ["http://example.net/", null],
                ]
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "scrape"],
                'rows'    => [
                    [1,1],
                    [2,1],
                    [3,0],
                    [4,0],
                ]
            ]
        ];
        $this->compareExpectations($this->drv, $exp);
    }
}
