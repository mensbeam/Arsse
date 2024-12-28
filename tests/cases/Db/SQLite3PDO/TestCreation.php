<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\SQLite3PDO;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\SQLite3\PDODriver as Driver;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\JKingWeb\Arsse\Db\SQLite3\PDODriver::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PDODriver::class)]
#[CoversClass(\JKingWeb\Arsse\Db\PDOError::class)]
#[CoversClass(\JKingWeb\Arsse\Db\SQLState::class)]
class TestCreation extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $data;
    protected $drv;
    protected $ch;
    protected $files;
    protected $path;

    public function setUp(): void {
        if (!Driver::requirementsMet()) {
            $this->markTestSkipped("PDO-SQLite extension not loaded");
        }
        parent::setUp();
        // test files
        $this->files = [
            // cannot create files
            'Cmain' => [],
            'Cshm'  => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
            ],
            'Cwal' => [
                'arsse.db' => "",
            ],
            // cannot write to files
            'Wmain' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Wwal' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Wshm' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            // cannot read from files
            'Rmain' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Rwal' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Rshm' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            // can neither read from or write to files
            'Amain' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Awal' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Ashm' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            // non-filesystem errors
            'corrupt' => [
                'arsse.db'     => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
        ];
        $vfs = vfsStream::setup("dbtest", 0777, $this->files);
        $this->path = $path = $vfs->url()."/";
        // set up access blocks
        chmod($path."Cmain", 0555);
        chmod($path."Cwal", 0555);
        chmod($path."Cshm", 0555);
        chmod($path."Rmain/arsse.db", 0333);
        chmod($path."Rwal/arsse.db-wal", 0333);
        chmod($path."Rshm/arsse.db-shm", 0333);
        chmod($path."Wmain/arsse.db", 0555);
        chmod($path."Wwal/arsse.db-wal", 0555);
        chmod($path."Wshm/arsse.db-shm", 0555);
        chmod($path."Amain/arsse.db", 0111);
        chmod($path."Awal/arsse.db-wal", 0111);
        chmod($path."Ashm/arsse.db-shm", 0111);
        // set up configuration
        self::setConf();
    }

    public function testFailToCreateDatabase(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Cmain/arsse.db";
        $this->assertException("fileUncreatable", "Db");
        new Driver;
    }

    public function testFailToCreateJournal(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Cwal/arsse.db";
        $this->assertException("fileUncreatable", "Db");
        new Driver;
    }

    public function testFailToCreateSharedMmeory(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Cshm/arsse.db";
        $this->assertException("fileUncreatable", "Db");
        new Driver;
    }

    public function testFailToReadDatabase(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Rmain/arsse.db";
        $this->assertException("fileUnreadable", "Db");
        new Driver;
    }

    public function testFailToReadJournal(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Rwal/arsse.db";
        $this->assertException("fileUnreadable", "Db");
        new Driver;
    }

    public function testFailToReadSharedMmeory(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Rshm/arsse.db";
        $this->assertException("fileUnreadable", "Db");
        new Driver;
    }

    public function testFailToWriteToDatabase(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Wmain/arsse.db";
        $this->assertException("fileUnwritable", "Db");
        new Driver;
    }

    public function testFailToWriteToJournal(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Wwal/arsse.db";
        $this->assertException("fileUnwritable", "Db");
        new Driver;
    }

    public function testFailToWriteToSharedMmeory(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Wshm/arsse.db";
        $this->assertException("fileUnwritable", "Db");
        new Driver;
    }

    public function testFailToAccessDatabase(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Amain/arsse.db";
        $this->assertException("fileUnusable", "Db");
        new Driver;
    }

    public function testFailToAccessJournal(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Awal/arsse.db";
        $this->assertException("fileUnusable", "Db");
        new Driver;
    }

    public function testFailToAccessSharedMmeory(): void {
        Arsse::$conf->dbSQLite3File = $this->path."Ashm/arsse.db";
        $this->assertException("fileUnusable", "Db");
        new Driver;
    }

    public function testAssumeDatabaseCorruption(): void {
        Arsse::$conf->dbSQLite3File = $this->path."corrupt/arsse.db";
        $this->assertException("fileCorrupt", "Db");
        new Driver;
    }
}
