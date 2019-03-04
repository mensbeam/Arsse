<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionRetry;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

trait ExceptionBuilder {
    protected function buildException(): array {
        return self::buildEngineException($this->db->lastErrorCode(), $this->db->lastErrorMsg());
    }

    public static function buildEngineException($code, string $msg): array {
        switch ($code) {
            case Driver::SQLITE_BUSY:
                return [ExceptionTimeout::class, 'general', $msg];
            case Driver::SQLITE_SCHEMA:
                return [ExceptionRetry::class, 'schemaChange', $msg];
            case Driver::SQLITE_CONSTRAINT:
                return [ExceptionInput::class, 'engineConstraintViolation', $msg];
            case Driver::SQLITE_MISMATCH:
                return [ExceptionInput::class, 'engineTypeViolation', $msg];
            default:
                return [Exception::class, 'engineErrorGeneral', $msg];
        }
    }
}
