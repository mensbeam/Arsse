<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

class CustomFunctions {
    protected static $tz;
    
    // Converts from SQLite3's date format to a specified standard date format.
    public static function dateFormat(string $format, $date) {
        settype($date, "string");
        if($date=="") return null;
        if(is_null(self::$tz)) self::$tz = new \DateTimeZone("UTC");
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date, self::$tz);
        $format = strtolower($format);
        switch ($format) {
            case 'unix': 
                return $date->getTimestamp();
            case 'rfc822':
            case 'http': 
                return $date->format(\DateTime::RFC822);
            case 'iso8601':
            default: 
                return $date->format(\DateTime::ISO8601);
        }
    }
}