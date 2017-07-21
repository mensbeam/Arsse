<?php
/** Conf class */
declare(strict_types=1);
namespace JKingWeb\Arsse;

/** Class for loading, saving, and querying configuration
* 
* The Conf class serves both as a means of importing and querying configuration information, as well as a source for default parameters when a configuration file does not specify a value.
* All public properties are configuration parameters that may be set by the server administrator.
*/
class Conf {
    /** @var string Default language to use for logging and errors */
    public $lang                    = "en";

    /** @var string Class of the database driver in use (SQLite3 by default) */
    public $dbDriver                = Db\SQLite3\Driver::class;
    /** @var string Base path to database schema files */
    public $dbSchemaBase            = BASE.'sql';
    /** @var boolean Whether to attempt to automatically update the database when updated to a new version with schema changes */
    public $dbAutoUpdate            = true;
    /** @var string Full path and file name of SQLite database (if using SQLite) */
    public $dbSQLite3File           = BASE."arsse.db";
    /** @var string Encryption key to use for SQLite database (if using a version of SQLite with SEE) */
    public $dbSQLite3Key            = "";
    /** @var string Address of host name for PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLHost        = "localhost";
    /** @var string Log-in user name for PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLUser        = "arsse";
    /** @var string Log-in password for PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLPass        = "";
    /** @var integer Listening port for PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLPort        = 5432;
    /** @var string Database name on PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLDb          = "arsse";
    /** @var string Schema name on PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLSchema      = "";
    /** @var string Address of host name for MySQL/MariaDB database server (if using MySQL or MariaDB) */
    public $dbMySQLHost             = "localhost";
    /** @var string Log-in user name for MySQL/MariaDB database server (if using MySQL or MariaDB) */
    public $dbMySQLUser             = "arsse";
    /** @var string Log-in password for MySQL/MariaDB database server (if using MySQL or MariaDB) */
    public $dbMySQLPass             = "";
    /** @var integer Listening port for MySQL/MariaDB database server (if using MySQL or MariaDB) */
    public $dbMySQLPort             = 3306;
    /** @var string Database name on MySQL/MariaDB database server (if using MySQL or MariaDB) */
    public $dbMySQLDb               = "arsse";

    /** @var string Class of the user management driver in use (Internal by default) */
    public $userDriver              = User\Internal\Driver::class;
    /** @var boolean Whether users are already authenticated by the Web server before the application is executed */
    public $userPreAuth             = true;
    /** @var boolean Whether to automatically append the hostname to form a user@host combination before performing authentication
    * @deprecated */
    public $userComposeNames        = true;
    /** @var integer Desired length of temporary user passwords */
    public $userTempPasswordLength  = 20;

    /** @var string Class of the background feed update service driver in use (Forking by default) */
    public $serviceDriver           = Service\Forking\Driver::class;
    /** @var string The interval between checks for new feeds, as an ISO 8601 duration
    * @see https://en.wikipedia.org/wiki/ISO_8601#Durations
    */
    public $serviceFrequency        = "PT2M";
    /** @var integer Number of concurrent feed updates to perform */
    public $serviceQueueWidth       = 5;
    /** @var string The base server address (with scheme, host, port if necessary, and terminal slash) to connect to the server when performing feed updates using cURL */
    public $serviceCurlBase         = "http://localhost/";
    /** @var string The user name to use when performing feed updates using cURL; if none is provided, a temporary name and password will be stored in the database (this is not compatible with pre-authentication) */
    public $serviceCurlUser         = null;
    /** @var string The password to use when performing feed updates using cURL */
    public $serviceCurlPassword     = null;
    
    /** @var integer Number of seconds to wait for data when fetching feeds from foreign servers */
    public $fetchTimeout            = 10;
    /** @var integer Maximum size, in bytes, of data when fetching feeds from foreign servers */
    public $fetchSizeLimit          = 2 * 1024 * 1024;
    /** @var boolean Whether to allow the possibility of fetching full article contents using an item's URL. Whether fetching will actually happen is also governed by a per-feed setting */
    public $fetchEnableScraping     = true;
    /** @var string User-Agent string to use when fetching feeds from foreign servers */
    public $fetchUserAgentString;

    /** Creates a new configuration object
    * @param string $import_file Optional file to read configuration data from
    * @see self::importFile() 
    */
    public function __construct(string $import_file = "") {
        if($import_file != "") {
            $this->importFile($import_file);
        }
        if(is_null($this->fetchUserAgentString)) {
            $this->fetchUserAgentString = sprintf('Arsse/%s (%s %s; %s; https://code.jkingweb.ca/jking/arsse) PicoFeed (https://github.com/fguillot/picoFeed)',
                VERSION, // Arsse version
                php_uname('s'), // OS
                php_uname('r'), // OS version
                php_uname('m') // platform architecture
            );
        }
    }

    /** Layers configuration data from a file into an existing object 
    *
    * The file must be a PHP script which return an array with keys that match the properties of the Conf class. Malformed files will throw an exception; unknown keys are silently ignored. Files may be imported is succession, though this is not currently used.
    * @param string $file Full path and file name for the file to import */
    public function importFile(string $file): self {
        if(!file_exists($file)) {
            throw new Conf\Exception("fileMissing", $file);
        } else if(!is_readable($file)) {
            throw new Conf\Exception("fileUnreadable", $file);
        }
        try {
            ob_start();
            $arr = (@include $file);
        } catch(\Throwable $e) {
            $arr = null;
        } finally {
            ob_end_clean();
        }
        if(!is_array($arr)) {
            throw new Conf\Exception("fileCorrupt", $file);
        }
        return $this->import($arr);
    }

    /** Layers configuration data from an associative array into an existing object 
    *
    * The input array must have keys that match the properties of the Conf class; unknown keys are silently ignored. Arrays may be imported is succession, though this is not currently used.
    * @param mixed[] $arr Array of configuration parameters to export */
    public function import(array $arr): self {
        foreach($arr as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    /** Outputs non-default configuration settings as a string compatible with var_export()
    *
    * If provided a file name, will produce the text of a PHP script suitable for later import
    * @param string $file Full path and file name for the file to export to */
    public function export(string $file = ""): string {
        // TODO: write export method
    }

    /** Alias of export() method with no parameters
    * @see self::export() */
    public function __toString(): string {
        return $this->export();
    }
}