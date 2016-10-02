<?php
namespace JKingWeb\NewsSync;

class Database {
	protected $drv;

	public function __construct(Conf $conf) {
		$driver = $conf->dbClass;
		$this->drv = new $driver($conf);
	}

	static public function listDrivers() {
		
	}
}