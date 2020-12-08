<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

abstract class Date {
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

    public static function normalize($date, string $inFormat = null): ?\DateTimeImmutable {
        return ValueInfo::normalize($date, ValueInfo::T_DATE, $inFormat);
    }

    public static function add($interval, $date = "now"): ?\DateTimeImmutable {
        return self::modify("add", $interval, $date);
    }

    public static function sub($interval, $date = "now"): ?\DateTimeImmutable {
        return self::modify("sub", $interval, $date);
    }

    protected static function modify(string $func, $interval, $date): ?\DateTimeImmutable {
        $date = self::normalize($date);
        $interval = (!$interval instanceof \DateInterval) ? ValueInfo::normalize($interval, ValueInfo::T_INTERVAL) : $interval;
        return $date ? $date->$func($interval) : null;
    }
}
