<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\PostgreSQL;

class PDOStatement extends \JKingWeb\Arsse\Db\PDOStatement {    
    public static function mungeQuery(string $query, array $types, ...$extraData): string {
        return Statement::mungeQuery($query, $types, false);
    }
}
