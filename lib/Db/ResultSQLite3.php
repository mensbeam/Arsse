<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class ResultSQLite3 implements Result {
	protected $st;
	protected $set;
	protected $pos = 0;
	protected $cur = null;
	protected $rows = 0;

	public function __construct($result, $changes, $statement = null) {
		$this->st = $statement; //keeps the statement from being destroyed, invalidating the result set
		$this->set = $result;
		$this->rows = $changes;
	}

	public function __destruct() {
		$this->set->finalize();
		unset($this->set);
	}

	public function valid() {
		$this->cur = $this->set->fetchArray(\SQLITE3_ASSOC);
		return ($this->cur !== false);
	}

	public function next() {
		$this->cur = null;
		$this->pos += 1;
	}

	public function current() {
		return $this->cur;
	}

	public function key() {
		return $this->pos;
	}

	public function rewind() {
		$this->pos = 0;
		$this->cur = null;
		$this->set->reset();
	}

	public function getSingle() {
		$this->next();
		if($this->valid()) {
			$keys = array_keys($this->cur);
			return $this->cur[array_shift($keys)];
		}
		return null;
	}

	public function get() {
		$this->next();
		return ($this->valid() ? $this->cur : null);
	}

	public function changes() {
		return $this->rows;
	}
}