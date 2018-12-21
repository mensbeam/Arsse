<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\MySQL;

class PDOStatement extends \JKingWeb\Arsse\Db\PDOStatement {    
    public static function mungeQuery(string $query, array $types, ...$extraData): string {
        $query = explode("?", $query);
        $out = "";
        for ($b = 1; $b < sizeof($query); $b++) {
            $a = $b - 1;
            $mark = (($types[$a] ?? "") == "datetime") ? "cast(? as datetime(0))" : "?";
            $out .= $query[$a].$mark;
        }
        $out .= array_pop($query);
        return $out;
    }
}
