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

    public function __construct(string $user = null, string $pass = null, string $db = null, string $host = null, int $port = null, string $schema = null) {
        // check to make sure required extension is loaded
        if (!static::requirementsMet()) {
            throw new Exception("extMissing", self::driverName()); // @codeCoverageIgnore
        }
        $user = $user ?? Arsse::$conf->dbPostgreSQLUser;
        $pass = $pass ?? Arsse::$conf->dbPostgreSQLPass;
        $db = $db ?? Arsse::$conf->dbPostgreSQLDb;
        $host = $host ?? Arsse::$conf->dbPostgreSQLHost;
        $port = $port ?? Arsse::$conf->dbPostgreSQLPort;
        $schema = $schema ?? Arsse::$conf->dbPostgreSQLSchema;
        $this->makeConnection($user, $pass, $db, $host, $port);
    }

    public static function requirementsMet(): bool {
        // stub: native interface is not yet supported
        return false;
    }

    protected function makeConnection(string $user, string $pass, string $db, string $host, int $port) {
        // stub: native interface is not yet supported
        throw new \Exception;
    }

    protected function makeConnectionString(bool $pdo, string $user, string $pass, string $db, string $host, int $port): string {
        $out = ['dbname' => $db];
        if ($host != "") {
            $out['host'] = $host;
            $out['port'] = (string) $port;
        }
        if (!$pdo) {
            $out['user'] = $user;
            $out['password'] = $pass;
        }
        $out = array_map(function($v, $k) {
            return "$k='".str_replace("'", "\\'", str_replace("\\", "\\\\", $v))."'";
        }, $out, array_keys($out));
        return implode(($pdo ? ";" : " "), $out);
    }

    public function __destruct() {
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
        return Arsse::$lang->msg("Driver.Db.PostgreSQL.Name");
    }

    public static function schemaID(): string {
        return "PostgreSQL";
    }

    public function schemaVersion(): int {
        // stub
        return 0;
    }

    public function schemaUpdate(int $to, string $basePath = null): bool {
        // stub
        return false;
    }

    public function charsetAcceptable(): bool {
        // stub
        return true;
    }

    protected function getError(): string {
        // stub
        return "";
    }

    public function exec(string $query): bool {
        // stub
        return true;
    }

    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        // stub
        return new ResultEmpty;
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        // stub
        return new Statement($this->db, $s, $paramTypes);
    }

    protected function lock(): bool {
        // stub
        return true;
    }

    protected function unlock(bool $rollback = false): bool {
        // stub
        return true;
    }
}
