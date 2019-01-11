<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class Date {
    public static function transform($date, string $outFormat = null, string $inFormat = null) {
        $date = ValueInfo::normalize($date, ValueInfo::T_DATE, $inFormat);
        if (!$date) {
            return null;
        }
        $out = ValueInfo::normalize($date, ValueInfo::T_STRING, null, $outFormat);
        if ($outFormat === "unix") {
            $out = (int) $out;
        } elseif ($outFormat === "float") {
            $out = (float) $out;
        }
        return $out;
    }

    public static function normalize($date, string $inFormat = null) {
        return ValueInfo::normalize($date, ValueInfo::T_DATE, $inFormat);
    }

    public static function add(string $interval, $date = "now") {
        return self::modify("add", $interval, $date);
    }

    public static function sub(string $interval, $date = "now") {
        return self::modify("sub", $interval, $date);
    }

    protected static function modify(string $func, string $interval, $date) {
        $date = self::normalize($date);
        return $date ? $date->$func(new \DateInterval($interval)) : null;
    }
}
