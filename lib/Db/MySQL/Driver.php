<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\MySQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

class Driver extends \JKingWeb\Arsse\Db\AbstractDriver {
    const SQL_MODE = "ANSI_QUOTES,HIGH_NOT_PRECEDENCE,NO_BACKSLASH_ESCAPES,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY,PIPES_AS_CONCAT,STRICT_ALL_TABLES";
    const TRANSACTIONAL_LOCKS = false;

    protected $db;
    protected $transStart = 0;

    public function __construct() {
        // check to make sure required extension is loaded
        if (!static::requirementsMet()) {
            throw new Exception("extMissing", static::driverName()); // @codeCoverageIgnore
        }
        $host = Arsse::$conf->dbMySQLHost;
        if ($host[0] == "/") {
            // host is a socket
            $socket = $host;
            $host = "";
        } elseif(substr($host, 0, 9) == "\\\\.\\pipe\\") {
            // host is a Windows named piple
            $socket = substr($host, 10);
            $host = "";
        }
        $user = Arsse::$conf->dbMySQLUser ?? "";
        $pass = Arsse::$conf->dbMySQLPass ?? "";
        $port = Arsse::$conf->dbMySQLPost ?? 3306;
        $db = Arsse::$conf->dbMySQLDb ?? "arsse";
        $this->makeConnection($user, $pass, $db, $host, $port, $socket ?? "");
        $this->exec("SET lock_wait_timeout = 1");
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

    public static function schemaID(): string {
        return "MySQL";
    }

    public function charsetAcceptable(): bool {
        return true;
    }

    public function schemaVersion(): int {
        if ($this->query("SELECT count(*) from information_schema.tables where table_name = 'arsse_meta'")->getValue()) {
            return (int) $this->query("SELECT value from arsse_meta where `key` = 'schema_version'")->getValue();
        } else {
            return 0;
        }
    }

    public function sqlToken(string $token): string {
        switch (strtolower($token)) {
            case "nocase":
                return '"utf8mb4_unicode_nopad_ci"';
            default:
                return $token;
        }
    }

    public function savepointCreate(bool $lock = false): int {
        if (!$this->transStart && !$lock) {
            $this->exec("BEGIN");
            $this->transStart = parent::savepointCreate($lock);
            return $this->transStart;
        } else {
            return parent::savepointCreate($lock);
        }
    }

    public function savepointRelease(int $index = null): bool {
        $index = $index ?? $this->transDepth;
        $out = parent::savepointRelease($index);
        if ($index == $this->transStart) {
            $this->exec("COMMIT");
            $this->transStart = 0;
        }
        return $out;
    }

    public function savepointUndo(int $index = null): bool {
        $index = $index ?? $this->transDepth;
        $out = parent::savepointUndo($index);
        if ($index == $this->transStart) {
            $this->exec("ROLLBACK");
            $this->transStart = 0;
        }
        return $out;
    }

    protected function lock(): bool {
        $tables = $this->query("SELECT table_name as name from information_schema.tables where table_schema = database() and table_name like 'arsse_%'")->getAll();
        if ($tables) {
            $tables = array_column($tables, "name");
            $tables = array_map(function($table) {
                $table = str_replace('"', '""', $table);
                return "\"$table\" write";
            }, $tables);
            $tables = implode(", ", $tables);
            try {
                $this->exec("SET lock_wait_timeout = 1; LOCK TABLES $tables");
            } finally {
                $this->exec("SET lock_wait_timeout = 0");
            }
        }
        return true;
    }

    protected function unlock(bool $rollback = false): bool {
        $this->exec("UNLOCK TABLES");
        return true;
    }

    public function __destruct() {
        if (isset($this->db)) {
            $this->db->close();
            unset($this->db);
        }
    }

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Db.MySQL.Name");
    }

    public static function requirementsMet(): bool {
        return false;
    }

    protected function makeConnection(string $db, string $user, string $password, string $host, int $port, string $socket) {
    }

    protected function getError(): string {
    }

    public function exec(string $query): bool {
    }

    public function query(string $query): \JKingWeb\Arsse\Db\Result {
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
    }
}
