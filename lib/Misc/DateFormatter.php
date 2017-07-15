<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

trait DateFormatter {
    
    protected function dateTransform($date, string $outFormat = null, string $inFormat = null, bool $inLocal = false) {
        $date = $this->dateNormalize($date, $inFormat, $inLocal);
        if(is_null($date) || is_null($outFormat)) return $date;
        $outFormat = strtolower($outFormat);
        if($outFormat=="unix") return $date->getTimestamp();
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

    protected function dateNormalize($date, string $inFormat = null, bool $inLocal = false) {
        if($date instanceof \DateTimeInterface) {
            return $date;
        } else if(is_numeric($date)) {
            $time = (int) $date;
        } else if($date===null) {
            return null;
        } else if(is_string($date)) {
            try {
                $tz = (!$inLocal) ? new \DateTimeZone("UTC") : null;
                if(!is_null($inFormat)) {
                    switch($inFormat) {
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
            } catch(\Throwable $e) {
                return null;
            }
        } else if (is_bool($date)) {
            return null;
        } else {
            $time = (int) $date;
        }
        $tz = (!$inLocal) ? new \DateTimeZone("UTC") : null;
        $d = new \DateTime("now", $tz);
        $d->setTimestamp($time);
        return $d;
    }
}