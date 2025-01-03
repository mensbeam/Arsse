<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db\SQLite3;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;

class Driver extends \JKingWeb\Arsse\Db\AbstractDriver {
    use ExceptionBuilder;

    protected const TRANSACTIONAL_LOCKS = true;

    public const SQLITE_BUSY = 5;
    public const SQLITE_SCHEMA = 17;
    public const SQLITE_CONSTRAINT = 19;
    public const SQLITE_MISMATCH = 20;

    protected $db;
    protected $collator;

    public function __construct() {
        // check to make sure required extension is loaded
        if (!static::requirementsMet()) {
            throw new Exception("extMissing", static::driverName()); // @codeCoverageIgnore
        }
        // if no database file is specified in the configuration, use a suitable default
        $dbFile = Arsse::$conf->dbSQLite3File ?? \JKingWeb\Arsse\BASE."arsse.db";
        $dbKey = Arsse::$conf->dbSQLite3Key;
        $timeout = Arsse::$conf->dbSQLite3Timeout * 1000;
        try {
            // check whether the file exists; if it doesn't create the file and set its mode to rw-r-----
            if ($dbFile !== ":memory:" && !file_exists($dbFile)) {
                if (@touch($dbFile)) {
                    chmod($dbFile, 0640);
                }
            }
            $this->makeConnection($dbFile, $dbKey);
        } catch (\Throwable $e) {
            // if opening the database doesn't work, check various pre-conditions to find out what the problem might be
            $files = [
                $dbFile,        // main database file
                $dbFile."-wal", // write-ahead log journal
                $dbFile."-shm", // shared memory index
            ];
            foreach ($files as $file) {
                if (!file_exists($file) && !is_writable(dirname($file))) {
                    throw new Exception("fileUncreatable", $file);
                } elseif (!is_readable($file) && !is_writable($file)) {
                    throw new Exception("fileUnusable", $file);
                } elseif (!is_readable($file)) {
                    throw new Exception("fileUnreadable", $file);
                } elseif (!is_writable($file)) {
                    throw new Exception("fileUnwritable", $file);
                }
            }
            // otherwise the database is probably corrupt
            throw new Exception("fileCorrupt", $dbFile);
        }
        // set the timeout
        $timeout = Arsse::$conf->dbSQLite3Timeout ?? Arsse::$conf->dbTimeoutLock; // old SQLite-specific timeout takes precedence
        $timeout = is_null($timeout) ? PHP_INT_MAX : (int) ceil($timeout * 1000);
        $this->setTimeout($timeout);
        // set other initial options
        $this->exec("PRAGMA foreign_keys = yes");
        // use a case-insensitive Unicode collation sequence
        $this->collator = new \Collator("@kf=false");
        $m = ($this->db instanceof \PDO) ? "sqliteCreateCollation" : "createCollation";
        $this->db->$m("nocase", [$this->collator, "compare"]);
    }

    public static function requirementsMet(): bool {
        return class_exists("SQLite3");
    }

    protected function makeConnection(string $file, string $key): void {
        $this->db = new \SQLite3($file, \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE, $key);
        // enable exceptions
        $this->db->enableExceptions(true);
    }

    protected function setTimeout(int $msec): void {
        $this->exec("PRAGMA busy_timeout = $msec");
    }

    public function __destruct() {
        try {
            $this->db->close();
        } catch (\Exception $e) { // @codeCoverageIgnore
        }
        unset($this->db);
    }

    /** @codeCoverageIgnore */
    public static function create(): \JKingWeb\Arsse\Db\Driver {
        if (self::requirementsMet()) {
            return new self;
        } elseif (PDODriver::requirementsMet()) {
            return new PDODriver;
        } else {
            throw new Exception("extMissing", self::driverName());
        }
    }

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Db.SQLite3.Name");
    }

    public static function schemaID(): string {
        return "SQLite3";
    }

    public function schemaVersion(): int {
        return (int) $this->query("PRAGMA user_version")->getValue();
    }

    public function sqlToken(string $token): string {
        switch (strtolower($token)) {
            case "greatest":
                return "max";
            case "least":
                return "min";
            case "asc":
                return "";
            default:
                return $token;
        }
    }

    public function schemaUpdate(int $to, ?string $basePath = null): bool {
        if ($to == 1) {
            // if we're initializing the database for the first time, switch to WAL mode
            $this->exec("PRAGMA journal_mode = wal");
        }
        // turn off foreign keys
        $this->exec("PRAGMA foreign_keys = no");
        // run the generic updater
        try {
            parent::schemaUpdate($to, $basePath);
        } finally {
            // turn foreign keys back on
            $this->exec("PRAGMA foreign_keys = yes");
        }
        return true;
    }

    public function charsetAcceptable(): bool {
        // SQLite 3 databases are UTF-8 internally, thus always acceptable
        return true;
    }

    public function exec(string $query): bool {
        try {
            return (bool) $this->db->exec($query);
        } catch (\Exception $e) {
            [$excClass, $excMsg, $excData] = $this->buildException();
            throw new $excClass($excMsg, $excData);
        }
    }

    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        try {
            $r = $this->db->query($query);
        } catch (\Exception $e) {
            [$excClass, $excMsg, $excData] = $this->buildException();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $this->db->changes();
        $lastId = $this->db->lastInsertRowID();
        return new Result($r, [$changes, $lastId]);
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        return new Statement($this->db, $query, $paramTypes);
    }

    protected function lock(): bool {
        $timeout = (int) $this->query("PRAGMA busy_timeout")->getValue();
        $this->setTimeout(0);
        try {
            $this->exec("BEGIN EXCLUSIVE TRANSACTION");
        } finally {
            $this->setTimeout($timeout);
        }
        return true;
    }

    protected function unlock(bool $rollback = false): bool {
        $this->exec((!$rollback) ? "COMMIT" : "ROLLBACK");
        return true;
    }

    public function literalString(string $str): string {
        return "'".\SQLite3::escapeString($str)."'";
    }

    public function maintenance(): bool {
        // analyze the database then checkpoint and truncate the write-ahead log
        $this->exec("ANALYZE; PRAGMA wal_checkpoint(truncate)");
        return true;
    }
}
