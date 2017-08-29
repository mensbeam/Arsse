<?php
/** Conf class */
declare(strict_types=1);
namespace JKingWeb\Arsse;

/** Class for loading, saving, and querying configuration
 *
 * The Conf class serves both as a means of importing and querying configuration information, as well as a source for default parameters when a configuration file does not specify a value.
 * All public properties are configuration parameters that may be set by the server administrator. */
class Conf {
    /** @var string Default language to use for logging and errors */
    public $lang                    = "en";

    /** @var string Class of the database driver in use (SQLite3 by default) */
    public $dbDriver                = Db\SQLite3\Driver::class;
    /** @var boolean Whether to attempt to automatically update the database when updated to a new version with schema changes */
    public $dbAutoUpdate            = true;
    /** @var string|null Full path and file name of SQLite database (if using SQLite) */
    public $dbSQLite3File           = null;
    /** @var string Encryption key to use for SQLite database (if using a version of SQLite with SEE) */
    public $dbSQLite3Key            = "";
    /** @var integer Number of seconds for SQLite to wait before returning a timeout error when writing to the database */
    public $dbSQLite3Timeout        = 5;

    /** @var string Class of the user management driver in use (Internal by default) */
    public $userDriver              = User\Internal\Driver::class;
    /** @var boolean Whether users are already authenticated by the Web server before the application is executed */
    public $userPreAuth             = false;
    /** @var integer Desired length of temporary user passwords */
    public $userTempPasswordLength  = 20;

    /** @var string Class of the background feed update service driver in use (Forking by default) */
    public $serviceDriver           = Service\Forking\Driver::class;
    /** @var string The interval between checks for new feeds, as an ISO 8601 duration
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
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
    /** @var string|null User-Agent string to use when fetching feeds from foreign servers */
    public $fetchUserAgentString;

    /** @var string When to delete a feed from the database after all its subscriptions have been deleted, as an ISO 8601 duration (default: 24 hours; empty string for never)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $purgeFeeds             = "PT24H";
    /** @var string When to delete an unstarred article in the database after it has been marked read by all users, as an ISO 8601 duration (default: 7 days; empty string for never)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $purgeArticlesRead     = "P7D";
    /** @var string When to delete an unstarred article in the database regardless of its read state, as an ISO 8601 duration (default: 21 days; empty string for never)
     * @see https://en.wikipedia.org/wiki/ISO_8601#Durations */
    public $purgeArticlesUnread     = "P21D";

    /** Creates a new configuration object
     * @param string $import_file Optional file to read configuration data from
     * @see self::importFile() */
    public function __construct(string $import_file = "") {
        if ($import_file != "") {
            $this->importFile($import_file);
        }
    }

    /** Layers configuration data from a file into an existing object
     *
     * The file must be a PHP script which return an array with keys that match the properties of the Conf class. Malformed files will throw an exception; unknown keys are silently ignored. Files may be imported is succession, though this is not currently used.
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
        return $this->import($arr);
    }

    /** Layers configuration data from an associative array into an existing object
     *
     * The input array must have keys that match the properties of the Conf class; unknown keys are silently ignored. Arrays may be imported is succession, though this is not currently used.
     * @param mixed[] $arr Array of configuration parameters to export */
    public function import(array $arr): self {
        foreach ($arr as $key => $value) {
            $this->$key = $value;
        }
        return $this;
    }

    /** Outputs configuration settings, either non-default ones or all, as an associative array
     * @param bool $full Whether to output all configuration options rather than only changed ones */
    public function export(bool $full = false): array {
        $ref = new self;
        $out = [];
        $conf = new \ReflectionObject($this);
        foreach ($conf->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->name;
            // add the property to the output if the value is scalar and either:
            // 1. full output has been requested
            // 2. the property is not defined in the class
            // 3. it differs from the default
            if (is_scalar($this->$name) && ($full || !$prop->isDefault() || $this->$name !== $ref->$name)) {
                $out[$name] = $this->$name;
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
            } catch (\ReflectionException $e) {
            }
            if ($doc) {
                // parse the docblock to extract the property description
                if (preg_match("<@var\s+\S+\s+(.+?)(?:\s*\*/)?$>m", $doc, $match)) {
                    $comment = $match[1];
                }
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
}
