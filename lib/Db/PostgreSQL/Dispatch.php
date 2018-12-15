<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

trait Dispatch {
    protected function dispatchQuery(string $query, array $params = []) {
        pg_send_query_params($this->db, $query, $params);
        $result = pg_get_result($this->db);
        if (($code = pg_result_error_field($result, \PGSQL_DIAG_SQLSTATE)) && isset($code) && $code) {
            return $this->buildException($code, pg_result_error($result));
        } else {
            return $result;
        }
    }

    protected function buildException(string $code, string $msg): array {
        switch ($code) {
            case "22P02":
            case "42804":
                return [ExceptionInput::class, 'engineTypeViolation', $msg];
            case "23000":
            case "23502":
            case "23505":
                return [ExceptionInput::class, "engineConstraintViolation", $msg];
            case "55P03":
            case "57014":
                return [ExceptionTimeout::class, 'general', $msg];
            default:
                return [Exception::class, "engineErrorGeneral", $code.": ".$msg]; // @codeCoverageIgnore
        }
    }
}
