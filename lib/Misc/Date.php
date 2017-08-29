<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class Date {
    public static function transform($date, string $outFormat = null, string $inFormat = null, bool $inLocal = false) {
        $date = self::normalize($date, $inFormat, $inLocal);
        if (is_null($date) || is_null($outFormat)) {
            return $date;
        }
        $outFormat = strtolower($outFormat);
        if ($outFormat=="unix") {
            return $date->getTimestamp();
        }
        switch ($outFormat) {
            case 'http':    $f = "D, d M Y H:i:s \G\M\T"; break;
            case 'iso8601': $f = "Y-m-d\TH:i:s";           break;
            case 'sql':     $f = "Y-m-d H:i:s";           break;
            case 'date':    $f = "Y-m-d";                 break;
            case 'time':    $f = "H:i:s";                 break;
            default:        $f = $outFormat;              break;
        }
        return $date->format($f);
    }

    public static function normalize($date, string $inFormat = null, bool $inLocal = false) {
        if ($date instanceof \DateTimeInterface) {
            return $date;
        } elseif (is_numeric($date)) {
            $time = (int) $date;
        } elseif ($date===null) {
            return null;
        } elseif (is_string($date)) {
            try {
                $tz = (!$inLocal) ? new \DateTimeZone("UTC") : null;
                if (!is_null($inFormat)) {
                    switch ($inFormat) {
                        case 'http':    $f = "D, d M Y H:i:s \G\M\T"; break;
                        case 'iso8601': $f = "Y-m-d\TH:i:sP";          break;
                        case 'sql':     $f = "Y-m-d H:i:s";           break;
                        case 'date':    $f = "Y-m-d";                 break;
                        case 'time':    $f = "H:i:s";                 break;
                        default:        $f = $inFormat;               break;
                    }
                    return \DateTime::createFromFormat("!".$f, $date, $tz);
                } else {
                    return new \DateTime($date, $tz);
                }
            } catch (\Throwable $e) {
                return null;
            }
        } elseif (is_bool($date)) {
            return null;
        } else {
            $time = (int) $date;
        }
        $tz = (!$inLocal) ? new \DateTimeZone("UTC") : null;
        $d = new \DateTime("now", $tz);
        $d->setTimestamp($time);
        return $d;
    }

    public static function add(string $interval, $date = null): \DateTimeInterface {
        return self::modify("add", $interval, $date);
    }

    public static function sub(string $interval, $date = null): \DateTimeInterface {
        return self::modify("sub", $interval, $date);
    }

    protected static function modify(string $func, string $interval, $date = null): \DateTimeInterface {
        $date = self::normalize($date ?? time());
        if ($date instanceof \DateTimeImmutable) {
            return $date->$func(new \DateInterval($interval));
        } else {
            $date->$func(new \DateInterval($interval));
            return $date;
        }
    }
}
