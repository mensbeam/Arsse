<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

use JKingWeb\Arsse\ExceptionType;

class ValueInfo {
    // universal
    const VALID = 1 << 0;
    const NULL  = 1 << 1;
    // integers
    const ZERO  = 1 << 2;
    const NEG   = 1 << 3;
    const FLOAT = 1 << 4;
    // strings
    const EMPTY = 1 << 2;
    const WHITE = 1 << 3;
    // normalization types
    const T_MIXED    = 0; // pass through unchanged
    const T_NULL     = 1; // convert to null
    const T_BOOL     = 2; // convert to boolean
    const T_INT      = 3; // convert to integer
    const T_FLOAT    = 4; // convert to floating point
    const T_DATE     = 5; // convert to DateTimeInterface instance
    const T_STRING   = 6; // convert to string
    const T_ARRAY    = 7; // convert to array
    const T_INTERVAL = 8; // convert to time interval
    // normalization modes
    const M_LOOSE    = 0;
    const M_NULL     = 1 << 28; // pass nulls through regardless of target type
    const M_DROP     = 1 << 29; // drop the value (return null) if the type doesn't match
    const M_STRICT   = 1 << 30; // throw an exception if the type doesn't match
    const M_ARRAY    = 1 << 31; // the value should be a flat array of values of the specified type; indexed and associative are both acceptable
    // symbolic date and time formats
    const DATE_FORMATS = [ // in                  out
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

    public static function normalize($value, int $type, string $dateInFormat = null, $dateOutFormat = null) {
        $allowNull = ($type & self::M_NULL);
        $strict    = ($type & (self::M_STRICT | self::M_DROP));
        $drop      = ($type & self::M_DROP);
        $arrayVal  = ($type & self::M_ARRAY);
        $type      = ($type & ~(self::M_NULL | self::M_DROP | self::M_STRICT | self::M_ARRAY));
        // if the value is null and this is allowed, simply return
        if ($allowNull && is_null($value)) {
            return null;
        }
        // if the value is supposed to be an array, handle it specially
        if ($arrayVal) {
            $value = self::normalize($value, self::T_ARRAY);
            foreach ($value as $key => $v) {
                $value[$key] = self::normalize($v, $type | ($allowNull ? self::M_NULL : 0) | ($strict ? self::M_STRICT : 0) | ($drop ? self::M_DROP : 0), $dateInFormat, $dateOutFormat);
            }
            return $value;
        }
        switch ($type) {
            case self::T_MIXED:
                return $value;
            case self::T_NULL:
                return null;
            case self::T_BOOL:
                if (is_bool($value)) {
                    return $value;
                }
                $out = self::bool($value);
                if ($strict && is_null($out)) {
                    // if strict and input is not a boolean, this is an error
                    if ($drop) {
                        return null;
                    }
                    throw new ExceptionType("strictFailure", $type);
                } elseif (is_float($value) && is_nan($value)) {
                    return false;
                } elseif (is_null($out)) {
                    // if not strict and input is not a boolean, return a simple type-cast
                    return (bool) $value;
                }
                return $out;
            case self::T_INT:
                if (is_int($value)) {
                    return $value;
                } elseif ($value instanceof \DateTimeInterface) {
                    if ($strict && !$drop) {
                        throw new ExceptionType("strictFailure", $type);
                    }
                    return (!$drop) ? (int) $value->getTimestamp(): null;
                } elseif ($value instanceof \DateInterval) {
                    if ($strict && !$drop) {
                        throw new ExceptionType("strictFailure", $type);
                    } elseif ($drop) {
                        return null;
                    } else {
                        // returns the number of seconds in the interval
                        // days are assumed to contain (60 * 60 * 24) seconds
                        // months are assumed to contain 30 days
                        // years are assumed to contain 365 days
                        $s = 0;
                        if ($value->days !== false) {
                            $s += ($value->days * 24 * 60 * 60);
                        } else {
                            $s += ($value->y * 365 * 24 * 60 * 60);
                            $s += ($value->m * 30 * 24 * 60 * 60);
                            $s += ($value->d * 24 * 60 * 60);
                        }
                        $s += ($value->h * 60 * 60);
                        $s += ($value->i * 60);
                        $s += $value->s;
                        return $s;
                    }
                }
                $info = self::int($value);
                if ($strict && !($info & self::VALID)) {
                    // if strict and input is not an integer, this is an error
                    if ($drop) {
                        return null;
                    }
                    throw new ExceptionType("strictFailure", $type);
                } elseif (is_bool($value)) {
                    return (int) $value;
                } elseif ($info & (self::VALID | self::FLOAT)) {
                    $out = strtolower((string) $value);
                    if (strpos($out, "e")) {
                        return (int) (float) $out;
                    } else {
                        return (int) $out;
                    }
                } else {
                    return 0;
                }
                break; // @codeCoverageIgnore
            case self::T_FLOAT:
                if (is_float($value)) {
                    return $value;
                } elseif ($value instanceof \DateTimeInterface) {
                    if ($strict && !$drop) {
                        throw new ExceptionType("strictFailure", $type);
                    }
                    return (!$drop) ? (float) $value->getTimestamp(): null;
                } elseif ($value instanceof \DateInterval) {
                    if ($drop) {
                        return null;
                    } elseif ($strict) {
                        throw new ExceptionType("strictFailure", $type);
                    }
                    // convert the interval to an integer, and then add microseconds if available (since PHP 7.1, for intervals created from a DateTime difference operation)
                    $out = (float) self::normalize($value, self::T_INT);
                    $out += isset($value->f) ? $value->f : 0.0;
                    return $out;
                } elseif (is_bool($value) && $strict) {
                    if ($drop) {
                        return null;
                    }
                    throw new ExceptionType("strictFailure", $type);
                }
                $out = filter_var($value, \FILTER_VALIDATE_FLOAT);
                if ($strict && $out===false) {
                    // if strict and input is not a float, this is an error
                    if ($drop) {
                        return null;
                    }
                    throw new ExceptionType("strictFailure", $type);
                }
                return (float) $out;
            case self::T_STRING:
                if (is_string($value)) {
                    return $value;
                }
                if ($value instanceof \DateTimeInterface) {
                    $dateOutFormat = $dateOutFormat ?? "iso8601";
                    $dateOutFormat = isset(self::DATE_FORMATS[$dateOutFormat]) ? self::DATE_FORMATS[$dateOutFormat][1] : $dateOutFormat;
                    if ($value instanceof \DateTimeImmutable) {
                        return $value->setTimezone(new \DateTimeZone("UTC"))->format($dateOutFormat);
                    } elseif ($value instanceof \DateTime) {
                        return \DateTimeImmutable::createFromMutable($value)->setTimezone(new \DateTimeZone("UTC"))->format($dateOutFormat);
                    }
                } elseif ($value instanceof \DateInterval) {
                    $dateSpec = "";
                    $timeSpec = "";
                    if ($value->days) {
                        $dateSpec = $value->days."D";
                    } else {
                        $dateSpec .= $value->y ? $value->y."Y": "";
                        $dateSpec .= $value->m ? $value->m."M": "";
                        $dateSpec .= $value->d ? $value->d."D": "";
                    }
                    $timeSpec .= $value->h ? $value->h."H": "";
                    $timeSpec .= $value->i ? $value->i."M": "";
                    $timeSpec .= $value->s ? $value->s."S": "";
                    $timeSpec = $timeSpec ? "T".$timeSpec : "";
                    if (!$dateSpec && !$timeSpec) {
                        return "PT0S";
                    } else {
                        return "P".$dateSpec.$timeSpec;
                    }
                } elseif (is_float($value) && is_finite($value)) {
                    $out = (string) $value;
                    if (!strpos($out, "E")) {
                        return $out;
                    } else {
                        $out = sprintf("%F", $value);
                        return preg_match("/\.0{1,}$/", $out) ? (string) (int) $out : $out;
                    }
                }
                $info = self::str($value);
                if (!($info & self::VALID)) {
                    if ($drop) {
                        return null;
                    } elseif ($strict) {
                        // if strict and input is not a string, this is an error
                        throw new ExceptionType("strictFailure", $type);
                    } elseif (!is_scalar($value)) {
                        return "";
                    } else {
                        return (string) $value;
                    }
                } else {
                    return (string) $value;
                }
                break; // @codeCoverageIgnore
            case self::T_DATE:
                if ($value instanceof \DateTimeImmutable) {
                    return $value->setTimezone(new \DateTimeZone("UTC"));
                } elseif ($value instanceof \DateTime) {
                    return \DateTimeImmutable::createFromMutable($value)->setTimezone(new \DateTimeZone("UTC"));
                } elseif (is_int($value)) {
                    return \DateTimeImmutable::createFromFormat("U", (string) $value, new \DateTimeZone("UTC"));
                } elseif (is_float($value) && is_finite($value)) {
                    return \DateTimeImmutable::createFromFormat("U.u", sprintf("%F", $value), new \DateTimeZone("UTC"));
                } elseif (is_string($value)) {
                    try {
                        if (!is_null($dateInFormat)) {
                            $out = false;
                            if ($dateInFormat === "microtime") {
                                // PHP is not able to correctly handle the output of microtime() as the input of DateTime::createFromFormat(), so we fudge it to look like a float
                                if (preg_match("<^0\.\d{6}00 \d+$>", $value)) {
                                    $value = substr($value, 11).".".substr($value, 2, 6);
                                } else {
                                    throw new \Exception;
                                }
                            }
                            $f = isset(self::DATE_FORMATS[$dateInFormat]) ? self::DATE_FORMATS[$dateInFormat][0] : $dateInFormat;
                            if ($dateInFormat === "iso8601" || $dateInFormat === "iso8601m") {
                                // DateTimeImmutable::createFromFormat() doesn't provide one catch-all for ISO 8601 timezone specifiers, so we try all of them till one works
                                if ($dateInFormat === "iso8601m") {
                                    $f2 = self::DATE_FORMATS["iso8601"][0];
                                    $zones = [$f."", $f."\Z", $f."P", $f."O", $f2."", $f2."\Z", $f2."P", $f2."O"];
                                } else {
                                    $zones = [$f."", $f."\Z", $f."P", $f."O"];
                                }
                                do {
                                    $ftz = array_shift($zones);
                                    $out = \DateTimeImmutable::createFromFormat($ftz, $value, new \DateTimeZone("UTC"));
                                } while (!$out && $zones);
                            } else {
                                $out = \DateTimeImmutable::createFromFormat($f, $value, new \DateTimeZone("UTC"));
                            }
                            if (!$out) {
                                throw new \Exception;
                            }
                            return $out;
                        } else {
                            return new \DateTimeImmutable($value, new \DateTimeZone("UTC"));
                        }
                    } catch (\Exception $e) {
                        if ($strict && !$drop) {
                            throw new ExceptionType("strictFailure", $type);
                        }
                        return null;
                    }
                } elseif ($strict && !$drop) {
                    throw new ExceptionType("strictFailure", $type);
                }
                return null;
            case self::T_ARRAY:
                if (is_array($value)) {
                    return $value;
                } elseif ($value instanceof \Traversable) {
                    $out = [];
                    foreach ($value as $k => $v) {
                        $out[$k] = $v;
                    }
                    return $out;
                } else {
                    if ($drop) {
                        return null;
                    } elseif ($strict) {
                        // if strict and input is not a string, this is an error
                        throw new ExceptionType("strictFailure", $type);
                    } elseif (is_null($value) || (is_float($value) && is_nan($value))) {
                        return [];
                    } else {
                        return [$value];
                    }
                }
                break; // @codeCoverageIgnore
            case self::T_INTERVAL:
                if ($value instanceof \DateInterval) {
                    if ($value->invert) {
                        $value = clone $value;
                        $value->invert = 0;
                    }
                    $value->f = $value->f ?? 0.0; // add microseconds for PHP 7.0
                    return $value;
                } elseif (is_null($value)) {
                    if ($strict && !$drop && !$allowNull) {
                        throw new ExceptionType("strictFailure", $type);
                    } else {
                        return null;
                    }
                } elseif (is_bool($value) || is_array($value) || (is_float($value) && (is_infinite($value) || is_nan($value))) || $value instanceof \DateTimeInterface || (is_object($value) && !method_exists($value, "__toString"))) {
                    if ($strict && !$drop) {
                        throw new ExceptionType("strictFailure", $type);
                    } else {
                        return null;
                    }
                } elseif (is_string($value) || is_object($value)) {
                    try {
                        $out = new \DateInterval((string) $value);
                        $out->f = 0.0;
                        return $out;
                    } catch (\Exception $e) {
                        if ($strict && !$drop) {
                            throw new ExceptionType("strictFailure", $type);
                        } elseif ($drop) {
                            return null;
                        } elseif (strtotime("now + $value") !== false) {
                            $out = \DateInterval::createFromDateString($value);
                            $out->f = 0.0;
                            return $out;
                        } else {
                            return null;
                        }
                    }
                } elseif ($drop) {
                    return null;
                } elseif ($strict) {
                    throw new ExceptionType("strictFailure", $type);
                } else {
                    // input is a number, assume this is a number of seconds
                    // for legibility we convert large numbers to minutes, hours, and days as necessary
                    // the DateInterval constructor only allows 12 digits for any given part of an interval,
                    // so we also convert days to 365-day years where we must, and cap the number of years
                    // at (1e11 - 1); this being a very large number, the loss of precision is probably not
                    // significant in practical usage
                    $sec = abs($value);
                    $msec = (float) ($sec - (int) $sec);
                    $sec = (int) $sec;
                    $min = 0;
                    $hour = 0;
                    $day = 0;
                    $year = 0;
                    if ($sec >= 60) {
                        $min = ($sec - ($sec % 60)) / 60;
                        $sec %= 60;
                    }
                    if ($min >= 60) {
                        $hour = ($min - ($min % 60)) / 60;
                        $min %= 60;
                    }
                    if ($hour >= 24) {
                        $day = ($hour - ($hour % 24)) / 24;
                        $hour %= 24;
                    }
                    if ($day >= 999999999999) {
                        $year = ($day - ($day % 365)) / 365;
                        $day %= 365;
                    }
                    $spec = "P";
                    $spec .= $year ? $year."Y" : "";
                    $spec .= $day ? $day."D" : "";
                    $spec .= "T";
                    $spec .= $hour ? $hour."H" : "";
                    $spec .= $min ? $min."M" : "";
                    $spec .= $sec ? $sec."S" : "";
                    $spec .= ($spec === "PT") ? "0S" : "";
                    $spec = trim($spec, "T");
                    $out = new \DateInterval($spec);
                    $out->f = $msec;
                    return $out;
                }
                break; // @codeCoverageIgnore
            default:
                throw new ExceptionType("typeUnknown", $type); // @codeCoverageIgnore
        }
    }

    public static function flatten(array $arr): array {
        $arr = array_values($arr);
        for ($a = 0; $a < sizeof($arr); $a++) {
            if (is_array($arr[$a])) {
                array_splice($arr, $a, 1, $arr[$a]);
                $a--;
            }
        }
        return $arr;
    }

    public static function int($value): int {
        $out = 0;
        if (is_null($value)) {
            // check if the input is null
            return self::NULL;
        } elseif (is_string($value) || (is_object($value) && method_exists($value, "__toString"))) {
            $value = strtolower((string) $value);
            // normalize a string an integer or float if possible
            if (!strlen($value)) {
                // the empty string is equivalent to null when evaluating an integer
                return self::NULL;
            }
            // interpret the value as a float
            $float = filter_var($value, \FILTER_VALIDATE_FLOAT);
            if ($float !== false) {
                if (!fmod($float, 1)) {
                    // an integral float is acceptable
                    $value = (int) (!strpos($value, "e") ? $value : $float);
                } else {
                    $out += self::FLOAT;
                    $value = $float;
                }
            } else {
                return $out;
            }
        } elseif (is_float($value)) {
            if (!fmod($value, 1)) {
                // an integral float is acceptable
                $value = (int) $value;
            } else {
                $out += self::FLOAT;
            }
        } elseif (!is_int($value)) {
            // if the value is not an integer or integral float, stop
            return $out;
        }
        // mark validity
        if (is_int($value)) {
            $out += self::VALID;
        }
        // mark zeroness
        if (!$value) {
            $out += self::ZERO;
        }
        // mark negativeness
        if ($value < 0) {
            $out += self::NEG;
        }
        return $out;
    }

    public static function str($value): int {
        $out = 0;
        // check if the input is null
        if (is_null($value)) {
            $out += self::NULL;
        }
        if (is_object($value) && method_exists($value, "__toString")) {
            // if the value is an object which has a __toString method, this is acceptable
            $value = (string) $value;
        } elseif (!is_scalar($value) || is_bool($value) || (is_float($value) && !is_finite($value))) {
            // otherwise if the value is not scalar, is a boolean, or is infinity or NaN, it cannot be valid
            return $out;
        }
        // mark validity
        $out += self::VALID;
        if (!strlen((string) $value)) {
            // mark emptiness
            $out += self::EMPTY;
        } elseif (!strlen(trim((string) $value))) {
            // mark whitespacedness
            $out += self::WHITE;
        }
        return $out;
    }

    public static function id($value, bool $allowNull = false): bool {
        $info = self::int($value);
        if ($allowNull && ($info & self::NULL)) { // null (and allowed)
            return true;
        } elseif (!($info & self::VALID)) { // not an integer
            return false;
        } elseif ($info & self::NEG) { // negative integer
            return false;
        } elseif (!$allowNull && ($info & self::ZERO)) { // zero (and not allowed)
            return false;
        } else { // non-negative integer
            return true;
        }
    }

    public static function bool($value, bool $default = null) {
        if (is_null($value) || ValueInfo::str($value) & ValueInfo::WHITE) {
            return $default;
        }
        $out = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
        if (is_null($out) && (ValueInfo::int($value) & ValueInfo::VALID)) {
            $out = (int) filter_var($value, \FILTER_VALIDATE_FLOAT);
            return ($out == 1 || $out == 0) ? (bool) $out : $default;
        }
        return !is_null($out) ? $out : $default;
    }
}
