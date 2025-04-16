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
        $this->drv = new static::$dbDriverClass();
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

    public function testUpdateTo8(): void {
        $this->drv->schemaUpdate(7);
        $this->drv->exec(
            <<<QUERY_TEXT
            INSERT INTO arsse_icons(id, url) values
                (4,  'https://example.org/icon'),
                (12, 'https://example.net/icon');
            insert into arsse_feeds(id, url, title, source, updated, modified, next_fetch, orphaned, etag, err_count, err_msg, username, password, size, icon) values
                (1, 'https://example.com/rss', 'Title 1', 'https://example.com/', '2001-06-13 06:55:23', '2001-06-13 06:56:23', '2001-06-13 06:57:23', '2001-06-13 06:54:23', '"ook"', 42,  'Some error', 'johndoe', 'secret', 47,   null),
                -- This feed has no subscriptions, so should not be seen in the new table
                (2, 'https://example.org/rss', 'Title 2', 'https://example.org/', '2001-06-14 06:55:23', '2001-06-14 06:56:23', '2001-06-14 06:57:23', '2001-06-14 06:54:23', '"eek"', 5,   'That error', 'janedoe', 'secret', 2112, 4),
                (3, 'https://example.net/rss', 'Title 3', 'https://example.net/', '2001-06-15 06:55:23', '2001-06-15 06:56:23', '2001-06-15 06:57:23', '2001-06-15 06:54:23', '"ack"', 44,  'This error', '',        '',       3,    12);
            insert into arsse_users(id,password,num,admin) values
                ('a', 'xyz', 1, 0), 
                ('b', 'abc', 2, 0),
                ('c', 'gfy', 5, 1);
            insert into arsse_folders(id, owner, parent, name) values
                (1337, 'a', null, 'ook'),
                (4400, 'c', null, 'eek');
            insert into arsse_subscriptions(id, owner, feed, added, modified, title, order_type, pinned, folder, keep_rule, block_rule, scrape) values
                (1, 'a', 1, '2002-02-02 00:02:03', '2002-02-02 00:05:03', 'User Title', 2, 1, null, 'keep', 'block', 0),
                (4, 'a', 3, '2002-02-03 00:02:03', '2002-02-03 00:05:03', 'Rosy Title', 1, 0, 1337, 'meep', 'bloop', 0),
                (6, 'c', 3, '2002-02-04 00:02:03', '2002-02-04 00:05:03', null,         2, 0, 4400, null,   null,    1);
            insert into arsse_articles(id, feed, url, title, author, published, edited, modified, guid, url_title_hash, url_content_hash, title_content_hash, content_scraped, content) values
                (1, 1, 'https://example.com/1', 'Article 1', 'John Doe', '2001-11-08 22:07:55', '2002-11-08 07:51:12', '2001-11-08 23:44:56', 'GUID1', 'UTHASH1', 'UCHASH1', 'TCHASH1', 'Scraped 1', 'Content 1'),
                (2, 1, 'https://example.com/2', 'Article 2', 'Jane Doe', '2001-11-09 22:07:55', '2002-11-09 07:51:12', '2001-11-09 23:44:56', 'GUID2', 'UTHASH2', 'UCHASH2', 'TCHASH2', 'Scraped 2', 'Content 2'),
                (3, 2, 'https://example.org/1', 'Article 3', 'John Doe', '2001-11-10 22:07:55', '2002-11-10 07:51:12', '2001-11-10 23:44:56', 'GUID3', 'UTHASH3', 'UCHASH3', 'TCHASH3', 'Scraped 3', 'Content 3'),
                (4, 2, 'https://example.org/2', 'Article 4', 'Jane Doe', '2001-11-11 22:07:55', '2002-11-11 07:51:12', '2001-11-11 23:44:56', 'GUID4', 'UTHASH4', 'UCHASH4', 'TCHASH4', 'Scraped 4', 'Content 4'),
                (5, 3, 'https://example.net/1', 'Article 5', 'Adam Doe', '2001-11-12 22:07:55', '2002-11-12 07:51:12', '2001-11-12 23:44:56', 'GUID5', 'UTHASH5', 'UCHASH5', 'TCHASH5', null,        'Content 5'),
                (6, 3, 'https://example.net/2', 'Article 6', 'Evie Doe', '2001-11-13 22:07:55', '2002-11-13 07:51:12', '2001-11-13 23:44:56', 'GUID6', 'UTHASH6', 'UCHASH6', 'TCHASH6', 'Scraped 6', 'Content 6');
            insert into arsse_marks(article, subscription, "read", starred, modified, note, hidden) values
                (1, 1, 1, 1, '2002-11-08 00:37:22', 'Note 1', 0),
                (5, 4, 1, 0, '2002-11-12 00:37:22', 'Note 5', 0),
                (5, 6, 0, 1, '2002-12-12 00:37:22', '',       0),
                (6, 6, 0, 0, '2002-12-13 00:37:22', 'Note 6', 1);
            insert into arsse_editions(article, modified) values
                (1, '2000-01-01 00:00:00'),
                (1, '2000-02-01 00:00:00'),
                (2, '2000-01-02 00:00:00'),
                (2, '2000-02-02 00:00:00'),
                (3, '2000-01-03 00:00:00'),
                (3, '2000-02-03 00:00:00'),
                (4, '2000-01-04 00:00:00'),
                (4, '2000-02-04 00:00:00'),
                (5, '2000-01-05 00:00:00'),
                (5, '2000-02-05 00:00:00'),
                (6, '2000-01-06 00:00:00'),
                (6, '2000-02-06 00:00:00');
            insert into arsse_enclosures(article, url, type) values
                (2, 'http://example.com/2/enclosure', 'image/png'),
                (3, 'http://example.org/3/enclosure', 'image/jpg'),
                (4, 'http://example.org/4/enclosure', 'audio/aac'),
                (5, 'http://example.net/5/enclosure', 'application/octet-stream');
            insert into arsse_categories(article, name) values
                (1, 'Sport'),
                (2, 'Opinion'),
                (2, 'Gourds'),
                (3, 'Politics'),
                (6, 'Medicine'),
                (6, 'Drugs'),
                (6, 'Technology');
            insert into arsse_labels(id, owner, name) values
                (1, 'a', 'Follow-up'),
                (2, 'a', 'For Gabriel!'),
                (3, 'c', 'Maple'),
                (4, 'c', 'Brown sugar');
            insert into arsse_label_members(label, article, subscription, assigned, modified) values
                (2, 2, 1, 1, '2023-09-01 11:22:33'),
                (1, 2, 1, 0, '2023-09-02 11:22:33'),
                (1, 5, 4, 1, '2023-09-03 11:22:33'),
                (4, 5, 6, 0, '2023-09-04 11:22:33');
QUERY_TEXT
        );
        $this->drv->schemaUpdate(8);
        $exp = [
            'arsse_users' => [
                'columns' => ["id", "password", "num", "admin"],
                'rows'    => [
                    ["a", "xyz", 1, 0],
                    ["b", "abc", 2, 0],
                    ["c", "gfy", 5, 1],
                ]
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "feed_title", "title", "folder", "last_mod", "etag", "next_fetch", "added", "source", "updated", "err_count", "err_msg", "size", "icon", "modified", "order_type", "pinned", "scrape", "keep_rule", "block_rule", "deleted"],
                'rows'    => [
                    [1, "a", "https://example.com/rss", "Title 1", "User Title", null, "2001-06-13 06:56:23", '"ook"', "2001-06-13 06:57:23", "2002-02-02 00:02:03", "https://example.com/", "2001-06-13 06:55:23", 42, "Some error", 47, null, "2002-02-02 00:05:03", 2, 1, 0, "keep", "block", 0],
                    [4, "a", "https://example.net/rss", "Title 3", "Rosy Title", 1337, "2001-06-15 06:56:23", '"ack"', "2001-06-15 06:57:23", "2002-02-03 00:02:03", "https://example.net/", "2001-06-15 06:55:23", 44, "This error", 3,  12,   "2002-02-03 00:05:03", 1, 0, 0, "meep", "bloop", 0],
                    [6, "c", "https://example.net/rss", "Title 3", null,         4400, "2001-06-15 06:56:23", '"ack"', "2001-06-15 06:57:23", "2002-02-04 00:02:03", "https://example.net/", "2001-06-15 06:55:23", 44, "This error", 3,  12,   "2002-02-04 00:05:03", 2, 0, 1, null,   null,    0],
                ]
            ],
            'arsse_articles' => [
                'columns' => ["id", "subscription", "read", "starred", "hidden", "touched", "published", "edited", "added", "modified", "marked", "url", "title", "author", "guid", "url_title_hash", "url_content_hash", "title_content_hash", "note"],
                'rows'    => [
                    [1,  1, 1, 1, 0, 0, "2001-11-08 22:07:55", "2002-11-08 07:51:12", "2001-11-08 23:44:56", "2001-11-08 23:44:56", "2002-11-08 00:37:22", "https://example.com/1", "Article 1", "John Doe", "GUID1", "UTHASH1", "UCHASH1", "TCHASH1", "Note 1"],
                    [2,  1, 0, 0, 0, 0, "2001-11-09 22:07:55", "2002-11-09 07:51:12", "2001-11-09 23:44:56", "2001-11-09 23:44:56", null,                  "https://example.com/2", "Article 2", "Jane Doe", "GUID2", "UTHASH2", "UCHASH2", "TCHASH2", ""],
                    [7,  4, 1, 0, 0, 0, "2001-11-12 22:07:55", "2002-11-12 07:51:12", "2001-11-12 23:44:56", "2001-11-12 23:44:56", "2002-11-12 00:37:22", "https://example.net/1", "Article 5", "Adam Doe", "GUID5", "UTHASH5", "UCHASH5", "TCHASH5", "Note 5"],
                    [8,  6, 0, 1, 0, 0, "2001-11-12 22:07:55", "2002-11-12 07:51:12", "2001-11-12 23:44:56", "2001-11-12 23:44:56", "2002-12-12 00:37:22", "https://example.net/1", "Article 5", "Adam Doe", "GUID5", "UTHASH5", "UCHASH5", "TCHASH5", ""],
                    [9,  4, 0, 0, 0, 0, "2001-11-13 22:07:55", "2002-11-13 07:51:12", "2001-11-13 23:44:56", "2001-11-13 23:44:56", null,                  "https://example.net/2", "Article 6", "Evie Doe", "GUID6", "UTHASH6", "UCHASH6", "TCHASH6", ""],
                    [10, 6, 0, 0, 1, 0, "2001-11-13 22:07:55", "2002-11-13 07:51:12", "2001-11-13 23:44:56", "2001-11-13 23:44:56", "2002-12-13 00:37:22", "https://example.net/2", "Article 6", "Evie Doe", "GUID6", "UTHASH6", "UCHASH6", "TCHASH6", "Note 6"],
                ]
            ],
            'arsse_article_contents' => [
                'columns' => ["id", "content"],
                'rows'    => [
                    [1,  "Content 1"],
                    [2,  "Content 2"],
                    [7,  "Content 5"],
                    [8,  "Content 5"],
                    [9,  "Content 6"],
                    [10, "Scraped 6"],
                ]
            ],
            'arsse_editions' => [
                'columns' => ["id", "article", "modified"],
                'rows'    => [
                    [1,  1,  "2000-01-01 00:00:00"],
                    [2,  1,  "2000-02-01 00:00:00"],
                    [3,  2,  "2000-01-02 00:00:00"],
                    [4,  2,  "2000-02-02 00:00:00"],
                    [13, 7,  "2000-01-05 00:00:00"],
                    [14, 7,  "2000-02-05 00:00:00"],
                    [15, 8,  "2000-01-05 00:00:00"],
                    [16, 8,  "2000-02-05 00:00:00"],
                    [17, 9,  "2000-01-06 00:00:00"],
                    [18, 9,  "2000-02-06 00:00:00"],
                    [19, 10, "2000-01-06 00:00:00"],
                    [20, 10, "2000-02-06 00:00:00"],
                ]
            ],
            'arsse_enclosures' => [
                'columns' => ["article", "url", "type"],
                'rows'    => [
                    [2, "http://example.com/2/enclosure", "image/png"],
                    [7, "http://example.net/5/enclosure", "application/octet-stream"],
                    [8, "http://example.net/5/enclosure", "application/octet-stream"],
                ]
            ],
            'arsse_categories' => [
                'columns' => ["article", "name"],
                'rows'    => [
                    [1,  "Sport"],
                    [2,  "Opinion"],
                    [2,  "Gourds"],
                    [9,  "Medicine"],
                    [9,  "Drugs"],
                    [9,  "Technology"],
                    [10, "Medicine"],
                    [10, "Drugs"],
                    [10, "Technology"],
                ]
            ],
            'arsse_label_members' => [
                'columns' => ["label", "article", "assigned", "modified"],
                'rows'    => [
                    [2, 2, 1, '2023-09-01 11:22:33'],
                    [1, 2, 0, '2023-09-02 11:22:33'],
                    [1, 7, 1, '2023-09-03 11:22:33'],
                    [4, 8, 0, '2023-09-04 11:22:33'],
                ]
            ]
        ];
        $this->compareExpectations($this->drv, $exp);
    }
}
