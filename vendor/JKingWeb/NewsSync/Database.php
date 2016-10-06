<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Database {
	protected $drv;

	public function __construct(Conf $conf) {
		$driver = $conf->dbClass;
		$this->drv = $driver::create($conf);
	}

	static public function listDrivers(): array {
		$sep = \DIRECTORY_SEPARATOR;
		$path = __DIR__.$sep."Db".$sep;
		$classes = [];
		foreach(glob($path."Driver?*.php") as $file) {
			$name = basename($file, ".php");
			if(substr($name,-3) != "PDO") {
				$name = NS_BASE."Db\\$name";
				if(class_exists($name)) {
					$classes[$name] = $name::driverName();
				}
			}			 
		}
		return $classes;
	}

	public function schemaVersion(): int {
		return $this->drv->schemaVersion();
	}
}