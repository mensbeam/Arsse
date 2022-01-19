<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Exception;

class PDODriver extends AbstractPDODriver {
    protected $db;

    public static function requirementsMet(): bool {
        return class_exists("PDO") && in_array("sqlite", \PDO::getAvailableDrivers());
    }

    protected function makeConnection(string $file, string $key): void {
        $this->db = new \PDO("sqlite:".$file, "", "", [
            \PDO::ATTR_ERRMODE           => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_STRINGIFY_FETCHES => true,
        ]);
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
        return Arsse::$lang->msg("Driver.Db.SQLite3PDO.Name");
    }

    public function prepareArray(string $query, array $paramTypes): \JKingWeb\Arsse\Db\Statement {
        return new PDOStatement($this->db, $query, $paramTypes);
    }

    /** @codeCoverageIgnore */
    public function exec(string $query): bool {
        // because PDO uses sqlite3_prepare() internally instead of sqlite3_prepare_v2(),
        // we have to retry ourselves in cases of schema changes
        // the SQLite3 class is not similarly affected
        $attempts = 0;
        retry:
        try {
            return parent::exec($query);
        } catch (\JKingWeb\Arsse\Db\ExceptionRetry $e) {
            if (++$attempts > 50) {
                throw $e;
            } else {
                goto retry;
            }
        }
    }

    /** @codeCoverageIgnore */
    public function query(string $query): \JKingWeb\Arsse\Db\Result {
        // because PDO uses sqlite3_prepare() internally instead of sqlite3_prepare_v2(),
        // we have to retry ourselves in cases of schema changes
        // the SQLite3 class is not similarly affected
        $attempts = 0;
        retry:
        try {
            return parent::query($query);
        } catch (\JKingWeb\Arsse\Db\ExceptionRetry $e) {
            if (++$attempts > 50) {
                throw $e;
            } else {
                goto retry;
            }
        }
    }
}
