<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\AbstractException;

class FeedException extends \JKingWeb\Arsse\Feed\Exception {
    public function __construct(string $msgID = "", $vars = null, \Throwable $e = null) {
        AbstractException::__construct($msgID, $vars, $e);
    }
}
