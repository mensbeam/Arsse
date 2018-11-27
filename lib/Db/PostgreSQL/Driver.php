<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

class Driver extends \JKingWeb\Arsse\Db\AbstractDriver {
    protected $transStart = 0;

    public function __construct(string $user = null, string $pass = null, string $db = null, string $host = null, int $port = null, string $schema = null, string $service = null) {
        // check to make sure required extension is loaded
        if (!static::requirementsMet()) {
            throw new Exception("extMissing", static::driverName()); // @codeCoverageIgnore
        }
        $user = $user ?? Arsse::$conf->dbPostgreSQLUser;
        $pass = $pass ?? Arsse::$conf->dbPostgreSQLPass;
        $db = $db ?? Arsse::$conf->dbPostgreSQLDb;
        $host = $host ?? Arsse::$conf->dbPostgreSQLHost;
        $port = $port ?? Arsse::$conf->dbPostgreSQLPort;
        $schema = $schema ?? Arsse::$conf->dbPostgreSQLSchema;
        $service = $service ?? Arsse::$conf->dbPostgreSQLService;
        $this->makeConnection($user, $pass, $db, $host, $port, $service);
        foreach (static::makeSetupQueries($schema) as $q) {
            $this->exec($q);
        }
    }

    public static function makeConnectionString(bool $pdo, string $user, string $pass, string $db, string $host, int $port, string $service): string {
        $base = [
            'client_encoding' => "UTF8",
            'application_name' => "arsse",
            'connect_timeout' => (string) ceil(Arsse::$conf->dbTimeoutConnect ?? 0),
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
        $timeout = ceil(Arsse::$conf->dbTimeoutExec * 1000);
        $out = [
            "SET TIME ZONE UTC",
            "SET DateStyle = 'ISO, MDY'",
            "SET statement_timeout = '$timeout'",
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
    }

    /** @codeCoverageIgnore */
    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Db.PostgreSQL.Name");
    }

    /** @codeCoverageIgnore */
    public static function requirementsMet(): bool {
        // stub: native interface is not yet supported
        return false;
    }

    /** @codeCoverageIgnore */
    protected function makeConnection(string $user, string $pass, string $db, string $host, int $port, string $service) {
        // stub: native interface is not yet supported
        throw new \Exception;
    }

    /** @codeCoverageIgnore */
    protected function getError(): string {
        // stub: native interface is not yet supported
        return "";
    }

    /** @codeCoverageIgnore */
    public function exec(string $query): bool {
        // stub: native interface is not yet supported
        return true;
    }

    /** @codeCoverageIgnore */
    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        // stub: native interface is not yet supported
        return new ResultEmpty;
    }

    /** @codeCoverageIgnore */
    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        // stub: native interface is not yet supported
        return new Statement($this->db, $s, $paramTypes);
    }
}
