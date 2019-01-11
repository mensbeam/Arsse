<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

trait SQLState {
    protected static function buildStandardException(string $code, string $msg): array {
        switch ($code) {
            case "22007":
            case "22P02":
            case "42804":
                return [ExceptionInput::class, 'engineTypeViolation', $msg];
            case "23000":
            case "23502":
            case "23505":
                return [ExceptionInput::class, "constraintViolation", $msg];
            case "55P03":
            case "57014":
                return [ExceptionTimeout::class, 'general', $msg];
            default:
                return [Exception::class, "engineErrorGeneral", "SQLSTATE $code: $msg"]; // @codeCoverageIgnore
        }
    }
}
