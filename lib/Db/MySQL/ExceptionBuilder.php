<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\MySQL;

use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

trait ExceptionBuilder {
    protected function buildException(): array {
        return self::buildEngineException($this->db->errno, $this->db->error);
    }

    public static function buildEngineException($code, string $msg): array {
        switch ($code) {
            case 1205:
                return [ExceptionTimeout::class, 'general', $msg];
            case 1364:
                return [ExceptionInput::class, "constraintViolation", $msg];
            case 1366:
                return [ExceptionInput::class, 'engineTypeViolation', $msg];
            default:
                return [Exception::class, 'engineErrorGeneral', $msg];
        }
    }
}
