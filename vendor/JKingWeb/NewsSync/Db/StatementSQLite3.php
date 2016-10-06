<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class StatementSQLite3 implements Statement {
	protected $st;
	protected $types;

	public function __construct(\SQLite3Stmt $st, $bindings = null) {
		$this->st = $st;
		$this->types = [];
		foreach($bindings as $binding) {
			switch(trim(strtolower($binding))) {
				case "int":
				case "integer":
					$this->types[] = \SQLITE3_INTEGER; break;
				case "float":
				case "double":
				case "real":
				case "numeric":
					$this->types[] = \SQLITE3_FLOAT; break;
				case "date":
				case "time":
				case "datetime":
				case "timestamp":
					$this->types[] = \SQLITE3_TEXT; break;
				case "blob":
				case "bin":
				case "binary":
					$this->types[] = \SQLITE3_BLOB; break;
				case "text":
				case "string":
				case "str":
				default:
					$this->types[] = \SQLITE3_TEXT; break;
			}
		}
	}

	public function __destruct() {
		$this->st->close();
		unset($this->st);
	}

	public function __invoke(&...$values) {
		return $this->runArray($values);
	}

	public function run(&...$values): Result {
		return $this->runArray($values);
	}

	public function runArray(array &$values = null): Result {
		$this->st->clear();
		$l = sizeof($values);
		for($a = 0; $a < $l; $a++) {
			if($values[$a]===null) {
				$type = \SQLITE3_NULL;
			} else {
				$type = (array_key_exists($a,$this->types)) ? $this->types[$a] : \SQLITE3_TEXT;
			}
			$st->bindParam($a+1, $values[$a], $type);
		}
		return new ResultSQLite3($st->execute());
	}
}