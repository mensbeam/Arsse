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

    public static function validate(string $pattern): bool {
        try {
            static::prep($pattern);
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /** applies keep and block rules against the title and categories of an article
     * 
     * Returns true if the article is to be kept, and false if it is to be suppressed
     */
    public static function apply(string $keepRule, string $blockRule, string $title, array $categories = []): bool {
        // if neither rule is processed we should keep
        $keep = true;
        // add the title to the front of the category array
        array_unshift($categories, $title);
        // process the keep rule if it exists
        if (strlen($keepRule)) {
            try {
                $rule = static::prep($keepRule);
            } catch (Exception $e) {
                return true;
            }
            // if a keep rule is specified the default state is now not to keep
            $keep = false;
            foreach ($categories as $str) {
                if (is_string($str)) {
                    if (preg_match($rule, $str)) {
                        // keep if the keep-rule matches one of the strings
                        $keep = true;
                        break;
                    }
                }
            }
        }
        // process the block rule if the keep rule was matched
        if ($keep && strlen($blockRule)) {
            try {
                $rule = static::prep($blockRule);
            } catch (Exception $e) {
                return true;
            }
            foreach ($categories as $str) {
                if (is_string($str)) {
                    if (preg_match($rule, $str)) {
                        // do not keep if the block-rule matches one of the strings
                        $keep = false;
                        break;
                    }
                }
            }
        }
        return $keep;
    }
}
