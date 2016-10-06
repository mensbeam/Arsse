<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class ResultSQLite3 implements Result {
	protected $set;

	public function __construct(\SQLite3Result $resultObj) {
		$this->set = $resultObj;
	}

	public function __destruct() {
		$this->set->finalize();
		unset($this->set);
	}

	public function __invoke() {
		return $this->get();
	}

	public function get() {
		return $this->set->fetchArray(\SQLITE3_ASSOC);
	}

	public function getSingle() {
		$res = $this->get();
		if($res===FALSE) return null;
		return array_shift($res);
	}
}