<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Driver {
    function __construct(bool $install = false);
    // returns a human-friendly name for the driver (for display in installer, for example)
    static function driverName(): string;
    // returns the version of the scheme of the opened database; if uninitialized should return 0
    function schemaVersion(): int;
    // begin a real or synthetic transactions, with real or synthetic nesting
    function begin(): bool;
    // commit either the latest or all pending nested transactions; use of this method should assume a partial commit is a no-op
    function commit(bool $all = false): bool;
    // rollback either the latest or all pending nested transactions; use of this method should assume a partial rollback will not work
    function rollback(bool $all = false): bool;
    // attempt to advise other processes that they should not attempt to access the database; used during live upgrades
    function lock(): bool;
    function unlock(): bool;
    function isLocked(): bool;
    // attempt to perform an in-place upgrade of the database schema; this may be a no-op which always throws an exception
    function schemaUpdate(int $to): bool;
    // execute one or more unsanitized SQL queries and return an indication of success
    function exec(string $query): bool;
    // perform a single unsanitized query and return a result set
    function query(string $query): Result;
    // ready a prepared statement for later execution
    function prepare(string $query, ...$paramType): Statement;
}