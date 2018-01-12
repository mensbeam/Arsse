<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db\SQLite3;

use JKingWeb\Arsse\Db\Exception;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ExceptionTimeout;

class Statement extends \JKingWeb\Arsse\Db\AbstractStatement {
    use ExceptionBuilder;

    const SQLITE_BUSY = 5;
    const SQLITE_CONSTRAINT = 19;
    const SQLITE_MISMATCH = 20;
    const BINDINGS = [
        "integer"   => \SQLITE3_INTEGER,
        "float"     => \SQLITE3_FLOAT,
        "datetime"  => \SQLITE3_TEXT,
        "binary"    => \SQLITE3_BLOB,
        "string"    => \SQLITE3_TEXT,
        "boolean"   => \SQLITE3_INTEGER,
    ];

    protected $db;
    protected $st;

    public function __construct(\SQLite3 $db, \SQLite3Stmt $st, array $bindings = []) {
        $this->db = $db;
        $this->st = $st;
        $this->retypeArray($bindings);
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
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $this->db->changes();
        $lastId = $this->db->lastInsertRowID();
        return new Result($r, [$changes, $lastId], $this);
    }

    protected function bindValue($value, string $type, int $position): bool {
        return $this->st->bindValue($position, $value, is_null($value) ? \SQLITE3_NULL : self::BINDINGS[$type]);
    }
}
