<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

class PDODriver extends Driver {
    use \JKingWeb\Arsse\Db\PDODriver;

    protected $db;

    public static function requirementsMet(): bool {
        return class_exists("PDO") && in_array("pgsql", \PDO::getAvailableDrivers());
    }

    protected function makeConnection(string $user, string $pass, string $db, string $host, int $port, string $service) {
        $dsn = $this->makeconnectionString(true, $user, $pass, $db, $host, $port, $service);
        $this->db = new \PDO("pgsql:$dsn", $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public function __destruct() {
        unset($this->db);
    }

    /** @codeCoverageIgnore */
    public static function create(): \JKingWeb\Arsse\Db\Driver {
        if (self::requirementsMet()) {
            return new self;
        } elseif (Driver::requirementsMet()) {
            return new Driver;
        } else {
            throw new Exception("extMissing", self::driverName());
        }
    }


    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Db.PostgreSQLPDO.Name");
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        return new PDOStatement($this->db, $query, $paramTypes);
    }
}
