<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class ValueInfo {
    // universal
    const VALID = 1 << 0;
    const NULL  = 1 << 1;
    // integers
    const ZERO  = 1 << 2;
    const NEG   = 1 << 3;
    // strings
    const EMPTY = 1 << 2;
    const WHITE = 1 << 3;

    static public function int($value): int {
        $out = 0;
        if (is_null($value)) {
            // check if the input is null
            return self::NULL;
        } elseif (is_string($value)) {
            // normalize a string an integer or float if possible
            if (!strlen((string) $value)) {
                // the empty string is equivalent to null when evaluating an integer
                return self::NULL;
            } elseif (filter_var($value, \FILTER_VALIDATE_FLOAT) !== false && !fmod((float) $value, 1)) {
                // an integral float is acceptable
                $value = (int) $value;
            } else {
                return $out;
            }
        } elseif (is_float($value) && !fmod($value, 1)) {
            // an integral float is acceptable
            $value = (int) $value;
        } elseif (!is_int($value)) {
            // if the value is not an integer or integral float, stop
            return $out;
        }
        // mark validity
        $out += self::VALID;
        // mark zeroness
        if($value==0) {
            $out += self::ZERO;
        }
        // mark negativeness
        if ($value < 0) {
            $out += self::NEG;
        }
        return $out;
    }

    static public function str($value): int {
        $out = 0;
        // check if the input is null
        if (is_null($value)) {
            $out += self::NULL;
        }
        // if the value is not scalar, is a boolean, or is infinity or NaN, it cannot be valid
        if (!is_scalar($value) || is_bool($value) || (is_float($value) && !is_finite($value))) {
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

    static public function id($value, bool $allowNull = false): bool {
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
}