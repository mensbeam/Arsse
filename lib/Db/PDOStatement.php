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
        "boolean"   => \PDO::PARAM_BOOL,
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

    protected function bindValues(array $values, int $offset = 0): int {
        $a = $offset;
        foreach ($values as $value) {
            if (is_array($value)) {
                // recursively flatten any arrays, which may be provided for SET or IN() clauses
                $a += $this->bindValues($value, $a);
            } elseif (array_key_exists($a, $this->types)) {
                // if the parameter type is something other than the known values, this is an error
                assert(array_key_exists($this->types[$a], self::BINDINGS), new Exception("paramTypeUnknown", $this->types[$a]));
                // if the parameter type is null or the value is null (and the type is nullable), just bind null
                if ($this->types[$a]=="null" || ($this->isNullable[$a] && is_null($value))) {
                    $this->st->bindValue($a+1, null, \PDO::PARAM_NULL);
                } else {
                    // otherwise cast the value to the right type and bind the result
                    $type = self::BINDINGS[$this->types[$a]];
                    $value = $this->cast($value, $this->types[$a], $this->isNullable[$a]);
                    // re-adjust for null casts
                    if ($value===null) {
                        $type = \PDO::PARAM_NULL;
                    }
                    // perform binding
                    $this->st->bindValue($a+1, $value, $type);
                }
                $a++;
            } else {
                throw new Exception("paramTypeMissing", $a+1);
            }
        }
        return $a - $offset;
    }
}
