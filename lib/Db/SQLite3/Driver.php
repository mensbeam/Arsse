<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;


class Driver extends \JKingWeb\Arsse\Db\AbstractDriver {
    use ExceptionBuilder;

    const SQLITE_BUSY = 5;
    const SQLITE_CONSTRAINT = 19;
    const SQLITE_MISMATCH = 20;

    protected $db;

    public function __construct() {
        // check to make sure required extension is loaded
        if(!class_exists("SQLite3")) {
            throw new Exception("extMissing", self::driverName()); // @codeCoverageIgnore
        }
        $dbFile = Arsse::$conf->dbSQLite3File;
        $mode = \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE;
        try {
            $this->db = $this->makeConnection($dbFile, $mode, Arsse::$conf->dbSQLite3Key);
            // set initial options
            $this->db->enableExceptions(true);
            $this->exec("PRAGMA journal_mode = wal");
            $this->exec("PRAGMA foreign_keys = yes");
        } catch(\Throwable $e) {
            // if opening the database doesn't work, check various pre-conditions to find out what the problem might be
            $files = [
                $dbFile,        // main database file
                $dbFile."-wal", // write-ahead log journal
                $dbFile."-shm", // shared memory index
            ];
            foreach($files as $file) {
                if(!file_exists($file) && !is_writable(dirname($file))) {
                    throw new Exception("fileUncreatable", $file);
                } else if(!is_readable($file) && !is_writable($file)) {
                    throw new Exception("fileUnusable", $file);
                } else if(!is_readable($file)) {
                    throw new Exception("fileUnreadable", $file);
                } else if(!is_writable($file)) {
                    throw new Exception("fileUnwritable", $file);
                }
            }
            // otherwise the database is probably corrupt
            throw new Exception("fileCorrupt", $dbFile);
        }
    }

    protected function makeConnection(string $file, int $opts, string $key) {
        return new \SQLite3($file, $opts, $key);
    }

    public function __destruct() {
        try{$this->db->close();} catch(\Exception $e) {}
        unset($this->db);
    }


    static public function driverName(): string {
        return Arsse::$lang->msg("Driver.Db.SQLite3.Name");
    }

    public function schemaVersion(): int {
        return $this->query("PRAGMA user_version")->getValue();
    }

    public function schemaUpdate(int $to): bool {
        $ver = $this->schemaVersion();
        if(!Arsse::$conf->dbAutoUpdate)  {
            throw new Exception("updateManual", ['version' => $ver, 'driver_name' => $this->driverName()]);
        } else if($ver >= $to) {
            throw new Exception("updateTooNew", ['difference' => ($ver - $to), 'current' => $ver, 'target' => $to, 'driver_name' => $this->driverName()]);
        }
        $sep = \DIRECTORY_SEPARATOR;
        $path = Arsse::$conf->dbSchemaBase.$sep."SQLite3".$sep;
        // lock the database
        $this->savepointCreate(true);
        for($a = $this->schemaVersion(); $a < $to; $a++) {
            $this->savepointCreate();
            try {
                $file = $path.$a.".sql";
                if(!file_exists($file)) {
                    throw new Exception("updateFileMissing", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                } else if(!is_readable($file)) {
                    throw new Exception("updateFileUnreadable", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                }
                $sql = @file_get_contents($file);
                if($sql===false) {
                    throw new Exception("updateFileUnusable", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]); // @codeCoverageIgnore
                }
                try {
                    $this->exec($sql);
                } catch(\Throwable $e) {
                    throw new Exception("updateFileError", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a, 'message' => $this->getError()]);
                }
                if($this->schemaVersion() != $a+1) {
                    throw new Exception("updateFileIncomplete", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                }
            } catch(\Throwable $e) {
                // undo any partial changes from the failed update
                $this->savepointUndo();
                // commit any successful updates if updating by more than one version
                $this->savepointRelease();
                // throw the error received
                throw $e;
            }
            $this->savepointRelease();
        }
        $this->savepointRelease();
        return true;
    }

    protected function getError(): string {
        return $this->db->lastErrorMsg();
    }

    public function exec(string $query): bool {
        try {
            return (bool) $this->db->exec($query);
        } catch(\Exception $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
    }

    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        try {
            $r = $this->db->query($query);
        } catch(\Exception $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $this->db->changes();
        $lastId = $this->db->lastInsertRowID();
        return new Result($r, [$changes, $lastId]);
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        try {
            $s = $this->db->prepare($query);
        } catch(\Exception $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        return new Statement($this->db, $s, $paramTypes);
    }

    protected function lock(): bool {
        $this->exec("BEGIN EXCLUSIVE TRANSACTION");
        return true;
    }

    protected function unlock(bool $rollback = false): bool {
        $this->exec((!$rollback) ? "COMMIT" : "ROLLBACK");
        return true;
    }
}