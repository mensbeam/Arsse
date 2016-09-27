<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Conf {
	public $dbType 				= "SQLite3";
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

	public $simplepieCache 		= BASE.".cache";


	function __construct(string $import_file = "") {
		if($import_file != "") $this->import($import_file);
	}

	function import(string $file): bool {
		$json = @file_get_contents($file);
		if($json===false) return false;
		$json = json_decode($json, true);
		if(!is_array(json)) return false;
		foreach($json as $key => $value) {
			$this->$$key = $value;
		}
		return true;
	}

	function export(string $file = ""): string {
		return json_encode($this, JSON_PRETTY_PRINT);
	}

	function __toString(): string {
		return $this->export();
	}
}