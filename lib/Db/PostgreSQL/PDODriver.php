<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;

class PDODriver extends Driver {
    use \JKingWeb\Arsse\Db\PDODriver;

    protected $db;

    public static function requirementsMet(): bool {
        return class_exists("PDO") && in_array("pgsql", \PDO::getAvailableDrivers());
    }

    protected function makeConnection(string $user, string $pass, string $db, string $host, int $port, string $service): void {
        $dsn = $this->makeconnectionString(true, $user, $pass, $db, $host, $port, $service);
        try {
            $this->db = new \PDO("pgsql:$dsn", $user, $pass, [
                \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT => true,
            ]);
        } catch (\PDOException $e) {
            if ($e->getCode() == 7) {
                switch (substr($e->getMessage(), 9, 5)) {
                    case "08006":
                        throw new Exception("connectionFailure", ['engine' => "PostgreSQL", 'message' => substr($e->getMessage(), 28)]);
                    default:
                        throw $e; // @codeCoverageIgnore
                }
            }
            throw $e; // @codeCoverageIgnore
        }
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

    public function stringOutput(): bool {
        return false;
    }
}
