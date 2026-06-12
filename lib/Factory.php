<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse;

class Factory {
    /** Instantiates an object of the specified class
     * 
     * This exists purely for overriding during testing. There we can return mock objects instead
     */
    public function get(string $class) {
        return new $class;
    }
}
