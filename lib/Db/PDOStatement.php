<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

class PDOStatement extends AbstractStatement {
    use PDOError;

    const BINDINGS = [
        "integer"   => \PDO::PARAM_INT,
        "float"     => \PDO::PARAM_STR,
        "datetime"  => \PDO::PARAM_STR,
        "binary"    => \PDO::PARAM_LOB,
        "string"    => \PDO::PARAM_STR,
        "boolean"   => \PDO::PARAM_INT, // FIXME: using \PDO::PARAM_BOOL leads to incompatibilities with versions of SQLite bundled prior to PHP 7.3
    ];

    protected $st;
    protected $db;

    public function __construct(\PDO $db, \PDOStatement $st, array $bindings = []) {
        $this->db = $db;
        $this->st = $st;
        $this->retypeArray($bindings);
    }

    public function __destruct() {
        unset($this->st);
    }

    public function runArray(array $values = []): \JKingWeb\Arsse\Db\Result {
        $this->st->closeCursor();
        $this->bindValues($values);
        try {
            $this->st->execute();
        } catch (\PDOException $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $this->st->rowCount();
        try {
            $lastId = 0;
            $lastId = $this->db->lastInsertId();
        } catch (\PDOException $e) { // @codeCoverageIgnore
        }
        return new PDOResult($this->st, [$changes, $lastId]);
    }

    protected function bindValue($value, string $type, int $position): bool {
        return $this->st->bindValue($position, $value, is_null($value) ? \PDO::PARAM_NULL : self::BINDINGS[$type]);
    }
}
