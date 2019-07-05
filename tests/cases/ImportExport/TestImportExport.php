<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\SQLite3\Driver;
use JKingWeb\Arsse\ImportExport\AbstractImportExport;
use JKingWeb\Arsse\ImportExport\Exception;
use JKingWeb\Arsse\Test\Database;

/** @covers \JKingWeb\Arsse\ImportExport\AbstractImportExport */
class TestImportExport extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $drv;
    protected $proc;
    protected $checkTables = [
        'arsse_folders'       => ["id", "owner", "parent", "name"],
        'arsse_feeds'         => ['id', 'url'],
        'arsse_subscriptions' => ["id", "owner", "folder", "feed", "title"],
        'arsse_tags'          => ["id", "owner", "name"],
        'arsse_tag_members'   => ["tag", "subscription", "assigned"],
    ];

    public function setUp() {
        self::clearData();
        // create a mock user manager
        Arsse::$user = \Phake::mock(\JKingWeb\Arsse\User::class);
        \Phake::when(Arsse::$user)->exists->thenReturn(true);
        \Phake::when(Arsse::$user)->authorize->thenReturn(true);
        // create a mock Import/Export processor
        $this->proc = \Phake::partialMock(AbstractImportExport::class);
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
                ],
                'rows' => [
                    ["john.doe@example.com", ""],
                    ["jane.doe@example.com", ""],
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
                    'tag' => "int",
                    'subscription' => "int",
                    'assigned' => "bool",
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

    public function tearDown() {
        $this->drv = null;
        $this->proc = null;
        self::clearData();
    }

    public function testMakeNoEffectiveChnages() {
        $in = [[
            ['url' => "http://localhost:8000/Import/nasa-jpl",  'title' => "NASA JPL",       'folder' => 3, 'tags' => ["tech"]],
            ['url' => "http://localhost:8000/Import/ars",       'title' => "Ars Technica",   'folder' => 2, 'tags' => ["frequent", "tech"]],
            ['url' => "http://localhost:8000/Import/torstar",   'title' => "Toronto Star",   'folder' => 5, 'tags' => ["news", "canada", "toronto"]],
            ['url' => "http://localhost:8000/Import/citizen",   'title' => "Ottawa Citizen", 'folder' => 6, 'tags' => ["news", "canada"]],
            ['url' => "http://localhost:8000/Import/eurogamer", 'title' => "Eurogamer",      'folder' => 0, 'tags' => ["gaming", "frequent"]],
            ['url' => "http://localhost:8000/Import/cbc",       'title' => "CBC News",       'folder' => 6, 'tags' => ["news", "canada"]],
        ], [1 =>
            ['id' => 1, 'name' => "Photography", 'parent' => 0],
            ['id' => 2, 'name' => "Science",     'parent' => 0],
            ['id' => 3, 'name' => "Rocketry",    'parent' => 2],
            ['id' => 4, 'name' => "Politics",    'parent' => 0],
            ['id' => 5, 'name' => "Local",       'parent' => 4],
            ['id' => 6, 'name' => "National",    'parent' => 4],
        ]];
        \Phake::when($this->proc)->parse->thenReturn($in);
        $exp = $this->primeExpectations($this->data, $this->checkTables);
        $this->proc->import("john.doe@example.com", "", false, false);
        $this->compareExpectations($this->drv, $exp);
        $this->proc->import("john.doe@example.com", "", false, true);
        $this->compareExpectations($this->drv, $exp);
    }
}
