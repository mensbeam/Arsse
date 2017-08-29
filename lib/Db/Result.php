<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Result extends \Iterator {
    public function current();
    public function key();
    public function next();
    public function rewind();
    public function valid();

    public function getRow();
    public function getAll(): array;
    public function getValue();

    public function changes();
    public function lastId();
}
