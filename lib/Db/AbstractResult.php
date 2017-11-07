<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

abstract class AbstractResult implements Result {
    protected $pos = 0;
    protected $cur = null;

    // actual public methods

    public function getValue() {
        if ($this->valid()) {
            $out = array_shift($this->cur);
            $this->next();
            return $out;
        }
        $this->next();
        return null;
    }

    public function getRow() {
        $out = ($this->valid() ? $this->cur : null);
        $this->next();
        return $out;
    }

    public function getAll(): array {
        return iterator_to_array($this, false);
    }

    abstract public function changes();

    abstract public function lastId();

    // PHP iterator methods

    abstract public function valid();

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
        if ($this->pos) {
            throw new Exception("resultReused");
        }
    }
}
