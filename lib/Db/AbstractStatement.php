<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Misc\Date;

abstract class AbstractStatement implements Statement {
    protected $types = [];
    protected $isNullable = [];

    abstract public function runArray(array $values = []): Result;

    public function run(...$values): Result {
        return $this->runArray($values);
    }

    public function retype(...$bindings): bool {
        return $this->retypeArray($bindings);
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
        return true;
    }

    protected function cast($v, string $t, bool $nullable) {
        switch ($t) {
            case "datetime":
                if (is_null($v) && !$nullable) {
                    $v = 0;
                }
                return Date::transform($v, "sql");
            case "integer":
            case "float":
            case "binary":
            case "string":
            case "boolean":
                if ($t=="binary") {
                    $t = "string";
                }
                if ($v instanceof \DateTimeInterface) {
                    if ($t=="string") {
                        return Date::transform($v, "sql");
                    } else {
                        $v = $v->getTimestamp();
                        settype($v, $t);
                    }
                } else {
                    settype($v, $t);
                }
                return $v;
            default:
                throw new Exception("paramTypeUnknown", $type); // @codeCoverageIgnore
        }
    }
}
