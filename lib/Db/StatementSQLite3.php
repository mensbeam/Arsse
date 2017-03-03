<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class StatementSQLite3 extends AbstractStatement {
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
            self::TS_TIME => 'h:i:sP',
            self::TS_DATE => 'Y-m-d',
            self::TS_BOTH => 'Y-m-d h:i:sP',
        ])[$part];
    }

    public function runArray(array $values = null): Result {
        $this->st->clear();
        $l = sizeof($values);
        for($a = 0; $a < $l; $a++) {
            // find the right SQLite binding type for the value/specified type
            if($values[$a]===null) {
                $type = \SQLITE3_NULL;
            } else if(array_key_exists($a,$this->types)) {
                if(!array_key_exists($this->types[$a], self::BINDINGS)) throw new Exception("paramTypeUnknown", $this->types[$a]);
                $type = self::BINDINGS[$this->types[$a]];
            } else {
                throw new Exception("paramTypeMissing", $a+1);
            }
            // cast value if necessary
            $values[$a] = $this->cast($values[$a], $this->types[$a]);
            // re-adjust for null casts
            if($values[$a]===null) $type = \SQLITE3_NULL;
            // perform binding
            $this->st->bindValue($a+1, $values[$a], $type);
        }
        return new ResultSQLite3($this->st->execute(), $this->db->changes(), $this);
    }
}