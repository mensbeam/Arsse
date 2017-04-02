<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

class Result implements \JKingWeb\Arsse\Db\Result {
    protected $st;
    protected $set;
    protected $pos = 0;
    protected $cur = null;
    protected $rows = 0;
    protected $id = 0;

    // actual public methods

    public function getValue() {
        $arr = $this->next();
        if($this->valid()) {
            $keys = array_keys($arr);
            return $arr[array_shift($keys)];
        }
        return null;
    }

    public function getRow() {
        $arr = $this->next();
        return ($this->valid() ? $arr : null);
    }

    public function getAll(): array {
        return $this->set;
    }

    public function changes() {
        return $this->rows;
    }

    public function lastId() {
        return $this->id;
    }

    // constructor/destructor

    public function __construct(array $result, int $changes = 0, int $lastID = 0) {
        $this->set = $result;
        $this->rows = $changes;
        $this->id = $lastID;
    }

    public function __destruct() {
    }

    // PHP iterator methods

    public function valid() {
        return !is_null(key($this->set));
    }

    public function next() {
        return next($this->set);
    }

    public function current() {
        return current($this->set);
    }

    public function key() {
        return key($this->set);
    }

    public function rewind() {
        reset($this->set);
    }
}