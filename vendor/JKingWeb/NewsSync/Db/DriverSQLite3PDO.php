<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

class DriverSQLite3 implements Driver {
	use CommonPDO, CommonSQLite3;
	
	protected $db;
	
	private function __construct(\JKingWeb\NewsSync\Conf $conf, bool $install = false) {
		// FIXME: stub
	}

	public function __destruct() {
		// FIXME: stub
	}

	static public function create(\JKingWeb\NewsSync\Conf $conf, bool $install = false): Driver {
		// check to make sure required extensions are loaded
		if(class_exists("PDO") && in_array("sqlite",\PDO::getAvailableDrivers())) {
			return new self($conf, $install);
		} else if(class_exists("SQLite3")) {
			return new DriverSQLite3($conf, $install);
		} else {
			throw new Exception("extMissing", self::driverName());
		}
	}
}