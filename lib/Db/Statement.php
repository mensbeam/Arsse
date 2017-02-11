<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

interface Statement {
	function __invoke(&...$values); // alias of run()
	function run(&...$values): Result;
	function runArray(array &$values): Result;
}