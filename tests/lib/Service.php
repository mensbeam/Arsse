<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test;

class Service extends \JKingWeb\Arsse\Service {
    public function __construct(\JKingWeb\Arsse\Service\Driver $drv) {
        $this->drv = $drv;
    }
}
