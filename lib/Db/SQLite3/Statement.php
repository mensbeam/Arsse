<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

class Statement extends \JKingWeb\Arsse\Db\AbstractStatement {
    use ExceptionBuilder;

    const SQLITE_BUSY = 5;
    const SQLITE_CONSTRAINT = 19;
    const SQLITE_MISMATCH = 20;
    const BINDINGS = [
        self::T_INTEGER  => \SQLITE3_INTEGER,
        self::T_FLOAT    => \SQLITE3_FLOAT,
        self::T_DATETIME => \SQLITE3_TEXT,
        self::T_BINARY   => \SQLITE3_BLOB,
        self::T_STRING   => \SQLITE3_TEXT,
        self::T_BOOLEAN  => \SQLITE3_INTEGER,
    ];

    protected $db;
    protected $st;
    protected $query;

    public function __construct(\SQLite3 $db, string $query, array $bindings = []) {
        $this->db = $db;
        $this->query = $query;
        $this->retypeArray($bindings);
    }

    protected function prepare(string $query): bool {
        try {
            // statements aren't evaluated at creation, and so should not fail
            $this->st = $this->db->prepare($query);
            return true;
        } catch (\Exception $e) { // @codeCoverageIgnore
            list($excClass, $excMsg, $excData) = $this->buildException(); // @codeCoverageIgnore
            throw new $excClass($excMsg, $excData); // @codeCoverageIgnore
        }
    }

    public function __destruct() {
        try {
            $this->st->close();
        } catch (\Throwable $e) { // @codeCoverageIgnore
        }
        unset($this->st);
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        $this->st->clear();
        $this->bindValues($values);
        try {
            $r = $this->st->execute();
        } catch (\Exception $e) {
            list($excClass, $excMsg, $excData) = $this->buildException();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $this->db->changes();
        $lastId = $this->db->lastInsertRowID();
        return new Result($r, [$changes, $lastId], $this);
    }

    protected function bindValue($value, int $type, int $position): bool {
        return $this->st->bindValue($position, $value, is_null($value) ? \SQLITE3_NULL : self::BINDINGS[$type]);
    }
}
