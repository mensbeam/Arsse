<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

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
		"text"      => "text",
		"string"    => "text",
		"str"       => "text",
		"bool"      => "boolean",
		"boolean"   => "boolean",
		"bit"       => "boolean",
	];

    function run(...$values): Result;
    function runArray(array $values): Result;
    function rebind(...$bindings): bool;
    function rebindArray(array $bindings): bool;
}