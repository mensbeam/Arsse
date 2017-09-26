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
        // check if the input is null
        if (is_null($value)) {
            $out += self::NULL;
        }
        // normalize the value to an integer or float if possible
        if (is_string($value)) {
            if (strval(@intval($value))===$value) {
                $value = (int) $value;
            } elseif (strval(@floatval($value))===$value) {
                $value = (float) $value;
            }
            // the empty string is equivalent to null when evaluating an integer
            if (!strlen((string) $value)) {
                $out += self::NULL;
            }
        }
        // if the value is not an integer or integral float, stop
        if (!is_int($value) && (!is_float($value) || fmod($value, 1))) {
            return $out;
        }
        // mark validity
        $value = (int) $value;
        $out += self::VALID;
        // mark zeroness
        if(!$value) {
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
        // if the value is not scalar, it cannot be valid
        if (!is_scalar($value)) {
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
}