<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

trait DateFormatter {
    
    protected function dateTransform($date, string $format = "iso8601", bool $local = false) {
        $date = $this->dateNormalize($date);
        $format = strtolower($format);
        if($format=="unix") return $date;
        switch ($format) {
            case 'http':    $f = "D, d M Y H:i:s \G\M\T"; break;
            case 'iso8601': $f = \DateTime::ATOM;         break;
            case 'sql':     $f = "Y-m-d H:i:s";           break;
            case 'date':    $f = "Y-m-d";                 break;
            case 'time':    $f = "H:i:s";                 break;
            default:        $f = \DateTime::ATOM;         break;
        }
        if($local) {
            return date($f, $date);
        } else {
            return gmdate($f, $date);
        }
    }

    protected function dateNormalize($date) {
        // convert input to a Unix timestamp
        if($date instanceof \DateTimeInterface) {
            $time = $date->getTimestamp();
        } else if(is_numeric($date)) {
            $time = (int) $date;
        } else if($date===null) {
            return null;
        } else if(is_string($date)) {
            try {
                $time = (new \DateTime($date, new \DateTimeZone("UTC")))->getTimestamp();
            } catch(\Throwable $e) {
                return null;
            }
        } else if (is_bool($date)) {
            return null;
        } else {
            $time = (int) $date;
        }
        return $time;
    }
}