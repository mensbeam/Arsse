<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

class ResultEmpty extends AbstractResult {
    protected $changes = 0;
    protected $id = 0;
    
    public function __construct(int $changes = 0, int $id = 0) {
        $this->changes = $changes;
        $this->id = $id;
    }

    public function changes(): int {
        return $this->changes;
    }

    public function lastId(): int {
        return $this->id;
    }

    // PHP iterator methods

    public function valid() {
        return false;
    }
}
