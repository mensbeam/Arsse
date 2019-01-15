<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

trait Dispatch {
    protected function dispatchQuery(string $query, array $params = []) {
        pg_send_query_params($this->db, $query, $params);
        $result = pg_get_result($this->db);
        if (($code = pg_result_error_field($result, \PGSQL_DIAG_SQLSTATE)) && isset($code) && $code) {
            return $this->buildStandardException($code, pg_result_error($result));
        } else {
            return $result;
        }
    }

    /** @codeCoverageIgnore */
    public static function buildEngineException($code, string $msg): array {
        // PostgreSQL uses SQLSTATE exclusively, so this is not used
        return [];
    }
}
