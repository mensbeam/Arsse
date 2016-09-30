<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Conf {
	public $lang 				= "en";
	
	public $dbClass				= NS_BASE."Db\\DriverSQLite3";
	public $dbSQLite3PDO 		= false;
	public $dbSQLite3File 		= BASE."newssync.db";
	public $dbPostgreSQLPDO 	= false;
	public $dbPostgreSQLHost 	= "localhost";
	public $dbPostgreSQLUser 	= "newssync";
	public $dbPostgreSQLPass 	= "";
	public $dbPostgreSQLPort 	= 5432;
	public $dbPostgreSQLDb 		= "newssync";
	public $dbPostgreSQLSchema 	= "";
	public $dbMySQLPDO 			= false;
	public $dbMySQLHost 		= "localhost";
	public $dbMySQLUser 		= "newssync";
	public $dbMySQLPass 		= "";
	public $dbMySQLPort 		= 3306;
	public $dbMySQLDb 			= "newssync";

	public $authClass 			= NS_BASE."Auth\\DriverInternal";
	public $authPreferHTTP 		= false;
	public $authProvision 		= false;

	public $simplepieCache 		= BASE.".cache";


	public function __construct(string $import_file = "") {
		if($import_file != "") $this->import_file($import_file);
	}

	public function importFile(string $file): self {
		if(!file_exists($file)) throw new Conf\Exception("fileMissing");
		if(!is_readable($file)) throw new Conf\Exception("fileUnreadable");
		$json = @file_get_contents($file);
		if($json===false) throw new Conf\Exception("fileUnreadable");
		return $this->import($json);
	}

	public function import(string $json): self {
		if($json=="") throw new Conf\Exception("blank");
		$json = json_decode($json, true);
		if(!is_array($json)) throw new Conf\Exception("corrupt");
		foreach($json as $key => $value) {
			$this->$$key = $value;
		}
		return $this;

	}

	public function export(string $file = ""): string {
		return json_encode($this, JSON_PRETTY_PRINT);
	}

	public function __toString(): string {
		return $this->export();
	}
}