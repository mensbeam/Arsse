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

    protected $types = [];
    protected $isNullable = [];

    abstract public function runArray(array $values = []): Result;
    abstract protected function bindValue($value, string $type, int $position): bool;
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

    public function retypeArray(array $bindings, bool $append = false): bool {
        if (!$append) {
            $this->types = [];
        }
        foreach ($bindings as $binding) {
            if (is_array($binding)) {
                // recursively flatten any arrays, which may be provided for SET or IN() clauses
                $this->retypeArray($binding, true);
            } else {
                $binding = trim(strtolower($binding));
                if (strpos($binding, "strict ")===0) {
                    // "strict" types' values may never be null; null values will later be cast to the type specified
                    $this->isNullable[] = false;
                    $binding = substr($binding, 7);
                } else {
                    $this->isNullable[] = true;
                }
                if (!array_key_exists($binding, self::TYPES)) {
                    throw new Exception("paramTypeInvalid", $binding); // @codeCoverageIgnore
                }
                $this->types[] = self::TYPES[$binding];
            }
        }
        if (!$append) {
            $this->prepare(static::mungeQuery($this->query, $this->types));
        }
        return true;
    }

    protected function cast($v, string $t, bool $nullable) {
        switch ($t) {
            case "datetime":
                $v = Date::transform($v, "sql");
                if (is_null($v) && !$nullable) {
                    $v = 0;
                    $v = Date::transform($v, "sql");
                }
                return $v;
            case "integer":
                return ValueInfo::normalize($v, ValueInfo::T_INT | ($nullable ? ValueInfo::M_NULL : 0), null, "sql");
            case "float":
                return ValueInfo::normalize($v, ValueInfo::T_FLOAT | ($nullable ? ValueInfo::M_NULL : 0), null, "sql");
            case "binary":
            case "string":
                return ValueInfo::normalize($v, ValueInfo::T_STRING | ($nullable ? ValueInfo::M_NULL : 0), null, "sql");
            case "boolean":
                $v = ValueInfo::normalize($v, ValueInfo::T_BOOL | ($nullable ? ValueInfo::M_NULL : 0), null, "sql");
                return is_null($v) ? $v : (int) $v;
            default:
                throw new Exception("paramTypeUnknown", $type); // @codeCoverageIgnore
        }
    }

    protected function bindValues(array $values, int $offset = null): int {
        $a = (int) $offset;
        foreach ($values as $value) {
            if (is_array($value)) {
                // recursively flatten any arrays, which may be provided for SET or IN() clauses
                $a += $this->bindValues($value, $a);
            } elseif (array_key_exists($a, $this->types)) {
                $value = $this->cast($value, $this->types[$a], $this->isNullable[$a]);
                $this->bindValue($value, $this->types[$a], ++$a);
            } else {
                throw new Exception("paramTypeMissing", $a+1);
            }
        }
        // once the last value is bound, check that all parameters have been supplied values and bind null for any missing ones
        // SQLite will happily substitute null for a missing value, but other engines (viz. PostgreSQL) produce an error
        if (is_null($offset)) {
            while ($a < sizeof($this->types)) {
                $this->bindValue(null, $this->types[$a], ++$a);
            }
        }
        return $a - $offset;
    }
}
