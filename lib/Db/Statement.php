<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Statement {
    const TYPES = [
        "int"       => "integer",
        "integer"   => "integer",
        "float"     => "float",
        "double"    => "float",
        "real"      => "float",
        "numeric"   => "float",
        "datetime"  => "datetime",
        "timestamp" => "datetime",
        "blob"      => "binary",
        "bin"       => "binary",
        "binary"    => "binary",
        "text"      => "string",
        "string"    => "string",
        "str"       => "string",
        "bool"      => "boolean",
        "boolean"   => "boolean",
        "bit"       => "boolean",
    ];

    public function run(...$values): Result;
    public function runArray(array $values = []): Result;
    public function retype(...$bindings): bool;
    public function retypeArray(array $bindings): bool;
}
