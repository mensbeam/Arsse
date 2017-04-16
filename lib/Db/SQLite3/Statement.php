<?php
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
        "null"      => \SQLITE3_NULL,
        "integer"   => \SQLITE3_INTEGER,
        "float"     => \SQLITE3_FLOAT,
        "date"      => \SQLITE3_TEXT,
        "time"      => \SQLITE3_TEXT,
        "datetime"  => \SQLITE3_TEXT,
        "binary"    => \SQLITE3_BLOB,
        "string"    => \SQLITE3_TEXT,
        "boolean"   => \SQLITE3_INTEGER,
    ];

    protected $db;
    protected $st;
    protected $types;

    public function __construct(\SQLite3 $db, \SQLite3Stmt $st, array $bindings = []) {
        $this->db = $db;
        $this->st = $st;
        $this->rebindArray($bindings);
    }

    public function __destruct() {
        try {$this->st->close();} catch(\Throwable $e) {}
        unset($this->st);
    }

    public static function dateFormat(int $part = self::TS_BOTH): string {
        return ([
            self::TS_TIME => 'h:i:s',
            self::TS_DATE => 'Y-m-d',
            self::TS_BOTH => 'Y-m-d h:i:s',
        ])[$part];
    }

    public function runArray(array $values = null): \JKingWeb\Arsse\Db\Result {
        $this->st->clear();
        if(!is_null($values)) $this->bindValues($values);
        try {
            $r = $this->st->execute();
        } catch(\Exception $e) {
            list($excClass, $excMsg, $excData) = $this->exceptionBuild();
            throw new $excClass($excMsg, $excData);
        }
        $changes = $this->db->changes();
        $lastId = $this->db->lastInsertRowID();
        return new Result($r, [$changes, $lastId], $this);
    }

    protected function bindValues(array $values, int $offset = 0): int {
        $a = $offset;
        foreach($values as $value) {
            if(is_array($value)) {
                // recursively flatten any arrays, which may be provided for SET or IN() clauses
                $a += $this->bindValues($value, $a);
            } else {
                // find the right SQLite binding type for the value/specified type
                if($value===null) {
                    $type = \SQLITE3_NULL;
                } else if(array_key_exists($a,$this->types)) {
                    if(!array_key_exists($this->types[$a], self::BINDINGS)) throw new Exception("paramTypeUnknown", $this->types[$a]);
                    $type = self::BINDINGS[$this->types[$a]];
                } else {
                    throw new Exception("paramTypeMissing", $a+1);
                }
                // cast value if necessary
                $value = $this->cast($value, $this->types[$a]);
                // re-adjust for null casts
                if($value===null) $type = \SQLITE3_NULL;
                // perform binding
                $this->st->bindValue($a+1, $value, $type);
                $a++;
            }
        }
        return $a - $offset;
    }
}