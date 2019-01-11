<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\MySQL;

use JKingWeb\Arsse\Db\Exception;

class Result extends \JKingWeb\Arsse\Db\AbstractResult {
    protected $st;
    protected $set;
    protected $cur = null;
    protected $rows = 0;
    protected $id = 0;

    // actual public methods

    public function changes(): int {
        return $this->rows;
    }

    public function lastId(): int {
        return $this->id;
    }

    // constructor/destructor

    public function __construct(\mysqli_result $result, array $changes = [0,0], Statement $statement = null) {
        $this->st = $statement; //keeps the statement from being destroyed, invalidating the result set
        $this->set = $result;
        $this->rows = $changes[0];
        $this->id = $changes[1];
    }

    public function __destruct() {
        try {
            $this->set->free();
        } catch (\Throwable $e) { // @codeCoverageIgnore
        }
        unset($this->set);
    }

    // PHP iterator methods

    public function valid() {
        $this->cur = $this->set->fetch_assoc();
        return ($this->cur !== null);
    }
}
