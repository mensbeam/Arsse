<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

/** Conf class */
declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\ValueInfo as Value;

/** Class for loading, saving, and querying configuration
 *
 * The Conf class serves both as a means of importing and querying configuration information, as well as a source for default parameters when a configuration file does not specify a value.
 * All public properties are configuration parameters that may be set by the server administrator. */
class Conf {
    /** @var string Default language to use for logging and errors */
    public $lang                    = "en";

    /** @var string The database driver to use, one of "sqlite3", "postgresql", or "mysql". A fully-qualified class name may also be used for custom drivers */
    public $dbDriver                = "sqlite3";
    /** @var boolean Whether to attempt to automatically update the database when upgrading to a new version with schema changes */
    public $dbAutoUpdate            = true;
    /** @var \DateInterval Number of seconds to wait before returning a timeout error when connecting to a database (zero waits forever; not applicable to SQLite) */
    public $dbTimeoutConnect        = 5.0;
    /** @var \DateInterval Number of seconds to wait before returning a timeout error when executing a database operation (zero waits forever; not applicable to SQLite) */
    public $dbTimeoutExec           = 0.0;
    /** @var string|null Full path and file name of SQLite database (if using SQLite) */
    public $dbSQLite3File           = null;
    /** @var string Encryption key to use for SQLite database (if using a version of SQLite with SEE) */
    public $dbSQLite3Key            = "";
    /** @var \DateInterval Number of seconds for SQLite to wait before returning a timeout error when trying to acquire a write lock on the database (zero does not wait) */
    public $dbSQLite3Timeout        = 60.0;
    /** @var string Host name, address, or socket path of PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLHost        = "";
    /** @var string Log-in user name for PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLUser        = "arsse";
    /** @var string Log-in password for PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLPass        = "";
    /** @var integer Listening port for PostgreSQL database server (if using PostgreSQL over TCP) */
    public $dbPostgreSQLPort        = 5432;
    /** @var string Database name on PostgreSQL database server (if using PostgreSQL) */
    public $dbPostgreSQLDb          = "arsse";
    /** @var string Schema name in PostgreSQL database (if using PostgreSQL) */
    public $dbPostgreSQLSchema      = "";
    /** @var string Service file entry to use (if using PostgreSQL); if using a service entry all above parameters except schema are ignored */
    public $dbPostgreSQLService     = "";
    /** @var string Host name or address of MySQL database server (if using MySQL) */
    public $dbMySQLHost             = "localhost";
    /** @var string Log-in user name for MySQL database server (if using MySQL) */
    public $dbMySQLUser             = "arsse";
    /** @var string Log-in password for MySQL database server (if using MySQL) */
    public $dbMySQLPass             = "";
    /** @var integer Listening port for MySQL database server (if using MySQL over TCP) */
    public $dbMySQLPort             = 3306;
    /** @var string Database name on MySQL database server (if using MySQL) */
    public $dbMySQLDb               = "arsse";
    /** @var string Unix domain socket or named pipe to use for MySQL when not connecting over TCP */
    public $dbMySQLSocket           = "";

    /** @var string The user management driver to use, currently only "internal". A fully-qualified class name may also be used for custom drivers */
    public $userDriver              = "internal";
    /** @var boolean Whether users are already authenticated by the Web server before the application is executed */
    public $userPreAuth             = false;
    /** @var boolean Whether to require successful HTTP authentication before processing API-level authentication for protocols which have any. Normally the Tiny Tiny RSS relies on its own session-token authentication scheme, for example */
    public $userHTTPAuthRequired    = false;
    /** @var integer Desired length of temporary user passwords */
    public $userTempPasswordLength  = 20;
    /** @var boolean Whether invalid or expired API session tokens should prevent logging in when HTTP authentication is used, for protocol which implement their own authentication */
    public $userSessionEnforced     = true;
    /** @var \DateInterval Period of inactivity after which log-in sessions should be considered invalid, as an ISO 8601 duration (default: 24 hours)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $userSessionTimeout      = "PT24H";
    /** @var \DateInterval Maximum lifetime of log-in sessions regardless of activity, as an ISO 8601 duration (default: 7 days);
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $userSessionLifetime     = "P7D";

    /** @var string Feed update service driver to use, one of "serial" or "subprocess". A fully-qualified class name may also be used for custom drivers */
    public $serviceDriver           = "subprocess";
    /** @var \DateInterval The interval between checks for new articles, as an ISO 8601 duration
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $serviceFrequency        = "PT2M";
    /** @var integer Number of concurrent feed updates to perform */
    public $serviceQueueWidth       = 5;

    /** @var \DateInterval Number of seconds to wait for data when fetching feeds from foreign servers */
    public $fetchTimeout            = 10.0;
    /** @var integer Maximum size, in bytes, of data when fetching feeds from foreign servers */
    public $fetchSizeLimit          = 2 * 1024 * 1024;
    /** @var boolean Whether to allow the possibility of fetching full article contents using an item's URL. Whether fetching will actually happen is also governed by a per-feed setting */
    public $fetchEnableScraping     = true;
    /** @var string|null User-Agent string to use when fetching feeds from foreign servers */
    public $fetchUserAgentString    = null;

    /** @var \DateInterval|null When to delete a feed from the database after all its subscriptions have been deleted, as an ISO 8601 duration (default: 24 hours; null for never)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $purgeFeeds              = "PT24H";
    /** @var \DateInterval|null When to delete an unstarred article in the database after it has been marked read by all users, as an ISO 8601 duration (default: 7 days; null for never)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $purgeArticlesRead       = "P7D";
    /** @var \DateInterval|null When to delete an unstarred article in the database regardless of its read state, as an ISO 8601 duration (default: 21 days; null for never)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $purgeArticlesUnread     = "P21D";

    /** @var string Application name to present to clients during authentication */
    public $httpRealm               = "The Advanced RSS Environment";
    /** @var string Space-separated list of origins from which to allow cross-origin resource sharing */
    public $httpOriginsAllowed      = "*";
    /** @var string Space-separated list of origins from which to deny cross-origin resource sharing */
    public $httpOriginsDenied       = "";

    const TYPE_NAMES = [
        Value::T_BOOL     => "boolean",
        Value::T_STRING   => "string",
        Value::T_FLOAT    => "float",
        VALUE::T_INT      => "integer",
        Value::T_INTERVAL => "interval",
    ];

    protected static $types = [];

    /** Creates a new configuration object
     * @param string $import_file Optional file to read configuration data from
     * @see self::importFile() */
    public function __construct(string $import_file = "") {
        if (!static::$types) {
            static::$types = $this->propertyDiscover();
        }
        foreach (array_keys(static::$types) as $prop) {
            $this->$prop = $this->propertyImport($prop, $this->$prop);
        }
        if ($import_file !== "") {
            $this->importFile($import_file);
        }
    }

    /** Layers configuration data from a file into an existing object
     *
     * The file must be a PHP script which returns an array with keys that match the properties of the Conf class. Malformed files will throw an exception; unknown keys are silently accepted. Files may be imported in succession, though this is not currently used.
     * @param string $file Full path and file name for the file to import */
    public function importFile(string $file): self {
        if (!file_exists($file)) {
            throw new Conf\Exception("fileMissing", $file);
        } elseif (!is_readable($file)) {
            throw new Conf\Exception("fileUnreadable", $file);
        }
        try {
            ob_start();
            $arr = (@include $file);
        } catch (\Throwable $e) {
            $arr = null;
        } finally {
            ob_end_clean();
        }
        if (!is_array($arr)) {
            throw new Conf\Exception("fileCorrupt", $file);
        }
        return $this->importData($arr, $file);
    }

    /** Layers configuration data from an associative array into an existing object
     *
     * The input array must have keys that match the properties of the Conf class; unknown keys are silently accepted. Arrays may be imported in succession, though this is not currently used.
     * @param mixed[] $arr Array of configuration parameters to export */
    public function import(array $arr): self {
        $file = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? "";
        return $this->importData($arr, $file);
    }

    /** Layers configuration data from an associative array into an existing object */
    protected function importData(array $arr, string $file): self {
        foreach ($arr as $key => $value) {
            $this->$key = $this->propertyImport($key, $value, $file);
        }
        return $this;
    }

    /** Outputs configuration settings, either non-default ones or all, as an associative array
     * @param bool $full Whether to output all configuration options rather than only changed ones */
    public function export(bool $full = false): array {
        $conf = new \ReflectionObject($this);
        $ref = (new \ReflectionClass($this))->getDefaultProperties();
        $out = [];
        foreach ($conf->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->name;
            $value = $prop->getValue($this);
            if ($prop->isDefault()) {
                $default = $ref[$name];
                // if the property is a known property (rather than one added by a hypothetical plug-in)
                // we convert intervals to strings and then export anything which doesn't match the default value
                $value = $this->propertyExport($name, $value);
                if ((is_scalar($value) || is_null($value)) && ($full || $value !== $ref[$name])) {
                    $out[$name] = $value;
                }
            } elseif (is_scalar($value) || is_null($value)) {
                // otherwise export the property only if it is scalar
                $out[$name] = $value;
            }
        }
        return $out;
    }

    /** Outputs configuration settings, either non-default ones or all, to a file in a format suitable for later import
     * @param string $file Full path and file name for the file to import to; the containing directory must already exist
     * @param bool $full Whether to output all configuration options rather than only changed ones */
    public function exportFile(string $file, bool $full = false): bool {
        $arr = $this->export($full);
        $conf = new \ReflectionObject($this);
        $out = "<?php return [".PHP_EOL;
        foreach ($arr as $prop => $value) {
            $match = null;
            $doc = $comment = "";
            // retrieve the property's docblock, if it exists
            try {
                $doc = (new \ReflectionProperty(self::class, $prop))->getDocComment();
                // parse the docblock to extract the property description
                if (preg_match("<@var\s+\S+\s+(.+?)(?:\s*\*/)?\s*$>m", $doc, $match)) {
                    $comment = $match[1];
                }
            } catch (\ReflectionException $e) {
            }
            // append the docblock description if there is one, or an empty comment otherwise
            $out .= " // ".$comment.PHP_EOL;
            // append the property and an export of its value to the output
            $out .= " ".var_export($prop, true)." => ".var_export($value, true).",".PHP_EOL;
        }
        $out .= "];".PHP_EOL;
        // write the configuration representation to the requested file
        if (!@file_put_contents($file, $out)) {
            // if it fails throw an exception
            $err = file_exists($file) ? "fileUnwritable" : "fileUncreatable";
            throw new Conf\Exception($err, $file);
        }
        return true;
    }

    /** Caches information about configuration properties for later access */
    protected function propertyDiscover(): array {
        $out = [];
        $rc = new \ReflectionClass($this);
        foreach ($rc->getProperties(\ReflectionProperty::IS_PUBLIC) as $p) {
            if (preg_match("/@var\s+((?:int(eger)?|float|bool(ean)?|string|\\\\DateInterval)(?:\|null)?)[^\[]/", $p->getDocComment(), $match)) {
                $match = explode("|", $match[1]);
                $nullable = (sizeof($match) > 1);
                $type = [
                    'string'         => Value::T_STRING   | Value::M_STRICT,
                    'integer'        => Value::T_INT      | Value::M_STRICT,
                    'boolean'        => Value::T_BOOL     | Value::M_STRICT,
                    'float'          => Value::T_FLOAT    | Value::M_STRICT,
                    '\\DateInterval' => Value::T_INTERVAL | Value::M_LOOSE,
                ][$match[0]];
                if ($nullable) {
                    $type |= Value::M_NULL;
                }
            } else {
                $type = Value::T_MIXED; // @codeCoverageIgnore
            }
            $out[$p->name] = ['name' => $match[0], 'const' => $type];
        }
        return $out;
    }

    protected function propertyImport(string $key, $value, string $file = "") {
        try {
            $typeName = static::$types[$key]['name'] ?? "mixed";
            $typeConst = static::$types[$key]['const'] ?? Value::T_MIXED;
            if ($typeName === "\\DateInterval") {
                // date intervals have special handling: if the existing value (ultimately, the default value)
                // is an integer or float, the new value should be imported as numeric. If the new value is a string
                // it is first converted to an interval and then converted to the numeric type if necessary
                if (is_string($value)) {
                    $value =  Value::normalize($value, Value::T_INTERVAL | Value::M_STRICT);
                }
                switch (gettype($this->$key)) {
                    case "integer":
                        return Value::normalize($value, Value::T_INT | Value::M_STRICT);
                    case "double":
                        return Value::normalize($value, Value::T_FLOAT | Value::M_STRICT);
                    case "string":
                    case "object":
                        return $value;
                    default:
                        throw new ExceptionType("strictFailure"); // @codeCoverageIgnore
                }
            }
            $value =  Value::normalize($value, $typeConst);
            switch ($key) {
                case "dbDriver":
                    $driver = $driver ?? Database::DRIVER_NAMES[strtolower($value)] ?? $value;
                    $interface = $interface ?? Db\Driver::class;
                    // no break
                case "userDriver":
                    $driver = $driver ?? User::DRIVER_NAMES[strtolower($value)] ?? $value;
                    $interface = $interface ?? User\Driver::class;
                    // no break
                case "serviceDriver":
                    $driver = $driver ?? Service::DRIVER_NAMES[strtolower($value)] ?? $value;
                    $interface = $interface ?? Service\Driver::class;
                    if (!is_subclass_of($driver, $interface)) {
                        throw new Conf\Exception("semanticMismatch", ['param' => $key, 'file' => $file]);
                    }
                    return $driver;
            }
            return $value;
        } catch (ExceptionType $e) {
            $nullable = (int) (bool) (static::$types[$key] & Value::M_NULL);
            $type =  static::$types[$key]['const'] & ~(Value::M_STRICT | Value::M_DROP | Value::M_NULL | Value::M_ARRAY);
            throw new Conf\Exception("typeMismatch", ['param' => $key, 'type' => self::TYPE_NAMES[$type], 'file' => $file, 'nullable' => $nullable]);
        }
    }

    protected function propertyExport(string $key, $value) {
        $value = ($value instanceof \DateInterval) ? Value::normalize($value, Value::T_STRING) : $value;
        switch ($key) {
            case "dbDriver":
                return array_flip(Database::DRIVER_NAMES)[$value] ?? $value;
            case "userDriver":
                return array_flip(User::DRIVER_NAMES)[$value] ?? $value;
            case "serviceDriver":
                return array_flip(Service::DRIVER_NAMES)[$value] ?? $value;
            default:
                return $value;
        }
    }
}
