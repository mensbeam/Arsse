<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Statement {
    const TS_TIME = -1;
    const TS_DATE = 0;
    const TS_BOTH = 1;
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

    static function dateFormat(int $part = self::TS_BOTH): string;

    function run(...$values): Result;
    function runArray(array $values = []): Result;
    function rebind(...$bindings): bool;
    function rebindArray(array $bindings): bool;
}