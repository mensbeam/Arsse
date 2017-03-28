<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;


trait ExceptionBuilder {

    public function exceptionBuild() {
        switch($this->db->lastErrorCode()) {
            case self::SQLITE_BUSY:
                return [ExceptionTimeout::class, 'general', $this->db->lastErrorMsg()];
            case self::SQLITE_CONSTRAINT:
                return [ExceptionInput::class, 'constraintViolation', $this->db->lastErrorMsg()];
            case self::SQLITE_MISMATCH:
                return [ExceptionInput::class, 'typeViolation', $this->db->lastErrorMsg()];
            default:
                return [Exception::class, 'engineErrorGeneral', $this->db->lastErrorMsg()];
        }
    }
}