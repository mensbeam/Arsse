<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

Trait CommonPDO {
	public function query(string $query): Result {
		return new ResultPDO($this->db->query($query));
	}

	public function prepareArray(string $query, array $paramTypes): Statement {
		return new StatementPDO($query, $paramTypes);
	}

	public function prepare(string $query, string ...$paramType): Statement {
		return $this->prepareArray($query, $paramType);
	}
}