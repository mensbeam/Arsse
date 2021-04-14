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
    public static function buildEngineException($code, string $msg): array {
        switch ($code) {
            case 1205:
                return [ExceptionTimeout::class, 'general', $msg];
            case 1364:
                return [ExceptionInput::class, "engineConstraintViolation", $msg];
            case 1366:
                return [ExceptionInput::class, 'engineTypeViolation', $msg];
            default:
                return [Exception::class, 'engineErrorGeneral', $msg];
        }
    }

    public static function buildConnectionException($code, string $msg): array {
        switch ($code) {
            case 1045:
            // @codeCoverageIgnoreStart
            case 1043:
            case 1044:
            case 1046:
            case 1049:
            case 2001:
            case 2002:
            case 2003:
            case 2004:
            case 2005:
            case 2007:
            case 2009:
            case 2010:
            case 2011:
            case 2012:
            case 2015:
            case 2016:
            case 2017:
            case 2018:
            case 2026:
            case 2028:
            // @codeCoverageIgnoreEnd
                return [Exception::class, 'connectionFailure', ['engine' => "MySQL", 'message' => $msg]];
            default:
                return [Exception::class, 'engineErrorGeneral', $msg]; // @codeCoverageIgnore
        }
    }
}
