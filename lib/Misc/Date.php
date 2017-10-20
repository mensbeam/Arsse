<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class Date {
    const FORMAT = [ // in                        out
        'iso8601'   => ["!Y-m-d\TH:i:s",          "Y-m-d\TH:i:s\Z"       ], // NOTE: ISO 8601 dates require special input processing because of varying formats for timezone offsets
        'iso8601m'  => ["!Y-m-d\TH:i:s.u",        "Y-m-d\TH:i:s.u\Z"     ], // NOTE: ISO 8601 dates require special input processing because of varying formats for timezone offsets
        'microtime' => ["U.u",                    "0.u00 U"              ], // NOTE: the actual input format at the user level matches the output format; pre-processing is required for PHP not to fail
        'http'      => ["!D, d M Y H:i:s \G\M\T", "D, d M Y H:i:s \G\M\T"],
        'sql'       => ["!Y-m-d H:i:s",           "Y-m-d H:i:s"          ],
        'date'      => ["!Y-m-d",                 "Y-m-d"                ],
        'time'      => ["!H:i:s",                 "H:i:s"                ],
        'unix'      => ["U",                      "U"                    ],
        'float'     => ["U.u",                    "U.u"                  ],
    ];
    
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
            case 'iso8601': $f = "Y-m-d\TH:i:s";          break;
            case 'sql':     $f = "Y-m-d H:i:s";           break;
            case 'date':    $f = "Y-m-d";                 break;
            case 'time':    $f = "H:i:s";                 break;
            default:        $f = $outFormat;              break;
        }
        return $date->format($f);
    }

    public static function normalize($date, string $inFormat = null) {
        return ValueInfo::normalize($date, ValueInfo::T_DATE, $inFormat);
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
