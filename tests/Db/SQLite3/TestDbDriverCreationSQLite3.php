<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Arsse;
use org\bovigo\vfs\vfsStream;
use Phake;

/** 
 * @covers \JKingWeb\Arsse\Db\SQLite3\Driver<extended>
 * @covers \JKingWeb\Arsse\Db\SQLite3\ExceptionBuilder */
class TestDbDriverCreationSQLite3 extends Test\AbstractTest {
    protected $data;
    protected $drv;
    protected $ch;

    public function setUp() {
        if (!extension_loaded("sqlite3")) {
            $this->markTestSkipped("SQLite extension not loaded");
        }
        $this->clearData();
        // test files
        $this->files = [
            // cannot create files
            'Cmain' => [],
            'Cshm' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
            ],
            'Cwal' => [
                'arsse.db' => "",
            ],
            // cannot write to files
            'Wmain' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Wwal' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Wshm' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            // cannot read from files
            'Rmain' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Rwal' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Rshm' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            // can neither read from or write to files
            'Amain' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Awal' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            'Ashm' => [
                'arsse.db' => "",
                'arsse.db-wal' => "",
                'arsse.db-shm' => "",
            ],
            // non-filesystem errors
            'corrupt' => [
                'arsse.db' => "",
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
        Arsse::$conf = new Conf();
        Arsse::$conf->dbSQLite3File = ":memory:";
        // set up database shim
    }

    public function tearDown() {
        $this->clearData();
    }

    public function testFailToCreateDatabase() {
        Arsse::$conf->dbSQLite3File = $this->path."Cmain/arsse.db";
        $this->assertException("fileUncreatable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToCreateJournal() {
        Arsse::$conf->dbSQLite3File = $this->path."Cwal/arsse.db";
        $this->assertException("fileUncreatable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToCreateSharedMmeory() {
        Arsse::$conf->dbSQLite3File = $this->path."Cshm/arsse.db";
        $this->assertException("fileUncreatable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToReadDatabase() {
        Arsse::$conf->dbSQLite3File = $this->path."Rmain/arsse.db";
        $this->assertException("fileUnreadable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToReadJournal() {
        Arsse::$conf->dbSQLite3File = $this->path."Rwal/arsse.db";
        $this->assertException("fileUnreadable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToReadSharedMmeory() {
        Arsse::$conf->dbSQLite3File = $this->path."Rshm/arsse.db";
        $this->assertException("fileUnreadable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToWriteToDatabase() {
        Arsse::$conf->dbSQLite3File = $this->path."Wmain/arsse.db";
        $this->assertException("fileUnwritable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToWriteToJournal() {
        Arsse::$conf->dbSQLite3File = $this->path."Wwal/arsse.db";
        $this->assertException("fileUnwritable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToWriteToSharedMmeory() {
        Arsse::$conf->dbSQLite3File = $this->path."Wshm/arsse.db";
        $this->assertException("fileUnwritable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToAccessDatabase() {
        Arsse::$conf->dbSQLite3File = $this->path."Amain/arsse.db";
        $this->assertException("fileUnusable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToAccessJournal() {
        Arsse::$conf->dbSQLite3File = $this->path."Awal/arsse.db";
        $this->assertException("fileUnusable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testFailToAccessSharedMmeory() {
        Arsse::$conf->dbSQLite3File = $this->path."Ashm/arsse.db";
        $this->assertException("fileUnusable", "Db");
        new Db\SQLite3\Driver;
    }

    public function testAssumeDatabaseCorruption() {
        Arsse::$conf->dbSQLite3File = $this->path."corrupt/arsse.db";
        $this->assertException("fileCorrupt", "Db");
        new Db\SQLite3\Driver;
    }
}
