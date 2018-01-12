<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Driver {
    const TR_PEND = 0;
    const TR_COMMIT = 1;
    const TR_ROLLBACK = 2;
    const TR_PEND_COMMIT = -1;
    const TR_PEND_ROLLBACK = -2;
    
    public static function create(): Driver;
    // returns a human-friendly name for the driver (for display in installer, for example)
    public static function driverName(): string;
    // returns the version of the scheme of the opened database; if uninitialized should return 0
    public function schemaVersion(): int;
    // returns the schema set to be used for database set-up
    public static function schemaID(): string;
    // return a Transaction object
    public function begin(bool $lock = false): Transaction;
    // manually begin a real or synthetic transactions, with real or synthetic nesting
    public function savepointCreate(): int;
    // manually commit either the latest or all pending nested transactions
    public function savepointRelease(int $index = null): bool;
    // manually rollback either the latest or all pending nested transactions
    public function savepointUndo(int $index = null): bool;
    // attempt to perform an in-place upgrade of the database schema; this may be a no-op which always throws an exception
    public function schemaUpdate(int $to): bool;
    // execute one or more unsanitized SQL queries and return an indication of success
    public function exec(string $query): bool;
    // perform a single unsanitized query and return a result set
    public function query(string $query): Result;
    // ready a prepared statement for later execution
    public function prepare(string $query, ...$paramType): Statement;
    public function prepareArray(string $query, array $paramTypes): Statement;
    // report whether the database character set is correct/acceptable
    public function charsetAcceptable(): bool;
}
