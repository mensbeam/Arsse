<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

class PDOStatement extends \JKingWeb\Arsse\Db\PDOStatement {
    use ExceptionBuilder;
    use \JKingWeb\Arsse\Db\PDOError;

    /** @codeCoverageIgnore */
    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        // because PDO uses sqlite3_prepare() internally instead of sqlite3_prepare_v2(),
        // we have to retry ourselves in cases of schema changes
        // the SQLite3 class is not similarly affected
        $attempts = 0;
        retry:
        try {
            return parent::runArray($values);
        } catch (\JKingWeb\Arsse\Db\ExceptionRetry $e) {
            if (++$attempts > 50) {
                throw $e;
            } else {
                $this->st = $this->db->prepare($this->st->queryString);
                goto retry;
            }
        }
    }
}
