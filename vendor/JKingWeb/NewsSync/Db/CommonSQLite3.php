<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

Trait CommonSQLite3 {
	
	static public function driverName(): string {
		return "SQLite 3";
	}

	public function schemaVersion(string $schema = "main"): int {
		return $this->unsafeQuery("PRAGMA $schema.user_version")->getSingle();
	}

	public function exec(string $query): bool {
		return (bool) $this->db->exec($query);
	}
}