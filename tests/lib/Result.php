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
        if ($this->valid()) {
            $keys = array_keys($this->current());
            $out = $this->current()[array_shift($keys)];
            $this->next();
            return $out;
        }
        $this->next();
        return null;
    }

    public function getRow() {
        $out = ($this->valid() ? $this->current() : null);
        $this->next();
        return $out;
    }

    public function getAll(): array {
        return iterator_to_array($this, false);
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
        return $this->pos < sizeof($this->set);
    }

    public function next() {
        $this->pos++;
    }

    public function current() {
        return $this->set[$this->key()];
    }

    public function key() {
        return array_keys($this->set)[$this->pos];
    }

    public function rewind() {
        $this->pos = 0;
    }
}
