<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

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

    public function changes(): int;
    public function lastId(): int;
}
