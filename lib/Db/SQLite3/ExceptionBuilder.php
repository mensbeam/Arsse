<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db\SQLite3;
use JKingWeb\NewsSync\Db\Exception;
use JKingWeb\NewsSync\Db\ExceptionInput;
use JKingWeb\NewsSync\Db\ExceptionTimeout;


trait ExceptionBuilder {

    public function exceptionBuild() {
        switch($this->db->lastErrorCode()) {
            case self::SQLITE_BUSY:
                return [ExceptionTimeout::class, 'sqliteBusy', $this->db->lastErrorMsg()];
            case self::SQLITE_CONSTRAINT:
                return [ExceptionInput::class, 'constraintViolation', $this->db->lastErrorMsg()];
            case self::SQLITE_MISMATCH:
                return [ExceptionInput::class, 'typeViolation', $this->db->lastErrorMsg()];
            default:
                return [Exception::class, 'engineErrorGeneral', $this->db->lastErrorMsg()];
        }
    }
}