<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;
use JKingWeb\NewsSync\Db\DriverSQLite3 as Driver;

class StatementSQLite3 implements Statement {
    protected $db;
    protected $st;
    protected $types;

    public function __construct(\SQLite3 $db, \SQLite3Stmt $st, array $bindings = []) {
        $this->db = $db;
        $this->st = $st;
        $this->rebindArray($bindings);
    }

    public function __destruct() {
        $this->st->close();
        unset($this->st);
    }

    public function run(...$values): Result {
        return $this->runArray($values);
    }

    public function runArray(array $values = null): Result {
        $this->st->clear();
        $l = sizeof($values);
        for($a = 0; $a < $l; $a++) {
            // find the right SQLite binding type for the value/specified type
            $type = null;
            if($values[$a]===null) {
                $type = \SQLITE3_NULL;
            } else if(array_key_exists($a,$this->types)) {
                $type = $this->translateType($this->types[$a]);
            } else {
                $type = \SQLITE3_TEXT;
            }
            // cast values if necessary
            switch($this->types[$a]) {
                case "null":
                    $value = null; break;
                case "integer":
                    $value = (int) $values[$a]; break;
                case "float":
                    $value = (float) $values[$a]; break;
                case "date":
                    $value = Driver::formatDate($values[$a], Driver::TS_DATE); break;
                case "time":
                    $value = Driver::formatDate($values[$a], Driver::TS_TIME); break;
                case "datetime":
                    $value = Driver::formatDate($values[$a], Driver::TS_BOTH); break;
                case "binary":
                    $value = (string) $values[$a]; break;
                case "text":
                    $value = $values[$a]; break;
                case "boolean":
                    $value = (bool) $values[$a]; break;
                default:
                    throw new Exception("paramTypeUnknown", $type);
            }
            if($type===null) {
                $this->st->bindParam($a+1, $value);
            } else {
                $this->st->bindParam($a+1, $value, $type);
            }
        }
        return new ResultSQLite3($this->st->execute(), $this->db->changes(), $this);
    }

    public function rebind(...$bindings): bool {
        return $this->rebindArray($bindings);
    }

    protected function translateType(string $type) {
        switch($type) {
            case "null":
                return \SQLITE3_NULL;
            case "integer":
                return \SQLITE3_INTEGER;
            case "float":
                return \SQLITE3_FLOAT;
            case "date":
            case "time":
            case "datetime":
                return \SQLITE3_TEXT;
            case "binary":
                return \SQLITE3_BLOB;
            case "text":
                return \SQLITE3_TEXT;
            case "boolean":
                return \SQLITE3_INTEGER;
            default:
                throw new Db\Exception("paramTypeUnknown", $binding);
        }
    }

    public function rebindArray(array $bindings): bool {
        $this->types = [];
        foreach($bindings as $binding) {
            $binding = trim(strtolower($binding));
            if(!array_key_exists($binding, self::TYPES)) throw new Db\Exception("paramTypeInvalid", $binding);
            $this->types[] = self::TYPES[$binding];
        }
        return true;
    }
}