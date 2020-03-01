<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

abstract class AbstractStatement implements Statement {
    use SQLState;

    const TYPE_NORM_MAP = [
        self::T_INTEGER                     => ValueInfo::M_NULL | ValueInfo::T_INT,
        self::T_STRING                      => ValueInfo::M_NULL | ValueInfo::T_STRING,
        self::T_BOOLEAN                     => ValueInfo::M_NULL | ValueInfo::T_BOOL,
        self::T_DATETIME                    => ValueInfo::M_NULL | ValueInfo::T_DATE,
        self::T_FLOAT                       => ValueInfo::M_NULL | ValueInfo::T_FLOAT,
        self::T_BINARY                      => ValueInfo::M_NULL | ValueInfo::T_STRING,
        self::T_NOT_NULL + self::T_INTEGER  => ValueInfo::T_INT,
        self::T_NOT_NULL + self::T_STRING   => ValueInfo::T_STRING,
        self::T_NOT_NULL + self::T_BOOLEAN  => ValueInfo::T_BOOL,
        self::T_NOT_NULL + self::T_DATETIME => ValueInfo::T_DATE,
        self::T_NOT_NULL + self::T_FLOAT    => ValueInfo::T_FLOAT,
        self::T_NOT_NULL + self::T_BINARY   => ValueInfo::T_STRING,
    ];

    protected $types = [];

    abstract public function runArray(array $values = []): Result;
    abstract protected function bindValue($value, int $type, int $position): bool;
    abstract protected function prepare(string $query): bool;
    abstract protected static function buildEngineException($code, string $msg): array;

    public function run(...$values): Result {
        return $this->runArray($values);
    }

    public function retype(...$bindings): bool {
        return $this->retypeArray($bindings);
    }

    public static function mungeQuery(string $query, array $types, ...$extraData): string {
        return $query;
    }

    public function retypeArray(array $bindings): bool {
        $this->types = [];
        foreach (ValueInfo::flatten($bindings) as $binding) { // recursively flatten any arrays, which may be provided for SET or IN() clauses
            $bindId = self::TYPES[trim(strtolower($binding))] ?? 0;
            assert($bindId, new Exception("paramTypeInvalid", $binding));
            $this->types[] = $bindId;
        }
        $this->prepare(static::mungeQuery($this->query, $this->types));
        return true;
    }

    protected function cast($v, int $t) {
        switch ($t) {
            case self::T_DATETIME:
                return Date::transform($v, "sql");
            case self::T_DATETIME + self::T_NOT_NULL:
                $v = Date::transform($v, "sql");
                return $v ? $v : "0001-01-01 00:00:00";
            default:
                $v = ValueInfo::normalize($v, self::TYPE_NORM_MAP[$t], null, "sql");
                return is_bool($v) ? (int) $v : $v;
        }
    }

    protected function bindValues(array $values): bool {
        // recursively flatten any arrays, which may be provided for SET or IN() clauses
        $values = ValueInfo::flatten($values);
        foreach ($values as $a => $value) {
            if (array_key_exists($a, $this->types)) {
                $value = $this->cast($value, $this->types[$a]);
                $this->bindValue($value, $this->types[$a] % self::T_NOT_NULL, ++$a);
            } else {
                throw new Exception("paramTypeMissing", $a + 1);
            }
        }
        // once all values are bound, check that all parameters have been supplied values and bind null for any missing ones
        // SQLite will happily substitute null for a missing value, but other engines (viz. PostgreSQL) produce an error
        for ($a = sizeof($values); $a < sizeof($this->types); $a++) {
            $this->bindValue(null, $this->types[$a] % self::T_NOT_NULL, $a + 1);
        }
        return true;
    }
}
