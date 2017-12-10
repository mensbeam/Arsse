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
    //normalization types
    const T_MIXED    = 0; // pass through unchanged
    const T_NULL     = 1; // convert to null
    const T_BOOL     = 2; // convert to boolean
    const T_INT      = 3; // convert to integer
    const T_FLOAT    = 4; // convert to floating point
    const T_DATE     = 5; // convert to DateTimeInterface instance
    const T_STRING   = 6; // convert to string
    const T_ARRAY    = 7; // convert to array
    //normalization modes
    const M_NULL     = 1 << 28; // pass nulls through regardless of target type
    const M_DROP     = 1 << 29; // drop the value (return null) if the type doesn't match
    const M_STRICT   = 1 << 30; // throw an exception if the type doesn't match
    const M_ARRAY    = 1 << 31; // the value should be a flat array of values of the specified type; indexed and associative are both acceptable

    public static function normalize($value, int $type, string $dateFormat = null) {
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
                $value[$key] = self::normalize($v, $type | ($allowNull ? self::M_NULL : 0) | ($strict ? self::M_STRICT : 0) | ($drop ? self::M_DROP : 0), $dateFormat);
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
                break;
            case self::T_FLOAT:
                if (is_float($value)) {
                    return $value;
                } elseif ($value instanceof \DateTimeInterface) {
                    if ($strict && !$drop) {
                        throw new ExceptionType("strictFailure", $type);
                    }
                    return (!$drop) ? (float) $value->getTimestamp(): null;
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
                if ($value instanceof \DateTimeImmutable) {
                    return $value->setTimezone(new \DateTimeZone("UTC"))->format(Date::FORMAT['iso8601'][1]);
                } elseif ($value instanceof \DateTime) {
                    $out = clone $value;
                    $out->setTimezone(new \DateTimeZone("UTC"));
                    return $out->format(Date::FORMAT['iso8601'][1]);
                } elseif (is_float($value) && is_finite($value)) {
                    $out = (string) $value;
                    if (!strpos($out, "E")) {
                        return $out;
                    } else {
                        $out = sprintf("%F", $value);
                        return substr($out, -2)==".0" ? (string) (int) $out : $out;
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
                break;
            case self::T_DATE:
                if ($value instanceof \DateTimeImmutable) {
                    return $value->setTimezone(new \DateTimeZone("UTC"));
                } elseif ($value instanceof \DateTime) {
                    $out = clone $value;
                    $out->setTimezone(new \DateTimeZone("UTC"));
                    return $out;
                } elseif (is_int($value)) {
                    return \DateTime::createFromFormat("U", (string) $value, new \DateTimeZone("UTC"));
                } elseif (is_float($value)) {
                    return \DateTime::createFromFormat("U.u", sprintf("%F", $value), new \DateTimeZone("UTC"));
                } elseif (is_string($value)) {
                    try {
                        if (!is_null($dateFormat)) {
                            $out = false;
                            if ($dateFormat=="microtime") {
                                // PHP is not able to correctly handle the output of microtime() as the input of DateTime::createFromFormat(), so we fudge it to look like a float
                                if (preg_match("<^0\.\d{6}00 \d+$>", $value)) {
                                    $value = substr($value, 11).".".substr($value, 2, 6);
                                } else {
                                    throw new \Exception;
                                }
                            }
                            $f = isset(Date::FORMAT[$dateFormat]) ? Date::FORMAT[$dateFormat][0] : $dateFormat;
                            if ($dateFormat=="iso8601" || $dateFormat=="iso8601m") {
                                // DateTime::createFromFormat() doesn't provide one catch-all for ISO 8601 timezone specifiers, so we try all of them till one works
                                if ($dateFormat=="iso8601m") {
                                    $f2 = Date::FORMAT["iso8601"][0];
                                    $zones = [$f."", $f."\Z", $f."P", $f."O", $f2."", $f2."\Z", $f2."P", $f2."O"];
                                } else {
                                    $zones = [$f."", $f."\Z", $f."P", $f."O"];
                                }
                                do {
                                    $ftz = array_shift($zones);
                                    $out = \DateTime::createFromFormat($ftz, $value, new \DateTimeZone("UTC"));
                                } while (!$out && $zones);
                            } else {
                                $out = \DateTime::createFromFormat($f, $value, new \DateTimeZone("UTC"));
                            }
                            if (!$out) {
                                throw new \Exception;
                            }
                            return $out;
                        } else {
                            return new \DateTime($value, new \DateTimeZone("UTC"));
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
                break;
            default:
                throw new ExceptionType("typeUnknown", $type); // @codeCoverageIgnore
        }
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
            return ($out==1 || $out==0) ? (bool) $out : $default;
        }
        return !is_null($out) ? $out : $default;
    }
}
