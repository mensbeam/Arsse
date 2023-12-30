<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db;

interface Driver {
    public const TR_PEND = 0;
    public const TR_COMMIT = 1;
    public const TR_ROLLBACK = 2;
    public const TR_PEND_COMMIT = -1;
    public const TR_PEND_ROLLBACK = -2;

    /** Creates and returns an instance of the class; this is so that either a native or PDO driver may be returned depending on what is available on the server */
    public static function create(): Driver;

    /** Returns a human-friendly name for the driver */
    public static function driverName(): string;

    /** Returns the version of the schema of the opened database; if uninitialized should return 0
     *
     * Normally the version is stored under the 'schema_version' key in the arsse_meta table, but another method may be used if appropriate
     */
    public function schemaVersion(): int;

    /** Returns the schema set to be used for database set-up */
    public static function schemaID(): string;

    /** Returns a Transaction object */
    public function begin(bool $lock = false): Transaction;

    /** Manually begins a real or synthetic transactions, with real or synthetic nesting, and returns its numeric ID
     *
     * If the database backend does not implement savepoints, IDs must still be tracked as if it does
     */
    public function savepointCreate(): int;

    /** Manually commits either the latest or a specified nested transaction */
    public function savepointRelease(int $index = null): bool;

    /** Manually rolls back either the latest or a specified nested transaction */
    public function savepointUndo(int $index = null): bool;

    /** Performs an in-place upgrade of the database schema
     *
     * The driver may choose not to implement in-place upgrading, in which case an exception should be thrown
     */
    public function schemaUpdate(int $to): bool;

    /** Executes one or more queries without parameters, returning only an indication of success */
    public function exec(string $query): bool;

    /** Executes a single query without parameters, and returns a result set */
    public function query(string $query): Result;

    /** Readies a prepared statement for later execution */
    public function prepare(string $query, ...$paramType): Statement;

    /** Readies a prepared statement for later execution */
    public function prepareArray(string $query, array $paramTypes): Statement;

    /** Reports whether the database character set is correct/acceptable
     *
     * The backend must be able to accept and provide UTF-8 text; information may be stored in any encoding capable of representing the entire range of Unicode
     */
    public function charsetAcceptable(): bool;

    /** Returns an implementation-dependent form of a reference SQL function or operator
     *
     * The tokens the implementation must understand are:
     *
     * - "greatest": the GREATEST function implemented by PostgreSQL and MySQL
     * - "least": the LEAST function implemented by PostgreSQL and MySQL
     * - "nocase": the name of a general-purpose case-insensitive collation sequence
     * - "like": the case-insensitive LIKE operator
     * - "integer": the integer type to use for explicit casts
     * - "asc": ascending sort order when dealing with nulls
     * - "desc": descending sort order when dealing with nulls
     */
    public function sqlToken(string $token): string;

    /** Returns a string literal which is properly escaped to guard against SQL injections. Delimiters are included in the output string
     *
     * This functionality should be avoided in favour of using statement parameters whenever possible
     */
    public function literalString(string $str): string;

    /** Performs implementation-specific database maintenance to ensure good performance
     *
     * This should be restricted to quick maintenance; in SQLite terms it might include ANALYZE, but not VACUUM
     */
    public function maintenance(): bool;

    /** Reports whether the implementation will coerce integer and float values to text (string) */
    public function stringOutput(): bool;
}
