<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

class CustomFunctions {
    protected static $tz;
    
    // Converts from SQL date format to a specified standard date format.
    public static function dateFormat(string $format, $date) {
        $format = strtolower($format);
        if($format=="sql") return $date;
        settype($date, "string");
        if($date=="") return null;
        if(is_null(self::$tz)) self::$tz = new \DateTimeZone("UTC");
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date, self::$tz);
        switch ($format) {
            case 'unix': 
                return $date->getTimestamp();
            case 'http': 
                return $date->format("D, d M Y H:i:s \G\M\T");
            case 'iso8601':
            default: 
                return $date->format(\DateTime::ATOM);
        }
    }
}