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

    public function testUpdateTo8(): void {
        $this->drv->schemaUpdate(7);
        $this->drv->exec(
            <<<QUERY_TEXT
            INSERT INTO arsse_icons(id, url) values
                (4,  'https://example.org/icon'),
                (12, 'https://example.net/icon');
            INSERT INTO arsse_users values
                ('a', 'xyz', 1, 0), 
                ('b', 'abc', 2, 0),
                ('c', 'gfy', 5, 1);
            INSERT INTO arsse_folders(id, owner, parent, name) values
                (1337, 'a', null, 'ook'),
                (4400, 'c', null, 'eek');
            INSERT INTO arsse_feeds values
                (1, 'https://example.com/rss', 'Title 1', 'https://example.com/', '2001-06-13 06:55:23', '2001-06-13 06:56:23', '2001-06-13 06:57:23', '2001-06-13 06:54:23', '"ook"', 42,  'Some error', 'johndoe', 'secret', 47,   null),
                -- This feed has no subscriptions, so should not be seen in the new table
                (2, 'https://example.org/rss', 'Title 2', 'https://example.org/', '2001-06-14 06:55:23', '2001-06-14 06:56:23', '2001-06-14 06:57:23', '2001-06-14 06:54:23', '"eek"', 5,   'That error', 'janedoe', 'secret', 2112, 4),
                (3, 'https://example.net/rss', 'Title 3', 'https://example.net/', '2001-06-15 06:55:23', '2001-06-15 06:56:23', '2001-06-15 06:57:23', '2001-06-15 06:54:23', '"ack"', 44,  'This error', '',        '',       3,    12);
            INSERT INTO arsse_subscriptions values
                (1, 'a', 1, '2002-02-02 00:02:03', '2002-02-02 00:05:03', 'User Title', 2, 1, null, 'keep', 'block', 0),
                (4, 'a', 3, '2002-02-03 00:02:03', '2002-02-03 00:05:03', 'Rosy Title', 1, 0, 1337, 'meep', 'bloop', 0),
                (6, 'c', 3, '2002-02-04 00:02:03', '2002-02-04 00:05:03', null,         2, 0, 4400, null,   null,    1);
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
                'columns' => ["id", "owner", "url", "feed_title", "title", "folder", "last_mod", "etag", "next_fetch", "added", "source", "updated", "err_count", "err_msg", "size", "icon", "modified", "order_type", "pinned", "scrape", "keep_rule", "block_rule"],
                'rows'    => [
                    [1, "a", "https://example.com/rss", "Title 1", "User Title", null, "2001-06-13 06:56:23", '"ook"', "2001-06-13 06:57:23", "2002-02-02 00:02:03", "https://example.com/", "2001-06-13 06:55:23", 42, "Some error", 47, null, "2002-02-02 00:05:03", 2, 1, 0, "keep", "block"],
                    [4, "a", "https://example.net/rss", "Title 3", "Rosy Title", 1337, "2001-06-15 06:56:23", '"ack"', "2001-06-15 06:57:23", "2002-02-03 00:02:03", "https://example.net/", "2001-06-15 06:55:23", 44, "This error", 3,  12,   "2002-02-03 00:05:03", 1, 0, 0, "meep", "bloop"],
                    [6, "c", "https://example.net/rss", "Title 3", null,         4400, "2001-06-15 06:56:23", '"ack"', "2001-06-15 06:57:23", "2002-02-04 00:02:03", "https://example.net/", "2001-06-15 06:55:23", 44, "This error", 3,  12,   "2002-02-04 00:05:03", 2, 0, 1, null,   null],
                ]
            ]
        ];
        $this->compareExpectations($this->drv, $exp);
    }
}

/*

create table arsse_subscriptions(
-- users' subscriptions to newsfeeds, with settings
    id integer primary key,                                                                 -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade,     -- owner of subscription
    url text not null,                                                                      -- URL of feed
    feed_title text collate nocase,                                                         -- feed title
    title text collate nocase,                                                              -- user-supplied title, which overrides the feed title when set
    folder integer references arsse_folders(id) on delete cascade,                          -- TT-RSS category (nestable); the first-level category (which acts as Nextcloud folder) is joined in when needed
    last_mod text,                                                                          -- time at which the feed last actually changed at the foreign host
    etag text not null default '',                                                          -- HTTP ETag hash used for cache validation, changes each time the content changes
    next_fetch text,                                                                        -- time at which the feed should next be fetched
    added text not null default CURRENT_TIMESTAMP,                                          -- time at which feed was added
    source text,                                                                            -- URL of site to which the feed belongs
    updated text,                                                                           -- time at which the feed was last fetched
    err_count integer not null default 0,                                                   -- count of successive times update resulted in error since last successful update
    err_msg text,                                                                           -- last error message
    size integer not null default 0,                                                        -- number of articles in the feed at last fetch
    icon integer references arsse_icons(id) on delete set null,                             -- numeric identifier of any associated icon
    modified text not null default CURRENT_TIMESTAMP,                                       -- time at which subscription properties were last modified by the user
    order_type int not null default 0,                                                      -- Nextcloud sort order
    pinned int not null default 0,                                                          -- whether feed is pinned (always sorts at top)
    scrape int not null default 0,                                                          -- whether the user has requested scraping content from source articles
    keep_rule text,                                                                         -- Regular expression the subscription's articles must match to avoid being hidden
    block_rule text,                                                                        -- Regular expression the subscription's articles must not match to avoid being hidden
    unique(owner,url)                                                                       -- a URL with particular credentials should only appear once
);

create table arsse_feeds(
-- newsfeeds, deduplicated
-- users have subscriptions to these feeds in another table
    id integer primary key,                                        -- sequence number
    url text not null,                                             -- URL of feed
    title text collate nocase,                                     -- default title of feed (users can set the title of their subscription to the feed)
    source text,                                                   -- URL of site to which the feed belongs
    updated text,                                                  -- time at which the feed was last fetched
    modified text,                                                 -- time at which the feed last actually changed
    next_fetch text,                                               -- time at which the feed should next be fetched
    orphaned text,                                                 -- time at which the feed last had no subscriptions
    etag text not null default '',                                 -- HTTP ETag hash used for cache validation, changes each time the content changes
    err_count integer not null default 0,                          -- count of successive times update resulted in error since last successful update
    err_msg text,                                                  -- last error message
    username text not null default '',                             -- HTTP authentication username
    password text not null default '',                             -- HTTP authentication password (this is stored in plain text)
    size integer not null default 0,                               -- number of articles in the feed at last fetch
    icon integer references arsse_icons(id) on delete set null,    -- numeric identifier of any associated icon
    unique(url,username,password)                                  -- a URL with particular credentials should only appear once
)

create table arsse_subscriptions(
-- users' subscriptions to newsfeeds, with settings
    id integer primary key,                                                             -- sequence number
    owner text not null references arsse_users(id) on delete cascade on update cascade, -- owner of subscription
    feed integer not null references arsse_feeds(id) on delete cascade,                 -- feed for the subscription
    added text not null default CURRENT_TIMESTAMP,                                      -- time at which feed was added
    modified text not null default CURRENT_TIMESTAMP,                                   -- time at which subscription properties were last modified
    title text collate nocase,                                                          -- user-supplied title
    order_type int not null default 0,                                                  -- Nextcloud sort order
    pinned boolean not null default 0,                                                  -- whether feed is pinned (always sorts at top)
    folder integer references arsse_folders(id) on delete cascade,                      -- TT-RSS category (nestable); the first-level category (which acts as Nextcloud folder) is joined in when needed
    keep_rule text default null,
    block_rule text default null,
    scape boolean not null default 0,
    unique(owner,feed)                                                                  -- a given feed should only appear once for a given owner
);
*/