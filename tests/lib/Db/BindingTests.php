<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Db;

use JKingWeb\Arsse\Db\Statement;

trait BindingTests {
    public function testBindNull() {
        $input = null;
        $exp = [
            "null"      => null,
            "integer"   => null,
            "float"     => null,
            "date"      => null,
            "time"      => null,
            "datetime"  => null,
            "binary"    => null,
            "string"    => null,
            "boolean"   => null,
        ];
        $this->checkBinding($input, $exp);
        // types may also be strict (e.g. "strict integer") and never pass null to the database; this is useful for NOT NULL columns
        // only null input should yield different results, so only this test has different expectations
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => gmdate("Y-m-d", 0),
            "time"      => gmdate("H:i:s", 0),
            "datetime"  => gmdate("Y-m-d H:i:s", 0),
            "binary"    => "",
            "string"    => "",
            "boolean"   => 0,
        ];
        $this->checkBinding($input, $exp, true);
    }

    public function testBindTrue() {
        $input = true;
        $exp = [
            "null"      => null,
            "integer"   => 1,
            "float"     => 1.0,
            "date"      => null,
            "time"      => null,
            "datetime"  => null,
            "binary"    => "1",
            "string"    => "1",
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindFalse() {
        $input = false;
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => null,
            "time"      => null,
            "datetime"  => null,
            "binary"    => "",
            "string"    => "",
            "boolean"   => 0,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindInteger() {
        $input = 2112;
        $exp = [
            "null"      => null,
            "integer"   => 2112,
            "float"     => 2112.0,
            "date"      => gmdate("Y-m-d", 2112),
            "time"      => gmdate("H:i:s", 2112),
            "datetime"  => gmdate("Y-m-d H:i:s", 2112),
            "binary"    => "2112",
            "string"    => "2112",
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindIntegerZero() {
        $input = 0;
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => gmdate("Y-m-d", 0),
            "time"      => gmdate("H:i:s", 0),
            "datetime"  => gmdate("Y-m-d H:i:s", 0),
            "binary"    => "0",
            "string"    => "0",
            "boolean"   => 0,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindFloat() {
        $input = 2112.0;
        $exp = [
            "null"      => null,
            "integer"   => 2112,
            "float"     => 2112.0,
            "date"      => gmdate("Y-m-d", 2112),
            "time"      => gmdate("H:i:s", 2112),
            "datetime"  => gmdate("Y-m-d H:i:s", 2112),
            "binary"    => "2112",
            "string"    => "2112",
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindFloatZero() {
        $input = 0.0;
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => gmdate("Y-m-d", 0),
            "time"      => gmdate("H:i:s", 0),
            "datetime"  => gmdate("Y-m-d H:i:s", 0),
            "binary"    => "0",
            "string"    => "0",
            "boolean"   => 0,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindAsciiString() {
        $input = "Random string";
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => null,
            "time"      => null,
            "datetime"  => null,
            "binary"    => $input,
            "string"    => $input,
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindUtf8String() {
        $input = "é";
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => null,
            "time"      => null,
            "datetime"  => null,
            "binary"    => $input,
            "string"    => $input,
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindBinaryString() {
        // FIXME: This test may be unreliable; SQLite happily stores invalid UTF-8 text as bytes untouched, but other engines probably don't do this
        $input = chr(233);
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => null,
            "time"      => null,
            "datetime"  => null,
            "binary"    => $input,
            "string"    => $input,
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindIso8601DateString() {
        $input = "2017-01-09T13:11:17";
        $time = strtotime($input." UTC");
        $exp = [
            "null"      => null,
            "integer"   => 2017,
            "float"     => 2017.0,
            "date"      => gmdate("Y-m-d", $time),
            "time"      => gmdate("H:i:s", $time),
            "datetime"  => gmdate("Y-m-d H:i:s", $time),
            "binary"    => $input,
            "string"    => $input,
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindArbitraryDateString() {
        $input = "Today";
        $time = strtotime($input." UTC");
        $exp = [
            "null"      => null,
            "integer"   => 0,
            "float"     => 0.0,
            "date"      => gmdate("Y-m-d", $time),
            "time"      => gmdate("H:i:s", $time),
            "datetime"  => gmdate("Y-m-d H:i:s", $time),
            "binary"    => $input,
            "string"    => $input,
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindMutableDateObject($class = '\DateTime') {
        $input = new $class("Noon Today");
        $time = $input->getTimestamp();
        $exp = [
            "null"      => null,
            "integer"   => $time,
            "float"     => (float) $time,
            "date"      => gmdate("Y-m-d", $time),
            "time"      => gmdate("H:i:s", $time),
            "datetime"  => gmdate("Y-m-d H:i:s", $time),
            "binary"    => gmdate("Y-m-d H:i:s", $time),
            "string"    => gmdate("Y-m-d H:i:s", $time),
            "boolean"   => 1,
        ];
        $this->checkBinding($input, $exp);
        $this->checkBinding($input, $exp, true);
    }

    public function testBindImmutableDateObject() {
        $this->testBindMutableDateObject('\DateTimeImmutable');
    }
}
