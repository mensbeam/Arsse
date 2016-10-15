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

	public function update($to) {
		$sep = \DIRECTORY_SEPARATOR;
		$path = \JKingWeb\NewsSync\BASE."sql".$sep."SQLite3".$sep;
		$this->begin();
		for($a = $this->schemaVersion(); $a < $to; $a++) {
			$file = $path.$a.".sql";
			if(!file_exists($file)) $this->fail(new Exception("updateMissing", ['version' => $a, 'driver_name' => $this->driverName()]));
			if(!is_readable($file)) $this->fail(new Exception("updateUnreadable", ['version' => $a, 'driver_name' => $this->driverName()]));
			$sql = @file_get_contents($file);
			if($sql===false) $this->fail(new Exception("updateUnusable", ['version' => $a, 'driver_name' => $this->driverName()]));
			$this->exec($sql);
		}
		$this->commit();
	}

	public function exec(string $query): bool {
		return (bool) $this->db->exec($query);
	}
}