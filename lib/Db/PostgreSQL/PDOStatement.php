<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db\PostgreSQL;

use JKingWeb\Arsse\Db\Result;

class PDOStatement extends \JKingWeb\Arsse\Db\PDOStatement {
    public static function mungeQuery(string $query, array $types, ...$extraData): string {
        return Statement::mungeQuery($query, $types, false);
    }

    /** @codeCoverageIgnore */
    public static function buildEngineException($code, string $msg): array {
        // PostgreSQL uses SQLSTATE exclusively, so this is not used
        return [];
    }

    public function runArray(array $values = []): Result {
        $this->st->closeCursor();
        $this->bindValues($values);
        try {
            $this->st->execute();
        } catch (\PDOException $e) {
            [$excClass, $excMsg, $excData] = $this->buildPDOException(true);
            throw new $excClass($excMsg, $excData);
        }
        return new PDOResult($this->db, $this->st);
    }
}
