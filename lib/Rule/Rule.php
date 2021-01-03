<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Rule;

abstract class Rule {
    public static function prep(string $pattern): string {
        if (preg_match_all("<`>", $pattern, $m, \PREG_OFFSET_CAPTURE)) {
            // where necessary escape our chosen delimiter (backtick) in reverse order
            foreach (array_reverse($m[0]) as [,$pos]) {
                // count the number of backslashes preceding the delimiter character
                $count = 0;
                $p = $pos;
                while ($p-- && $pattern[$p] === "\\" && ++$count);
                // if the number is even (including zero), add a backslash
                if ($count % 2 === 0) {
                    $pattern = substr($pattern, 0, $pos)."\\".substr($pattern, $pos);
                }
            }
        }
        // add the delimiters and test the pattern
        $pattern = "`$pattern`u";
        if (@preg_match($pattern, "") === false) {
            throw new Exception("invalidPattern");
        }
        return $pattern;
    }
}