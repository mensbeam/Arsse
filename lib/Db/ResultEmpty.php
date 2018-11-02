<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Db\Exception;

class ResultEmpty extends AbstractResult {
    public function changes(): int {
        return 0;
    }

    public function lastId(): int {
        return 0;
    }

    // PHP iterator methods

    public function valid() {
        return false;
    }
}
