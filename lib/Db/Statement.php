<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Statement {
    const TYPES = [
        "null"      => "null",
        "nil"       => "null",
        "int"       => "integer",
        "integer"   => "integer",
        "float"     => "float",
        "double"    => "float",
        "real"      => "float",
        "numeric"   => "float",
        "date"      => "date",
        "time"      => "time",
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
    public function rebind(...$bindings): bool;
    public function rebindArray(array $bindings): bool;
}
