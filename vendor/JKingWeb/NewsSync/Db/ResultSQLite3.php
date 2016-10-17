<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class ResultSQLite3 implements Result {
	protected $set;
	protected $pos = 0;
	protected $cur = null;

	public function __construct(\SQLite3Result $resultObj) {
		$this->set = $resultObj;
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
}