<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Conf {
	public $lang 					= "en";
	
	public $dbDriver				= Db\DriverSQLite3::class;
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

	public $userDriver 				= User\DriverInternal::class;
	public $userAuthPreferHTTP 		= false;
	public $userComposeNames 		= true;

	public $simplepieCache 			= BASE.".cache";


	public function __construct(string $import_file = "") {
		if($import_file != "") $this->importFile($import_file);
	}

	public function importFile(string $file): self {
		if(!file_exists($file)) throw new Conf\Exception("fileMissing", $file);
		if(!is_readable($file)) throw new Conf\Exception("fileUnreadable", $file);
		try {
			ob_start();
			$arr = (@include $file);
		} catch(\Throwable $e) {
			$arr = null;
		} finally {
			ob_end_clean();
		}
		if(!is_array($arr)) throw new Conf\Exception("fileCorrupt", $file);
		return $this->import($arr);
	}

	public function import(array $arr): self {
		foreach($arr as $key => $value) {
			$this->$key = $value;
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