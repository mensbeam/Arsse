<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

interface Driver {
	static function create(\JKingWeb\NewsSync\Conf $conf, bool $install = false): Driver;
	static function driverName(): string;
	function schemaVersion(): int;
	function begin(): bool;
	function commit(): bool;
	function rollback(): bool;
	function exec(string $query): bool;
	function unsafeQuery(string $query): Result;
	function prepare(string $query, string ...$paramType): Statement;
}