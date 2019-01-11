<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

trait PDOError {
    use SQLState;

    protected function buildPDOException(bool $statementError = false): array {
        if ($statementError) {
            $err = $this->st->errorInfo();
        } else {
            $err = $this->db->errorInfo();
        }
        if ($err[0] === "HY000") {
            return static::buildEngineException($err[1], $err[2]);
        } else {
            return static::buildStandardException($err[0], $err[2]);
        }
    }
}
