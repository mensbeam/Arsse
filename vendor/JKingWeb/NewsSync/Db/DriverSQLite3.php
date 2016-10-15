<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class DriverSQLite3 implements Driver {
	use Common, CommonSQLite3;
	
	protected $db;
	
	private function __construct(\JKingWeb\NewsSync\Conf $conf, bool $install = false) {
		// normalize the path
		$path = $conf->dbSQLite3Path;
		$sep = \DIRECTORY_SEPARATOR;
		if(substr($path,-(strlen($sep))) != $sep) $path .= $sep;
		$mainfile = $path."newssync-main.db";
		$feedfile = $path."newssync-feeds.db";
		// if the files exists (or we're initializing the database), try to open it and set initial options
		try {
			$this->db = new \SQLite3($mainfile, ($install) ? \SQLITE3_OPEN_READWRITE | \SQLITE3_OPEN_CREATE : \SQLITE3_OPEN_READWRITE, $conf->dbSQLite3Key);
			$this->db->enableExceptions(true);
			$attach = "'".$this->db->escapeString($feedfile)."'";
			$this->exec("ATTACH DATABASE $attach AS feeds");
			$this->exec("PRAGMA main.journal_mode = wal");
			$this->exec("PRAGMA feeds.journal_mode = wal");
			$this->exec("PRAGMA foreign_keys = yes");
		} catch(\Throwable $e) {
			// if opening the database doesn't work, check various pre-conditions to find out what the problem might be
			foreach([$mainfile, $feedfile] as $file) {
				if(!file_exists($file)) {
					if($install && !is_writable(dirname($file))) throw new Exception("fileUncreatable", dirname($file));
					throw new Exception("fileMissing", $file);
				}
				if(!is_readable($file) && !is_writable($file)) throw new Exception("fileUnusable", $file);
				if(!is_readable($file)) throw new Exception("fileUnreadable", $file);
				if(!is_writable($file)) throw new Exception("fileUnwritable", $file);
			}
			// otherwise the database is probably corrupt
			throw new Exception("fileCorrupt", $mainfile);
		}
	}

	public function __destruct() {
		$this->db->close();
		unset($this->db);
	}

	static public function create(\JKingWeb\NewsSync\Conf $conf, bool $install = false): Driver {
		// check to make sure required extensions are loaded
		if(class_exists("SQLite3")) {
			return new self($conf, $install);
		} else if(class_exists("PDO") && in_array("sqlite",\PDO::getAvailableDrivers())) {
			return new DriverSQLite3PDO($conf, $install);
		} else {
			throw new Exception("extMissing", self::driverName());
		}
	}

	public function unsafeQuery(string $query): Result {
		return new ResultSQLite3($this->db->query($query));
	}

	public function prepareArray(string $query, array $paramTypes): Statement {
		return new StatementSQLite3($this->db->prepare($query), $paramTypes);
	}
}