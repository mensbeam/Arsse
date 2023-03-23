<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\SQLite3\Driver;
use JKingWeb\Arsse\ImportExport\AbstractImportExport;
use JKingWeb\Arsse\Test\Database;

/** @covers \JKingWeb\Arsse\ImportExport\AbstractImportExport */
class TestImportExport extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $drv;
    protected $proc;
    protected $data;
    protected $primed;
    protected $checkTables = [
        'arsse_folders'       => ["id", "owner", "parent", "name"],
        'arsse_feeds'         => ["id", "url", "title"],
        'arsse_subscriptions' => ["id", "owner", "folder", "feed", "title"],
        'arsse_tags'          => ["id", "owner", "name"],
        'arsse_tag_members'   => ["tag", "subscription", "assigned"],
    ];

    public function setUp(): void {
        parent::setUp();
        // create a mock user manager
        Arsse::$user = $this->mock(\JKingWeb\Arsse\User::class)->get();
        // create a mock Import/Export processor
        $this->proc = $this->partialMock(AbstractImportExport::class);
        // initialize an SQLite memeory database
        static::setConf();
        try {
            $this->drv = Driver::create();
        } catch (\JKingWeb\Arsse\Db\Exception $e) {
            $this->markTestSkipped("An SQLite database is required for this test");
        }
        // create the database interface with the suitable driver and apply the latest schema
        Arsse::$db = new Database($this->drv);
        Arsse::$db->driverSchemaUpdate();
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                    'num'      => 'int',
                ],
                'rows' => [
                    ["john.doe@example.com", "", 1],
                    ["jane.doe@example.com", "", 2],
                ],
            ],
            'arsse_folders' => [
                'columns' => [
                    'id'     => "int",
                    'owner'  => "str",
                    'parent' => "int",
                    'name'   => "str",
                ],
                'rows' => [
                    [1, "john.doe@example.com", null, "Science"],
                    [2, "john.doe@example.com", 1,    "Rocketry"],
                    [3, "john.doe@example.com", null, "Politics"],
                    [4, "john.doe@example.com", null, "Photography"],
                    [5, "john.doe@example.com", 3,    "Local"],
                    [6, "john.doe@example.com", 3,    "National"],
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                ],
                'rows' => [
                    [1, "http://localhost:8000/Import/nasa-jpl",  "NASA JPL"],
                    [2, "http://localhost:8000/Import/torstar",   "Toronto Star"],
                    [3, "http://localhost:8000/Import/ars",       "Ars Technica"],
                    [4, "http://localhost:8000/Import/cbc",       "CBC News"],
                    [5, "http://localhost:8000/Import/citizen",   "Ottawa Citizen"],
                    [6, "http://localhost:8000/Import/eurogamer", "Eurogamer"],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'         => "int",
                    'owner'      => "str",
                    'folder'     => "int",
                    'feed'       => "int",
                    'title'      => "str",
                ],
                'rows' => [
                    [1, "john.doe@example.com", 2,    1, "NASA JPL"],
                    [2, "john.doe@example.com", 5,    2, "Toronto Star"],
                    [3, "john.doe@example.com", 1,    3, "Ars Technica"],
                    [4, "john.doe@example.com", 6,    4, "CBC News"],
                    [5, "john.doe@example.com", 6,    5, "Ottawa Citizen"],
                    [6, "john.doe@example.com", null, 6, "Eurogamer"],
                ],
            ],
            'arsse_tags' => [
                'columns' => [
                    'id'       => "int",
                    'owner'    => "str",
                    'name'     => "str",
                ],
                'rows' => [
                    [1, "john.doe@example.com", "canada"],
                    [2, "john.doe@example.com", "frequent"],
                    [3, "john.doe@example.com", "gaming"],
                    [4, "john.doe@example.com", "news"],
                    [5, "john.doe@example.com", "tech"],
                    [6, "john.doe@example.com", "toronto"],
                ],
            ],
            'arsse_tag_members' => [
                'columns' => [
                    'tag'          => "int",
                    'subscription' => "int",
                    'assigned'     => "bool",
                ],
                'rows' => [
                    [1, 2, 1],
                    [1, 4, 1],
                    [1, 5, 1],
                    [2, 3, 1],
                    [2, 6, 1],
                    [3, 6, 1],
                    [4, 2, 1],
                    [4, 4, 1],
                    [4, 5, 1],
                    [5, 1, 1],
                    [5, 3, 1],
                    [6, 2, 1],
                ],
            ],
        ];
        $this->primeDatabase($this->drv, $this->data);
    }

    public function tearDown(): void {
        $this->drv = null;
        $this->proc = null;
        parent::tearDown();
    }

    public function testImportForAMissingUser(): void {
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        $this->proc->get()->import("no.one@example.com", "", false, false);
    }

    public function testImportWithInvalidFolder(): void {
        $in = [[
        ], [1 =>
            ['id' => 1, 'name' => "", 'parent' => 0],
        ]];
        $this->proc->parse->returns($in);
        $this->assertException("invalidFolderName", "ImportExport");
        $this->proc->get()->import("john.doe@example.com", "", false, false);
    }

    public function testImportWithDuplicateFolder(): void {
        $in = [[
        ], [1 =>
            ['id' => 1, 'name' => "New", 'parent' => 0],
            ['id' => 2, 'name' => "New", 'parent' => 0],
        ]];
        $this->proc->parse->returns($in);
        $this->assertException("invalidFolderCopy", "ImportExport");
        $this->proc->get()->import("john.doe@example.com", "", false, false);
    }

    public function testMakeNoEffectiveChanges(): void {
        $in = [[
            ['url' => "http://localhost:8000/Import/nasa-jpl",  'title' => "NASA JPL",       'folder' => 3, 'tags' => ["tech"]],
            ['url' => "http://localhost:8000/Import/ars",       'title' => "Ars Technica",   'folder' => 2, 'tags' => ["frequent", "tech"]],
            ['url' => "http://localhost:8000/Import/torstar",   'title' => "Toronto Star",   'folder' => 5, 'tags' => ["news", "canada", "toronto"]],
            ['url' => "http://localhost:8000/Import/citizen",   'title' => "Ottawa Citizen", 'folder' => 6, 'tags' => ["news", "canada"]],
            ['url' => "http://localhost:8000/Import/eurogamer", 'title' => "Eurogamer",      'folder' => 0, 'tags' => ["gaming", "frequent"]],
            ['url' => "http://localhost:8000/Import/cbc",       'title' => "CBC News",       'folder' => 6, 'tags' => ["news", "canada"]],
        ], [1      =>
            ['id' => 1, 'name' => "Photography", 'parent' => 0],
            ['id' => 2, 'name' => "Science",     'parent' => 0],
            ['id' => 3, 'name' => "Rocketry",    'parent' => 2],
            ['id' => 4, 'name' => "Politics",    'parent' => 0],
            ['id' => 5, 'name' => "Local",       'parent' => 4],
            ['id' => 6, 'name' => "National",    'parent' => 4],
        ]];
        $this->proc->parse->returns($in);
        $exp = $this->primeExpectations($this->data, $this->checkTables);
        $this->proc->get()->import("john.doe@example.com", "", false, false);
        $this->compareExpectations($this->drv, $exp);
        $this->proc->get()->import("john.doe@example.com", "", false, true);
        $this->compareExpectations($this->drv, $exp);
    }

    public function testModifyASubscription(): void {
        $in = [[
            ['url' => "http://localhost:8000/Import/nasa-jpl",  'title' => "NASA JPL",       'folder' => 3, 'tags' => ["tech"]],
            ['url' => "http://localhost:8000/Import/ars",       'title' => "Ars Technica",   'folder' => 2, 'tags' => ["frequent", "tech"]],
            ['url' => "http://localhost:8000/Import/torstar",   'title' => "Toronto Star",   'folder' => 5, 'tags' => ["news", "canada", "toronto"]],
            ['url' => "http://localhost:8000/Import/citizen",   'title' => "Ottawa Citizen", 'folder' => 6, 'tags' => ["news", "canada"]],
            ['url' => "http://localhost:8000/Import/eurogamer", 'title' => "Eurogamer",      'folder' => 0, 'tags' => ["gaming", "frequent"]],
            ['url' => "http://localhost:8000/Import/cbc",       'title' => "CBC",            'folder' => 0, 'tags' => ["news", "canada"]], // moved to root and renamed
        ], [1      =>
            ['id' => 1, 'name' => "Photography", 'parent' => 0],
            ['id' => 2, 'name' => "Science",     'parent' => 0],
            ['id' => 3, 'name' => "Rocketry",    'parent' => 2],
            ['id' => 4, 'name' => "Politics",    'parent' => 0],
            ['id' => 5, 'name' => "Local",       'parent' => 4],
            ['id' => 6, 'name' => "National",    'parent' => 4],
            ['id' => 7, 'name' => "Nature",      'parent' => 0], // new folder
        ]];
        $this->proc->parse->returns($in);
        $this->proc->get()->import("john.doe@example.com", "", false, true);
        $exp = $this->primeExpectations($this->data, $this->checkTables);
        $exp['arsse_subscriptions']['rows'][3] = [4, "john.doe@example.com", null, 4, "CBC"];
        $exp['arsse_folders']['rows'][] = [7, "john.doe@example.com", null, "Nature"];
        $this->compareExpectations($this->drv, $exp);
    }

    public function testImportAFeed(): void {
        $in = [[
            ['url' => "http://localhost:8000/Import/some-feed", 'title' => "Some Feed", 'folder' => 0, 'tags' => ["frequent", "cryptic"]], //one existing tag and one new one
        ], []];
        $this->proc->parse->returns($in);
        $this->proc->get()->import("john.doe@example.com", "", false, false);
        $exp = $this->primeExpectations($this->data, $this->checkTables);
        $exp['arsse_feeds']['rows'][] = [7, "http://localhost:8000/Import/some-feed", "Some feed"]; // author-supplied and user-supplied titles differ
        $exp['arsse_subscriptions']['rows'][] = [7, "john.doe@example.com", null, 7, "Some Feed"];
        $exp['arsse_tags']['rows'][] = [7, "john.doe@example.com", "cryptic"];
        $exp['arsse_tag_members']['rows'][] = [2, 7, 1];
        $exp['arsse_tag_members']['rows'][] = [7, 7, 1];
        $this->compareExpectations($this->drv, $exp);
    }

    public function testImportAFeedWithAnInvalidTag(): void {
        $in = [[
            ['url' => "http://localhost:8000/Import/some-feed", 'title' => "Some Feed", 'folder' => 0, 'tags' => [""]],
        ], []];
        $this->proc->parse->returns($in);
        $this->assertException("invalidTagName", "ImportExport");
        $this->proc->get()->import("john.doe@example.com", "", false, false);
    }

    public function testReplaceData(): void {
        $in = [[
            ['url' => "http://localhost:8000/Import/some-feed", 'title' => "Some Feed", 'folder' => 1, 'tags' => ["frequent", "cryptic"]],
        ], [1 =>
            ['id' => 1, 'name' => "Photography", 'parent' => 0],
        ]];
        $this->proc->parse->returns($in);
        $this->proc->get()->import("john.doe@example.com", "", false, true);
        $exp = $this->primeExpectations($this->data, $this->checkTables);
        $exp['arsse_feeds']['rows'][] = [7, "http://localhost:8000/Import/some-feed", "Some feed"]; // author-supplied and user-supplied titles differ
        $exp['arsse_subscriptions']['rows'] = [[7, "john.doe@example.com", 4, 7, "Some Feed"]];
        $exp['arsse_tags']['rows'] = [[2, "john.doe@example.com", "frequent"], [7, "john.doe@example.com", "cryptic"]];
        $exp['arsse_tag_members']['rows'] = [[2, 7, 1], [7, 7, 1]];
        $exp['arsse_folders']['rows'] = [[4, "john.doe@example.com", null, "Photography"]];
        $this->compareExpectations($this->drv, $exp);
    }
}
