<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Result extends \Iterator {
    #[\ReturnTypeWillChange]
    public function current();
    #[\ReturnTypeWillChange]
    public function key();
    #[\ReturnTypeWillChange]
    public function next();
    #[\ReturnTypeWillChange]
    public function rewind();
    #[\ReturnTypeWillChange]
    public function valid();

    public function getRow();
    public function getAll(): array;
    public function getValue();

    public function changes(): int;
    public function lastId(): int;
}
