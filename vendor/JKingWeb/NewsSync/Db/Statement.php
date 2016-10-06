<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

interface Statement {
	function __invoke(...$bindings); // alias of run()
	function run(...$bindings): Result;
	function runArray(array $bindings): Result;
}