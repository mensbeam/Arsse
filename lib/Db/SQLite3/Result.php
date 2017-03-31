<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

class Result implements \JKingWeb\Arsse\Db\Result {
    protected $st;
    protected $set;
    protected $pos = 0;
    protected $cur = null;
    protected $rows = 0;
    protected $id = 0;

    // actual public methods

    public function getValue() {
        $this->next();
        if($this->valid()) {
            $keys = array_keys($this->cur);
            return $this->cur[array_shift($keys)];
        }
        return null;
    }

    public function getRow() {
        $this->next();
        return ($this->valid() ? $this->cur : null);
    }

    public function getAll(): array {
        $out = [];
        foreach($this as $row) {
            $out [] = $row;
        }
        return $out;
    }

    public function changes() {
        return $this->rows;
    }

    public function lastId() {
        return $this->id;
    }

    // constructor/destructor

    public function __construct(\SQLite3Result $result, array $changes = [0,0], Statement $statement = null) {
        $this->st = $statement; //keeps the statement from being destroyed, invalidating the result set
        $this->set = $result;
        $this->rows = $changes[0];
        $this->id = $changes[1];
    }

    public function __destruct() {
        $this->set->finalize();
        unset($this->set);
    }

    // PHP iterator methods

    public function valid() {
        $this->cur = $this->set->fetchArray(\SQLITE3_ASSOC);
        return ($this->cur !== false);
    }

    public function next() {
        $this->cur = null;
        $this->pos += 1;
    }

    public function current() {
        return $this->cur;
    }

    public function key() {
        return $this->pos;
    }

    public function rewind() {
        $this->pos = 0;
        $this->cur = null;
        $this->set->reset();
    }
}