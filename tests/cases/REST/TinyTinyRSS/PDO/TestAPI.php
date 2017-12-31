<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\REST\TinyTinyRSS\PDO;

/** @covers \JKingWeb\Arsse\REST\TinyTinyRSS\API<extended>
 *  @covers \JKingWeb\Arsse\REST\TinyTinyRSS\Exception */
class TestAPI extends \JKingWeb\Arsse\TestCase\REST\TinyTinyRSS\TestAPI {
    protected function v($value) {
        if (!is_array($value)) {
            return $value;
        }
        foreach($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->v($v);
            } elseif (is_int($v) || is_float($v)) {
                $value[$k] = (string) $v;
            }
        }
        return $value;
    }
}