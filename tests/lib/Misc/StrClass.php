<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Misc;

class StrClass {
    public $str = "";

    public function __construct($str) {
        $this->str = (string) $str;
    }

    public function __toString() {
        return $this->str;
    }
}
