<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db\SQLite3;
use JKingWeb\NewsSync\Lang;
use JKingWeb\NewsSync\Db\Exception;
use JKingWeb\NewsSync\Db\ExceptionInput;
use JKingWeb\NewsSync\Db\ExceptionTimeout;


class Driver extends \JKingWeb\NewsSync\Db\AbstractDriver {    
    const SQLITE_ERROR = 1;
    const SQLITE_BUSY = 5;
    const SQLITE_CONSTRAINT = 19;
    const SQLITE_MISMATCH = 20;
    
    protected $db;
    protected $data;
    
    public function __construct(\JKingWeb\NewsSync\RuntimeData $data, bool $install = false) {
        // check to make sure required extension is loaded
        if(!class_exists("SQLite3")) throw new Exception("extMissing", self::driverName());
        $this->data = $data;
        $file = $data->conf->dbSQLite3File;
        // if the file exists (or we're initializing the database), try to open it and set initial options
        try {
            $this->db = new \SQLite3($file, ($install) ? \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE : \SQLITE3_OPEN_READWRITE, $data->conf->dbSQLite3Key);
            $this->db->enableExceptions(true);
            $this->exec("PRAGMA journal_mode = wal");
            $this->exec("PRAGMA foreign_keys = yes");
        } catch(\Throwable $e) {
            // if opening the database doesn't work, check various pre-conditions to find out what the problem might be
            if(!file_exists($file)) {
                if($install && !is_writable(dirname($file))) throw new Exception("fileUncreatable", dirname($file));
                throw new Exception("fileMissing", $file);
            }
            if(!is_readable($file) && !is_writable($file)) throw new Exception("fileUnusable", $file);
            if(!is_readable($file)) throw new Exception("fileUnreadable", $file);
            if(!is_writable($file)) throw new Exception("fileUnwritable", $file);
            // otherwise the database is probably corrupt
            throw new Exception("fileCorrupt", $mainfile);
        }
    }

    public function __destruct() {
        try{$this->db->close();} catch(\Exception $e) {}
        unset($this->db);
    }

    
    static public function driverName(): string {
        return Lang::msg("Driver.Db.SQLite3.Name");
    }

    public function schemaVersion(): int {
        return $this->query("PRAGMA user_version")->getValue();
    }

    public function schemaUpdate(int $to): bool {
        $ver = $this->schemaVersion();
        if(!$this->data->conf->dbSQLite3AutoUpd)  throw new Exception("updateManual", ['version' => $ver, 'driver_name' => $this->driverName()]);
        if($ver >= $to) throw new Exception("updateTooNew", ['difference' => ($ver - $to), 'current' => $ver, 'target' => $to, 'driver_name' => $this->driverName()]);
        $sep = \DIRECTORY_SEPARATOR;
        $path = \JKingWeb\NewsSync\BASE."sql".$sep."SQLite3".$sep;
        $this->lock();
        $this->begin();
        for($a = $ver; $a < $to; $a++) {
            $this->begin();
            try {
                $file = $path.$a.".sql";
                if(!file_exists($file)) throw new Exception("updateFileMissing", ['file' => $file, 'driver_name' => $this->driverName()]);
                if(!is_readable($file)) throw new Exception("updateFileUnreadable", ['file' => $file, 'driver_name' => $this->driverName()]);
                $sql = @file_get_contents($file);
                if($sql===false) throw new Exception("updateFileUnusable", ['file' => $file, 'driver_name' => $this->driverName()]);
                $this->exec($sql);
            } catch(\Throwable $e) {
                // undo any partial changes from the failed update
                $this->rollback();
                // commit any successful updates if updating by more than one version
                $this->commit(true);
                // throw the error received
                // FIXME: This should create the relevant type of SQL exception
                throw $e;
            }
            $this->commit();
        }
        $this->unlock();
        $this->commit();
        return true;
    }

    public function exec(string $query): bool {
        return (bool) $this->db->exec($query);
    }

    public function query(string $query): \JKingWeb\NewsSync\Db\Result {
        return new Result($this->db->query($query), $this->db->changes());
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\NewsSync\Db\Statement {
        return new Statement($this->db, $this->db->prepare($query), $paramTypes);
    }
}