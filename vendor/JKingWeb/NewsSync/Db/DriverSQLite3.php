<?php
namespace JKingWeb\NewsSync\Db;

class DriverSQLite3 implements DriverInterface {
	protected $db;
	protected $pdo = false;
	
	public function __construct(\JKingWeb\NewsSync\Conf $conf, bool $install = false) {
		// check to make sure required extensions are loaded
		if(class_exists("SQLite3")) {
			$this->pdo = false;
		} else if(class_exists("PDO") && in_array("sqlite",\PDO::getAvailableDrivers())) {
			$this->pdo = true;
		} else {
			throw new Exception("extMissing", self::driverName());
		}
		// if the file exists (or we're initializing the database), try to open it and set initial options
		if((!$install && file_exists($conf->dbSQLite3File)) || $install) {
			try {
				$this->db = ($this->PDO) ? (new \SQLite3($conf->dbSQLite3File, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $conf->dbSQLite3Key)) : (new PDO("sqlite:".$conf->dbSQLite3File));
				//FIXME: add foreign key enforcement, WAL mode
			} catch(\Throwable $e) {
				// if opening the database doesn't work, check various pre-conditions to find out what the problem might be
				foreach([$conf->dbSQLite3File, $conf->dbSQLite3File."-wal", $conf->dbSQLite3File."-shm"] as $file) {
					if(!file_exists($file)) {
						if($install && !is_writable(dirname($file))) throw new Exception("fileUncreatable", dirname($file));
						throw new Exception("fileMissing", $file);
					}
					if(!is_readable($file) && !is_writable($file)) throw new Exception("fileUnusable", $file);
					if(!is_readable($file)) throw new Exception("fileUnreadable", $file);
					if(!is_writable($file)) throw new Exception("fileUnwritable", $file);
				}
				// otherwise the database is probably corrupt
				throw new Exception("fileCorrupt", $conf->dbSQLite3File);
			}
		} else {
			throw new Exception("fileMissing", $conf->dbSQLite3File);
		}
	}

	static public function driverName(): string {
		return "SQLite3";
	}
}