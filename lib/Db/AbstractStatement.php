<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

abstract class AbstractStatement implements Statement {
    protected $types = [];
    protected $isNullable = [];

    abstract function runArray(array $values): Result;
    abstract static function dateFormat(int $part = self::TS_BOTH): string;

    public function run(...$values): Result {
        return $this->runArray($values);
    }

    public function rebind(...$bindings): bool {
        return $this->rebindArray($bindings);
    }

    public function rebindArray(array $bindings, bool $append = false): bool {
        if(!$append) $this->types = [];
        foreach($bindings as $binding) {
            if(is_array($binding)) {
                // recursively flatten any arrays, which may be provided for SET or IN() clauses
                $this->rebindArray($binding, true);
            } else {
                $binding = trim(strtolower($binding));
                if(strpos($binding, "strict ")===0) {
                    // "strict" types' values may never be null; null values will later be cast to the type specified
                    $this->isNullable[] = false;
                    $binding = substr($binding, 7);
                } else {
                    $this->isNullable[] = true;
                }
                if(!array_key_exists($binding, self::TYPES)) throw new Exception("paramTypeInvalid", $binding);
                $this->types[] = self::TYPES[$binding];
            }
        }
        return true;
    }

    protected function cast($v, string $t, bool $nullable) {
        switch($t) {
            case "date":
                if(is_null($v) && !$nullable) $v = 0;
                return $this->formatDate($v, self::TS_DATE);
            case "time":
                if(is_null($v) && !$nullable) $v = 0;
                return $this->formatDate($v, self::TS_TIME);
            case "datetime":
                if(is_null($v) && !$nullable) $v = 0;
                return $this->formatDate($v, self::TS_BOTH);
            case "null":
            case "integer":
            case "float":
            case "binary":
            case "string":
            case "boolean":
                if($t=="binary") $t = "string";
                $value = $v;
                try{
                    settype($value, $t);
                } catch(\Throwable $e) {
                    // handle objects
                    $value = $v;
                    if($value instanceof \DateTimeInterface) {
                        $value = $value->getTimestamp();
                        if($t=="string") $value = $this->formatDate($value, self::TS_BOTH);
                        settype($value, $t);
                    } else {
                        $value = null;
                        settype($value, $t);
                    }
                }
                return $value;
            default:
                throw new Exception("paramTypeUnknown", $type);
        }
    }

    protected function formatDate($date, int $part = self::TS_BOTH) {
        // convert input to a Unix timestamp
        if($date instanceof \DateTimeInterface) {
            $time = $date->getTimestamp();
        } else if(is_numeric($date)) {
            $time = (int) $date;
        } else if($date===null) {
            return null;
        } else if(is_string($date)) {
            $time = strtotime($date);
            if($time===false) return null;
        } else if (is_bool($date)) {
            return null;
        } else {
            $time = (int) $date;
        }
        // ISO 8601 with space in the middle instead of T.
        return date($this->dateFormat($part), $time);
    }
}