<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Db;

abstract class PDOStatement extends AbstractStatement {
    use PDOError;

    protected const BINDINGS = [
        self::T_INTEGER  => \PDO::PARAM_INT,
        self::T_FLOAT    => \PDO::PARAM_STR,
        self::T_DATETIME => \PDO::PARAM_STR,
        self::T_BINARY   => \PDO::PARAM_LOB,
        self::T_STRING   => \PDO::PARAM_STR,
        self::T_BOOLEAN  => \PDO::PARAM_INT, // NOTE: Integers are used rather than booleans so that they may be manipulated arithmetically
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
            [$excClass, $excMsg, $excData] = $this->buildPDOException(); // @codeCoverageIgnore
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
            [$excClass, $excMsg, $excData] = $this->buildPDOException(true);
            throw new $excClass($excMsg, $excData);
        }
        return new PDOResult($this->db, $this->st);
    }

    protected function bindValue($value, int $type, int $position): bool {
        return $this->st->bindValue($position, $value, is_null($value) ? \PDO::PARAM_NULL : self::BINDINGS[$type]);
    }
}
