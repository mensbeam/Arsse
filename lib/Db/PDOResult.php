<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Db\Exception;

class PDOResult extends AbstractResult {
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

    public function __construct(\PDOStatement $result, array $changes = [0,0]) {
        $this->set = $result;
        $this->rows = (int) $changes[0];
        $this->id = (int) $changes[1];
    }

    public function __destruct() {
        try {
            $this->set->closeCursor();
        } catch (\PDOException $e) { // @codeCoverageIgnore
        }
        unset($this->set);
    }

    // PHP iterator methods

    public function valid() {
        $this->cur = $this->set->fetch(\PDO::FETCH_ASSOC);
        return ($this->cur !== false);
    }
}
