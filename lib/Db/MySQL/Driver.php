<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\MySQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;

class Driver extends \JKingWeb\Arsse\Db\AbstractDriver {
    use ExceptionBuilder;

    protected const SQL_MODE = "ANSI_QUOTES,HIGH_NOT_PRECEDENCE,NO_BACKSLASH_ESCAPES,NO_ENGINE_SUBSTITUTION,PIPES_AS_CONCAT,STRICT_ALL_TABLES";
    protected const TRANSACTIONAL_LOCKS = false;

    /** @var \mysqli */
    protected $db;
    protected $transStart = 0;
    protected $packetSize = 4194304;

    public function __construct() {
        // check to make sure required extension is loaded
        if (!static::requirementsMet()) {
            throw new Exception("extMissing", static::driverName()); // @codeCoverageIgnore
        }
        $host = strtolower(!strlen(Arsse::$conf->dbMySQLHost) ? "localhost" : Arsse::$conf->dbMySQLHost);
        $socket = strlen(Arsse::$conf->dbMySQLSocket) ? Arsse::$conf->dbMySQLSocket : ini_get("mysqli.default_socket");
        $user = Arsse::$conf->dbMySQLUser;
        $pass = Arsse::$conf->dbMySQLPass;
        $port = Arsse::$conf->dbMySQLPort;
        $db = Arsse::$conf->dbMySQLDb;
        // make the connection
        $this->makeConnection($db, $user, $pass, $host, $port, $socket);
        // set session variables
        foreach (static::makeSetupQueries() as $q) {
            $this->exec($q);
        }
        // get the maximum packet size; parameter strings larger than this size need to be chunked
        $this->packetSize = (int) $this->query("SELECT variable_value from performance_schema.session_variables where variable_name = 'max_allowed_packet'")->getValue();
    }

    public static function makeSetupQueries(): array {
        return [
            "SET sql_mode = '".self::SQL_MODE."'",
            "SET time_zone = '+00:00'",
            "SET lock_wait_timeout = ".self::lockTimeout(),
            "SET max_execution_time = ".ceil(Arsse::$conf->dbTimeoutExec * 1000),
        ];
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
                return '"utf8mb4_unicode_ci"';
            case "integer":
                return "signed integer";
            case "asc":
                return "";
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
                $this->exec("SET lock_wait_timeout = ".self::lockTimeout());
            }
        }
        return true;
    }

    protected function unlock(bool $rollback = false): bool {
        $this->exec("UNLOCK TABLES");
        return true;
    }

    protected static function lockTimeout(): int {
        return (int) max(min(ceil(Arsse::$conf->dbTimeoutLock ?? 31536000), 31536000), 1);
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
        return class_exists("mysqli");
    }

    protected function makeConnection(string $db, string $user, string $password, string $host, int $port, string $socket): void {
        $this->db = mysqli_init();
        $this->db->options(\MYSQLI_OPT_CONNECT_TIMEOUT, ceil(Arsse::$conf->dbTimeoutConnect));
        @$this->db->real_connect($host, $user, $password, $db, $port, $socket);
        if ($this->db->connect_errno) {
            [$excClass, $excMsg, $excData] = $this->buildConnectionException($this->db->connect_errno, $this->db->connect_error);
            throw new $excClass($excMsg, $excData);
        }
        $this->db->set_charset("utf8mb4");
    }

    public function exec(string $query): bool {
        $this->dispatch($query, true);
        return true;
    }

    protected function dispatch(string $query, bool $multi = false) {
        if ($multi) {
            $this->db->multi_query($query);
        } else {
            $this->db->real_query($query);
        }
        $e = null;
        do {
            if ($this->db->sqlstate !== "00000") {
                if ($this->db->sqlstate === "HY000") {
                    [$excClass, $excMsg, $excData] = $this->buildEngineException($this->db->errno, $this->db->error);
                } else {
                    [$excClass, $excMsg, $excData] = $this->buildStandardException($this->db->sqlstate, $this->db->error);
                }
                $e = new $excClass($excMsg, $excData, $e);
            }
            $r = $this->db->store_result();
        } while ($this->db->more_results() && $this->db->next_result());
        if ($e) {
            throw $e;
        } else {
            return $r;
        }
    }

    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        $r = $this->dispatch($query);
        $rows = (int) $this->db->affected_rows;
        $id = (int) $this->db->insert_id;
        return new Result($r, [$rows, $id]);
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        return new Statement($this->db, $query, $paramTypes, $this->packetSize);
    }

    public function literalString(string $str): string {
        return "'".$this->db->real_escape_string($str)."'";
    }

    public function maintenance(): bool {
        // with MySQL each table must be analyzed separately, so we first have to get a list of tables
        foreach ($this->query("SHOW TABLES like 'arsse\\_%'") as $table) {
            $table = array_pop($table);
            if (!preg_match("/^arsse_[a-z_]+$/", $table)) {
                // table is not one of ours
                continue; // @codeCoverageIgnore
            }
            $this->query("ANALYZE TABLE $table");
        }
        return true;
    }
}
