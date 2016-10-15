<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

Trait Common {
	protected $transDepth = 0;

	public function fail(\Throwable $e, bool $bool = false) {
		$this->rollback($all);
		throw $e;
	}
	
	public function begin(): bool {
		$this->exec("SAVEPOINT newssync_".($this->transDepth));
		$this->transDepth += 1;
		return true;
	}

	public function commit(bool $all = false): bool {
		if($this->transDepth==0) return false;
		if(!$all) {
			$this->exec("RELEASE SAVEPOINT newssync_".($this->transDepth - 1));
			$this->transDepth -= 1;
		} else {
			$this->exec("COMMIT TRANSACTION");
			$this->transDepth = 0;	
		}
		return true;
	}

	public function rollback(bool $all = false): bool {
		if($this->transDepth==0) return false;
		if(!$all) {
			$this->exec("ROLLBACK TRANSACTION TO SAVEPOINT newssync_".($this->transDepth - 1));
			$this->transDepth -= 1;
			if($this->transDepth==0) $this->exec("ROLLBACK TRANSACTION");
		} else {
			$this->exec("ROLLBACK TRANSACTION");
			$this->transDepth = 0;
		}
		return true;
	}

	public function prepare(string $query, string ...$paramType): Statement {
		return $this->prepareArray($query, $paramType);
	}

}