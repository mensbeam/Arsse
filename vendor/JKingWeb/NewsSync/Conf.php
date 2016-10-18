<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Conf {
	public $lang 					= "en";
	
	public $dbClass					= NS_BASE."Db\\DriverSQLite3";
	public $dbSQLite3File 			= BASE."newssync.db";
	public $dbSQLite3Key 			= "";
	public $dbSQLite3AutoUpd 		= true;
	public $dbPostgreSQLHost 		= "localhost";
	public $dbPostgreSQLUser 		= "newssync";
	public $dbPostgreSQLPass 		= "";
	public $dbPostgreSQLPort 		= 5432;
	public $dbPostgreSQLDb 			= "newssync";
	public $dbPostgreSQLSchema 		= "";
	public $dbPostgreSQLAutoUpd 	= false;
	public $dbMySQLHost 			= "localhost";
	public $dbMySQLUser 			= "newssync";
	public $dbMySQLPass 			= "";
	public $dbMySQLPort 			= 3306;
	public $dbMySQLDb 				= "newssync";
	public $dbMySQLAutoUpd 			= false;

	public $authClass 				= NS_BASE."Auth\\DriverInternal";
	public $authPreferHTTP 			= false;
	public $authAutoAdd 			= false;

	public $simplepieCache 			= BASE.".cache";


	public function __construct(string $import_file = "") {
		if($import_file != "") $this->importFile($import_file);
	}

	public function importFile(string $file): self {
		if(!file_exists($file)) throw new Conf\Exception("fileMissing");
		if(!is_readable($file)) throw new Conf\Exception("fileUnreadable");
		$arr = (@include $file);
		if(!is_array($arr)) throw new Conf\Exception("fileCorrupt");
		return $this->import($arr);
	}

	public function import(array $arr): self {
		foreach($arr as $key => $value) {
			$this->$$key = $value;
		}
		return $this;
	}

	public function export(string $file = ""): string {
		// TODO
	}

	public function __toString(): string {
		return $this->export();
	}
}