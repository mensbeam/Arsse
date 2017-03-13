<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db\SQLite3;

class CustomFunctions {
    // Converts from SQLite3's date format to a specified standard date format.
    public static function dateFormat(string $format, string $date): string {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date, 'UTC');

        $format = strtolower($format);
        switch ($format) {
            case 'unix': return (string)$date->getTimestamp();
            break;
            case 'rfc822':
            case 'http': return $date->format(\DateTime::RFC822);
            break;
            case 'iso8601':
            default: return $date->format(\DateTime::ISO8601);
        }
    }
}