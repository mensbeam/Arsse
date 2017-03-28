<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class Conf {
    public $lang                    = "en";

    public $dbDriver                = Db\SQLite3\Driver::class;
    public $dbSchemaBase            = BASE.'sql';
    public $dbSQLite3File           = BASE."arsse.db";
    public $dbSQLite3Key            = "";
    public $dbSQLite3AutoUpd        = true;
    public $dbPostgreSQLHost        = "localhost";
    public $dbPostgreSQLUser        = "arsse";
    public $dbPostgreSQLPass        = "";
    public $dbPostgreSQLPort        = 5432;
    public $dbPostgreSQLDb          = "arsse";
    public $dbPostgreSQLSchema      = "";
    public $dbPostgreSQLAutoUpd     = false;
    public $dbMySQLHost             = "localhost";
    public $dbMySQLUser             = "arsse";
    public $dbMySQLPass             = "";
    public $dbMySQLPort             = 3306;
    public $dbMySQLDb               = "arsse";
    public $dbMySQLAutoUpd          = false;

    public $userDriver              = User\Internal\Driver::class;
    public $userAuthPreferHTTP      = false;
    public $userComposeNames        = true;
    public $userTempPasswordLength  = 20;

    public $userAgentString         = 'Arsse (https://code.jkingweb.ca/jking/arsse)';

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