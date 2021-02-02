<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;

class Driver extends \JKingWeb\Arsse\Db\AbstractDriver {
    use Dispatch;

    protected const TRANSACTIONAL_LOCKS = true;

    protected $db;
    protected $transStart = 0;

    public function __construct() {
        // check to make sure required extension is loaded
        if (!static::requirementsMet()) {
            throw new Exception("extMissing", static::driverName()); // @codeCoverageIgnore
        }
        $user = Arsse::$conf->dbPostgreSQLUser;
        $pass = Arsse::$conf->dbPostgreSQLPass;
        $db = Arsse::$conf->dbPostgreSQLDb;
        $host = Arsse::$conf->dbPostgreSQLHost;
        $port = Arsse::$conf->dbPostgreSQLPort;
        $schema = Arsse::$conf->dbPostgreSQLSchema;
        $service = Arsse::$conf->dbPostgreSQLService;
        $this->makeConnection($user, $pass, $db, $host, $port, $service);
        foreach (static::makeSetupQueries($schema) as $q) {
            $this->exec($q);
        }
    }

    public static function makeConnectionString(bool $pdo, string $user, string $pass, string $db, string $host, int $port, string $service): string {
        $base = [
            'client_encoding'  => "UTF8",
            'application_name' => "arsse",
            'connect_timeout'  => (string) (int) ceil(Arsse::$conf->dbTimeoutConnect),
        ];
        $out = [];
        if ($service != "") {
            $out['service'] = $service;
        } else {
            if ($host != "") {
                $out['host'] = $host;
            }
            if ($port != 5432 && !($host != "" && $host[0] == "/")) {
                $out['port'] = (string) $port;
            }
            if ($db != "") {
                $out['dbname'] = $db;
            }
            if (!$pdo) {
                $out['user'] = $user;
                if ($pass != "") {
                    $out['password'] = $pass;
                }
            }
        }
        ksort($out);
        ksort($base);
        $out = array_merge($out, $base);
        $out = array_map(function($v, $k) {
            return "$k='".str_replace("'", "\\'", str_replace("\\", "\\\\", $v))."'";
        }, $out, array_keys($out));
        return implode(" ", $out);
    }

    public static function makeSetupQueries(string $schema = ""): array {
        $timeExec = is_null(Arsse::$conf->dbTimeoutExec) ? 0 : ceil(max(Arsse::$conf->dbTimeoutExec * 1000, 1));
        $timeLock = is_null(Arsse::$conf->dbTimeoutLock) ? 0 : ceil(max(Arsse::$conf->dbTimeoutLock * 1000, 1));
        $out = [
            "SET TIME ZONE UTC",
            "SET DateStyle = 'ISO, MDY'",
            "SET statement_timeout = '$timeExec'",
            "SET lock_timeout = '$timeLock'",
        ];
        if (strlen($schema) > 0) {
            $schema = '"'.str_replace('"', '""', $schema).'"';
            $out[] = "SET search_path = $schema, public";
        }
        return $out;
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
        return "PostgreSQL";
    }

    public function charsetAcceptable(): bool {
        return $this->query("SELECT pg_encoding_to_char(encoding) from pg_database where datname = current_database()")->getValue() == "UTF8";
    }

    public function schemaVersion(): int {
        if ($this->query("SELECT count(*) from information_schema.tables where table_name = 'arsse_meta' and table_schema = current_schema()")->getValue()) {
            return (int) $this->query("SELECT value from arsse_meta where key = 'schema_version'")->getValue();
        } else {
            return 0;
        }
    }

    public function sqlToken(string $token): string {
        switch (strtolower($token)) {
            case "nocase":
                return '"und-x-icu"';
            case "like":
                return "ilike";
            case "asc":
                return "nulls first";
            case "desc":
                return "desc nulls last";
            default:
                return $token;
        }
    }

    public function savepointCreate(bool $lock = false): int {
        if (!$this->transStart) {
            $this->exec("BEGIN TRANSACTION");
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
        if ($this->query("SELECT count(*) from information_schema.tables where table_schema = current_schema() and table_name = 'arsse_meta'")->getValue()) {
            $this->exec("LOCK TABLE arsse_meta IN EXCLUSIVE MODE NOWAIT");
        }
        return true;
    }

    protected function unlock(bool $rollback = false): bool {
        // do nothing; transaction is committed or rolled back later
        return true;
    }

    public function __destruct() {
        if (isset($this->db)) {
            pg_close($this->db);
            unset($this->db);
        }
    }

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Db.PostgreSQL.Name");
    }

    public static function requirementsMet(): bool {
        return \extension_loaded("pgsql");
    }

    protected function makeConnection(string $user, string $pass, string $db, string $host, int $port, string $service): void {
        $dsn = $this->makeconnectionString(false, $user, $pass, $db, $host, $port, $service);
        set_error_handler(function(int $code, string $msg) {
            $msg = substr($msg, 62);
            throw new Exception("connectionFailure", ['engine' => "PostgreSQL", 'message' => $msg]);
        });
        try {
            $this->db = pg_connect($dsn, \PGSQL_CONNECT_FORCE_NEW);
        } finally {
            restore_error_handler();
        }
    }

    public function exec(string $query): bool {
        pg_send_query($this->db, $query);
        while ($result = pg_get_result($this->db)) {
            if (($code = pg_result_error_field($result, \PGSQL_DIAG_SQLSTATE)) && isset($code) && $code) {
                [$excClass, $excMsg, $excData] = $this->buildStandardException($code, pg_result_error($result));
                throw new $excClass($excMsg, $excData);
            }
        }
        return true;
    }

    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        $r = $this->dispatchQuery($query);
        if (is_resource($r)) {
            return new Result($this->db, $r);
        } else {
            [$excClass, $excMsg, $excData] = $r;
            throw new $excClass($excMsg, $excData);
        }
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        return new Statement($this->db, $query, $paramTypes);
    }

    public function literalString(string $str): string {
        return pg_escape_literal($this->db, $str);
    }

    public function maintenance(): bool {
        // analyze the database
        $this->exec("ANALYZE");
        return true;
    }
}
