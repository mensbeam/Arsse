<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\ImportExport;

use JKingWeb\Arsse\Db\SQLite3\Driver;
use JKingWeb\Arsse\ImportExport\AbstractImportExport;
use JKingWeb\Arsse\ImportExport\Exception;
use JKingWeb\Arsse\Test\Database;

/** @covers \JKingWeb\Arsse\ImportExport\AbstractImportExport */
class TestImportExport extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $drv;
    protected $proc;

    public function setUp() {
        self::clearData();
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
                ],
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                    'title'      => "str",
                ],
                'rows' => [
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'         => "int",
                    'owner'      => "str",
                    'feed'       => "int",
                    'title'      => "str",
                ],
                'rows' => [
                ],
            ],
            'arsse_tags' => [
                'columns' => [
                    'id'       => "int",
                    'owner'    => "str",
                    'name'     => "str",
                ],
                'rows' => [
                ],
            ],
            'arsse_tag_members' => [
                'columns' => [
                    'tag' => "int",
                    'subscription' => "int",
                    'assigned' => "bool",
                ],
                'rows' => [
                ],
            ],
        ];
    }

    public function tearDown() {
        $this->drv = null;
        $this->proc = null;
        self::clearData();
    }
}
