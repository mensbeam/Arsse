<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class DriverSQLite3 implements Driver {
	use Common, CommonSQLite3 {
		CommonSQLite3::schemaVersion insteadof Common;
	}
	
	protected $db;
	protected $data;
	
	private function __construct(\JKingWeb\NewsSync\RuntimeData $data, bool $install = false) {
		$this->data = $data;
		$file = $data->conf->dbSQLite3File;
		// if the file exists (or we're initializing the database), try to open it and set initial options
		try {
			$this->db = new \SQLite3($file, ($install) ? \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE : \SQLITE3_OPEN_READWRITE, $data->conf->dbSQLite3Key);
			$this->db->enableExceptions(true);
			$this->exec("PRAGMA journal_mode = wal");
			$this->exec("PRAGMA foreign_keys = yes");
		} catch(\Throwable $e) {
			// if opening the database doesn't work, check various pre-conditions to find out what the problem might be
			if(!file_exists($file)) {
				if($install && !is_writable(dirname($file))) throw new Exception("fileUncreatable", dirname($file));
				throw new Exception("fileMissing", $file);
			}
			if(!is_readable($file) && !is_writable($file)) throw new Exception("fileUnusable", $file);
			if(!is_readable($file)) throw new Exception("fileUnreadable", $file);
			if(!is_writable($file)) throw new Exception("fileUnwritable", $file);
			// otherwise the database is probably corrupt
			throw new Exception("fileCorrupt", $mainfile);
		}
	}

	public function __destruct() {
		$this->db->close();
		unset($this->db);
	}

	static public function create(\JKingWeb\NewsSync\RuntimeData $data, bool $install = false): Driver {
		// check to make sure required extensions are loaded
		if(class_exists("SQLite3")) {
			return new self($data, $install);
		} else if(class_exists("PDO") && in_array("sqlite",\PDO::getAvailableDrivers())) {
			return new DriverSQLite3PDO($data, $install);
		} else {
			throw new Exception("extMissing", self::driverName());
		}
	}

	public function query(string $query): Result {
		return new ResultSQLite3($this->db->query($query));
	}

	public function prepareArray(string $query, array $paramTypes): Statement {
		return new StatementSQLite3($this->db->prepare($query), $paramTypes);
	}
}