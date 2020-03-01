<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

class PDOResult extends AbstractResult {
    protected $set;
    protected $db;
    protected $cur = null;

    // actual public methods

    public function changes(): int {
        return $this->set->rowCount();
    }

    public function lastId(): int {
        try {
            return (int) $this->db->lastInsertId();
        } catch (\PDOException $e) {
            return 0;
        }
    }

    // constructor/destructor

    public function __construct(\PDO $db, \PDOStatement $result) {
        $this->set = $result;
        $this->db = $db;
    }

    public function __destruct() {
        try {
            $this->set->closeCursor();
        } catch (\PDOException $e) { // @codeCoverageIgnore
        }
        unset($this->set);
        unset($this->db);
    }

    // PHP iterator methods

    public function valid() {
        $this->cur = $this->set->fetch(\PDO::FETCH_ASSOC);
        return $this->cur !== false;
    }
}
