<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Db\SQLite3\Driver as SQLite3;

trait PDOError {
    public function exceptionBuild(bool $statementError = null): array {
        if ($statementError ?? ($this instanceof Statement)) {
            $err = $this->st->errorInfo();
        } else {
            $err = $this->db->errorInfo();
        }
        switch ($err[0]) {
            case "22007":
            case "22P02":
            case "42804":
                return [ExceptionInput::class, 'engineTypeViolation', $err[2]];
            case "23000":
            case "23502":
            case "23505":
                return [ExceptionInput::class, "constraintViolation", $err[2]];
            case "55P03":
            case "57014":
                return [ExceptionTimeout::class, 'general', $err[2]];
            case "HY000":
                // engine-specific errors
                switch ($this->db->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
                    case "sqlite":
                        switch ($err[1]) {
                            case SQLite3::SQLITE_BUSY:
                                return [ExceptionTimeout::class, 'general', $err[2]];
                            case SQLite3::SQLITE_MISMATCH:
                                return [ExceptionInput::class, 'engineTypeViolation', $err[2]];
                        }
                        break;
                    case "mysql":
                        switch ($err[1]) {
                            case 1205:
                                return [ExceptionTimeout::class, 'general', $err[2]];
                            case 1364:
                                return [ExceptionInput::class, "constraintViolation", $err[2]];
                        }
                        break;
                }
                return [Exception::class, "engineErrorGeneral", $err[0]."/".$err[1].": ".$err[2]]; // @codeCoverageIgnore
            default:
                return [Exception::class, "engineErrorGeneral", $err[0].": ".$err[2]]; // @codeCoverageIgnore
        }
    }

    public function getError(): string {
        return (string) $this->db->errorInfo()[2];
    }
}
