<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

abstract class PDOStatement extends AbstractStatement {
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
    protected $query;

    public function __construct(\PDO $db, string $query, array $bindings = []) {
        $this->db = $db;
        $this->query = $query;
        $this->retypeArray($bindings);
    }

    protected function prepare(string $query): bool {
        try {
            // PDO statements aren't usually evaluated at creation, and so should not fail
            $this->st = $this->db->prepare($query);
            return true;
        } catch (\PDOException $e) { // @codeCoverageIgnore
            list($excClass, $excMsg, $excData) = $this->buildPDOException(); // @codeCoverageIgnore
            throw new $excClass($excMsg, $excData); // @codeCoverageIgnore
        }
    }

    public function __destruct() {
        unset($this->st, $this->db);
    }

    public function runArray(array $values = []): Result {
        $this->st->closeCursor();
        $this->bindValues($values);
        try {
            $this->st->execute();
        } catch (\PDOException $e) {
            list($excClass, $excMsg, $excData) = $this->buildPDOException(true);
            throw new $excClass($excMsg, $excData);
        }
        return new PDOResult($this->db, $this->st);
    }

    protected function bindValue($value, string $type, int $position): bool {
        return $this->st->bindValue($position, $value, is_null($value) ? \PDO::PARAM_NULL : self::BINDINGS[$type]);
    }
}
