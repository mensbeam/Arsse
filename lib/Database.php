<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\DrUUID\UUID;
use JKingWeb\Arsse\Db\Statement;
use JKingWeb\Arsse\Misc\Query;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\Misc\URL;

/** The high-level interface with the database
 *
 * The database stores information on the following things:
 *
 * - Users
 * - Subscriptions to feeds, which belong to users
 * - Folders, which belong to users and contain subscriptions
 * - Tags, which belong to users and can be assigned to multiple subscriptions
 * - Feeds to which users are subscribed
 * - Articles, which belong to feeds and for which users can only affect metadata
 * - Editions, identifying authorial modifications to articles
 * - Labels, which belong to users and can be assigned to multiple articles
 * - Sessions, used by some protocols to identify users across periods of time
 * - Tokens, similar to sessions, but with more control over their properties
 * - Metadata, used internally by the server
 *
 * The various methods of this class perform operations on these things, with
 * each public method prefixed with the thing it concerns e.g. userRemove()
 * deletes a user from the database, and labelArticlesSet() changes a label's
 * associations with articles. There has been an effort to keep public method
 * names consistent throughout, but protected methods, having different
 * concerns, will typicsally follow different conventions.
 */
class Database {
    /** The version number of the latest schema the interface is aware of */
    const SCHEMA_VERSION = 5;
    /** The size of a set of values beyond which the set will be embedded into the query text */
    const LIMIT_SET_SIZE = 25;
    /** The length of a string in an embedded set beyond which a parameter placeholder will be used for the string */
    const LIMIT_SET_STRING_LENGTH = 200;
    /** Makes tag/label association change operations remove members */
    const ASSOC_REMOVE = 0;
    /** Makes tag/label association change operations add members */
    const ASSOC_ADD = 1;
    /** Makes tag/label association change operations replace members */
    const ASSOC_REPLACE = 2;
    /** A map database driver short-names and their associated class names */
    const DRIVER_NAMES = [
        'sqlite3'    => \JKingWeb\Arsse\Db\SQLite3\Driver::class,
        'postgresql' => \JKingWeb\Arsse\Db\PostgreSQL\Driver::class,
        'mysql'      => \JKingWeb\Arsse\Db\MySQL\Driver::class,
    ];

    /** @var Db\Driver */
    public $db;

    /** Constructs the database interface
     *
     * @param boolean $initialize Whether to attempt to upgrade the databse schema when constructing
     */
    public function __construct($initialize = true) {
        $driver = Arsse::$conf->dbDriver;
        $this->db = $driver::create();
        $ver = $this->db->schemaVersion();
        if ($initialize && $ver < self::SCHEMA_VERSION) {
            $this->db->schemaUpdate(self::SCHEMA_VERSION);
        }
    }

    /** Returns the bare name of the calling context's calling method, when __FUNCTION__ is not appropriate */
    protected function caller(): string {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];
    }

    /** Lists the available database drivers, as an associative array with
     * fully-qualified class names as keys, and human-readable descriptions as values
     */
    public static function driverList(): array {
        $sep = \DIRECTORY_SEPARATOR;
        $path = __DIR__.$sep."Db".$sep;
        $classes = [];
        foreach (glob($path."*".$sep."Driver.php") as $file) {
            $name = basename(dirname($file));
            $class = NS_BASE."Db\\$name\\Driver";
            $classes[$class] = $class::driverName();
        }
        return $classes;
    }

    /** Returns the current (actual) schema version of the database; compared against self::SCHEMA_VERSION to know when an upgrade is required */
    public function driverSchemaVersion(): int {
        return $this->db->schemaVersion();
    }

    /** Attempts to update the database schema. If it is already up to date, false is returned */
    public function driverSchemaUpdate(): bool {
        if ($this->db->schemaVersion() < self::SCHEMA_VERSION) {
            return $this->db->schemaUpdate(self::SCHEMA_VERSION);
        }
        return false;
    }

    /** Returns whether the database's character set is Unicode */
    public function driverCharsetAcceptable(): bool {
        return $this->db->charsetAcceptable();
    }

    /** Performs maintenance on the database to ensure good performance */
    public function driverMaintenance(): bool {
        return $this->db->maintenance();
    }

    /** Computes the column and value text of an SQL "SET" clause, validating arbitrary input against a whitelist
     *
     * Returns an indexed array containing the clause text, an array of types, and another array of values
     *
     * @param array $props An associative array containing untrusted data; keys are column names
     * @param array $valid An associative array containing a whitelist: keys are column names, and values are strings representing data types
     */
    protected function generateSet(array $props, array $valid): array {
        $out = [
            [], // query clause
            [], // binding types
            [], // binding values
        ];
        foreach ($valid as $prop => $type) {
            if (!array_key_exists($prop, $props)) {
                continue;
            }
            $out[0][] = "\"$prop\" = ?";
            $out[1][] = $type;
            $out[2][] = $props[$prop];
        }
        $out[0] = implode(", ", $out[0]);
        return $out;
    }

    /** Computes the contents of an SQL "IN()" clause, for each input value either embedding the value or producing a parameter placeholder
     *
     * Returns an indexed array containing the clause text, an array of types, and an array of values. Note that the array of output values may not match the array of input values
     *
     * @param array $values Arbitrary values
     * @param string $type A single data type applied to each value
     */
    protected function generateIn(array $values, string $type): array {
        if (!sizeof($values)) {
            // if the set is empty, some databases require an explicit null
            return ["null", [], []];
        }
        $t = (Statement::TYPES[$type] ?? 0) % Statement::T_NOT_NULL;
        if (sizeof($values) > self::LIMIT_SET_SIZE && ($t == Statement::T_INTEGER || $t == Statement::T_STRING)) {
            $clause = [];
            $params = [];
            $count = 0;
            $convType = Db\AbstractStatement::TYPE_NORM_MAP[Statement::TYPES[$type]];
            foreach ($values as $v) {
                $v = ValueInfo::normalize($v, $convType, null, "sql");
                if (is_null($v)) {
                    // nulls are pointless to have
                    continue;
                } elseif (is_string($v)) {
                    if (strlen($v) > self::LIMIT_SET_STRING_LENGTH) {
                        $clause[] = "?";
                        $params[] = $v;
                    } else {
                        $clause[] = $this->db->literalString($v);
                    }
                } else {
                    $clause[] = ValueInfo::normalize($v, ValueInfo::T_STRING, null, "sql");
                }
                $count++;
            }
            if (!$count) {
                // the set is actually empty
                return ["null", [], []];
            } else {
                return [implode(",", $clause), array_fill(0, sizeof($params), $type), $params];
            }
        } else {
            return [implode(",", array_fill(0, sizeof($values), "?")), array_fill(0, sizeof($values), $type), $values];
        }
    }

    /** Computes basic LIKE-based text search constraints for use in a WHERE clause
     *
     * Returns an indexed array containing the clause text, an array of types, and another array of values
     *
     * The clause is structured such that all terms must be present across any of the columns
     *
     * @param string[] $terms The terms to search for
     * @param string[] $cols The columns to match against; these are -not- sanitized, so much -not- come directly from user input
     * @param boolean $matchAny Whether the search is successful when it matches any (true) or all (false) terms
     */
    protected function generateSearch(array $terms, array $cols, bool $matchAny = false): array {
        $clause = [];
        $types = [];
        $values = [];
        $like = $this->db->sqlToken("like");
        assert(sizeof($cols) > 0, new Exception("arrayEmpty", "cols"));
        $embedSet = sizeof($terms) > ((int) (self::LIMIT_SET_SIZE / sizeof($cols)));
        foreach ($terms as $term) {
            $embedTerm = ($embedSet && strlen($term) <= self::LIMIT_SET_STRING_LENGTH);
            $term = str_replace(["%", "_", "^"], ["^%", "^_", "^^"], $term);
            $term = "%$term%";
            $term = $embedTerm ? $this->db->literalString($term) : $term;
            $spec = [];
            foreach ($cols as $col) {
                if ($embedTerm) {
                    $spec[] = "$col $like $term escape '^'";
                } else {
                    $spec[] = "$col $like ? escape '^'";
                    $types[] = "str";
                    $values[] = $term;
                }
            }
            $spec = sizeof($spec) > 1 ? "(".implode(" or ", $spec).")" : (string) array_pop($spec);
            $clause[] = $spec;
        }
        $glue = $matchAny ? "or" : "and";
        $clause = sizeof($clause) > 1 ? "(".implode(" $glue ", $clause).")" : (string) array_pop($clause);
        return [$clause, $types, $values];
    }

    /** Returns a Transaction object, which is rolled back unless explicitly committed */
    public function begin(): Db\Transaction {
        return $this->db->begin();
    }

    /** Retrieve a value from the metadata table. If the key is not set null is returned */
    public function metaGet(string $key) {
        return $this->db->prepare("SELECT value from arsse_meta where \"key\" = ?", "str")->run($key)->getValue();
    }

    /** Sets the given key in the metadata table to the given value. If the key already exists it is silently overwritten */
    public function metaSet(string $key, $value, string $type = "str"): bool {
        $out = $this->db->prepare("UPDATE arsse_meta set value = ? where \"key\" = ?", $type, "str")->run($value, $key)->changes();
        if (!$out) {
            $out = $this->db->prepare("INSERT INTO arsse_meta(\"key\",value) values(?,?)", "str", $type)->run($key, $value)->changes();
        }
        return (bool) $out;
    }

    /** Unsets the given key in the metadata table. Returns false if the key does not exist */
    public function metaRemove(string $key): bool {
        return (bool) $this->db->prepare("DELETE from arsse_meta where \"key\" = ?", "str")->run($key)->changes();
    }

    /** Returns whether the specified user exists in the database */
    public function userExists(string $user): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return (bool) $this->db->prepare("SELECT count(*) from arsse_users where id = ?", "str")->run($user)->getValue();
    }

    /** Adds a user to the database
     *
     * @param string $user The user to add
     * @param string $passwordThe user's password in cleartext. It will be stored hashed
     */
    public function userAdd(string $user, string $password): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif ($this->userExists($user)) {
            throw new User\Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        }
        $hash = (strlen($password) > 0) ? password_hash($password, \PASSWORD_DEFAULT) : "";
        $this->db->prepare("INSERT INTO arsse_users(id,password) values(?,?)", "str", "str")->runArray([$user,$hash]);
        return true;
    }

    /** Removes a user from the database */
    public function userRemove(string $user): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if ($this->db->prepare("DELETE from arsse_users where id = ?", "str")->run($user)->changes() < 1) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return true;
    }

    /** Returns a flat, indexed array of all users in the database */
    public function userList(): array {
        $out = [];
        if (!Arsse::$user->authorize("", __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => ""]);
        }
        foreach ($this->db->query("SELECT id from arsse_users") as $user) {
            $out[] = $user['id'];
        }
        return $out;
    }

    /** Retrieves the hashed password of a user */
    public function userPasswordGet(string $user) {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare("SELECT password from arsse_users where id = ?", "str")->run($user)->getValue();
    }

    /** Sets the password of an existing user
     *
     * @param string $user The user for whom to set the password
     * @param string $password The new password, in cleartext. The password will be stored hashed. If null is passed, the password is unset and authentication not possible
     */
    public function userPasswordSet(string $user, string $password = null): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $hash = (strlen($password ?? "") > 0) ? password_hash($password, \PASSWORD_DEFAULT) : $password;
        $this->db->prepare("UPDATE arsse_users set password = ? where id = ?", "str", "str")->run($hash, $user);
        return true;
    }

    /** Creates a new session for the given user and returns the session identifier */
    public function sessionCreate(string $user): string {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // generate a new session ID and expiry date
        $id = UUID::mint()->hex;
        $expires = Date::add(Arsse::$conf->userSessionTimeout);
        // save the session to the database
        $this->db->prepare("INSERT INTO arsse_sessions(id,expires,\"user\") values(?,?,?)", "str", "datetime", "str")->run($id, $expires, $user);
        // return the ID
        return $id;
    }

    /** Explicitly removes a session from the database
     *
     * Sessions may also be invalidated as they expire, and then be automatically pruned.
     * This function can be used to explicitly invalidate a session after a user logs out
     *
     * @param string $user The user who owns the session to be destroyed
     * @param string|null $id The identifier of the session to destroy
     */
    public function sessionDestroy(string $user, string $id = null): bool {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (is_null($id)) {
            // delete all sessions and report success unconditionally if no identifier was specified
            $this->db->prepare("DELETE FROM arsse_sessions where \"user\" = ?", "str")->run($user);
            return true;
        } else {
            // otherwise delete only the specified session and report success.
            return (bool) $this->db->prepare("DELETE FROM arsse_sessions where id = ? and \"user\" = ?", "str", "str")->run($id, $user)->changes();
        }
    }

    /** Resumes a session, returning available session data
     *
     * This also has the side effect of refreshing the session if it is near its timeout
     */
    public function sessionResume(string $id): array {
        $maxAge = Date::sub(Arsse::$conf->userSessionLifetime);
        $out = $this->db->prepare("SELECT id,created,expires,\"user\" from arsse_sessions where id = ? and expires > CURRENT_TIMESTAMP and created > ?", "str", "datetime")->run($id, $maxAge)->getRow();
        // if the session does not exist or is expired, throw an exception
        if (!$out) {
            throw new User\ExceptionSession("invalid", $id);
        }
        // if we're more than half-way from the session expiring, renew it
        if ($this->sessionExpiringSoon(Date::normalize($out['expires'], "sql"))) {
            $expires = Date::add(Arsse::$conf->userSessionTimeout);
            $this->db->prepare("UPDATE arsse_sessions set expires = ? where id = ?", "datetime", "str")->run($expires, $id);
        }
        return $out;
    }

    /** Deletes expires sessions from the database, returning the number of deleted sessions */
    public function sessionCleanup(): int {
        $maxAge = Date::sub(Arsse::$conf->userSessionLifetime);
        return $this->db->prepare("DELETE FROM arsse_sessions where expires < CURRENT_TIMESTAMP or created < ?", "datetime")->run($maxAge)->changes();
    }

    /** Checks if a given future timeout is less than half the session timeout interval */
    protected function sessionExpiringSoon(\DateTimeInterface $expiry): bool {
        // calculate half the session timeout as a number of seconds
        $now = time();
        $max = Date::add(Arsse::$conf->userSessionTimeout, $now)->getTimestamp();
        $diff = intdiv($max - $now, 2);
        // determine if the expiry time is less than half the session timeout into the future
        return (($now + $diff) >= $expiry->getTimestamp());
    }

    /** Creates a new token for the given user in the given class
     *
     * @param string $user The user for whom to create the token
     * @param string $class The class of the token e.g. the protocol name
     * @param string|null $id The value of the token; if none is provided a UUID will be generated
     * @param \DateTimeInterface|null $expires An optional expiry date and time for the token
    */
    public function tokenCreate(string $user, string $class, string $id = null, \DateTimeInterface $expires = null): string {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        // generate a token if it's not provided
        $id = $id ?? UUID::mint()->hex;
        // save the token to the database
        $this->db->prepare("INSERT INTO arsse_tokens(id,class,\"user\",expires) values(?,?,?,?)", "str", "str", "str", "datetime")->run($id, $class, $user, $expires);
        // return the ID
        return $id;
    }

    /** Revokes one or all tokens for a user in a class
     *
     * @param string $user The user who owns the token to be revoked
     * @param string $class The class of the token e.g. the protocol name
     * @param string|null $id The ID of a specific token, or null for all tokens in the class
     */
    public function tokenRevoke(string $user, string $class, string $id = null): bool {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (is_null($id)) {
            $out = $this->db->prepare("DELETE FROM arsse_tokens where \"user\" = ? and class = ?", "str", "str")->run($user, $class)->changes();
        } else {
            $out = $this->db->prepare("DELETE FROM arsse_tokens where \"user\" = ? and class = ? and id = ?", "str", "str", "str")->run($user, $class, $id)->changes();
        }
        return (bool) $out;
    }

    /** Look up data associated with a token */
    public function tokenLookup(string $class, string $id): array {
        $out = $this->db->prepare("SELECT id,class,\"user\",created,expires from arsse_tokens where class = ? and id = ? and (expires is null or expires > CURRENT_TIMESTAMP)", "str", "str")->run($class, $id)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "token", 'id' => $id]);
        }
        return $out;
    }

    /** Deletes expires tokens from the database, returning the number of deleted tokens */
    public function tokenCleanup(): int {
        return $this->db->query("DELETE FROM arsse_tokens where expires < CURRENT_TIMESTAMP")->changes();
    }

    /** Adds a folder for containing newsfeed subscriptions, returning an integer identifying the created folder
     *
     * The $data array may contain the following keys:
     *
     * - "name": A folder name, which must be a non-empty string not composed solely of whitespace; this key is required
     * - "parent": An integer (or null) identifying a parent folder; this key is optional
     *
     * If a folder with the same name and parent already exists, this is an error
     *
     * @param string $user The user who will own the folder
     * @param array $data An associative array defining the folder
     */
    public function folderAdd(string $user, array $data): int {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // normalize folder's parent, if there is one
        $parent = array_key_exists("parent", $data) ? $this->folderValidateId($user, $data['parent'])['id'] : null;
        // validate the folder name and parent (if specified); this also checks for duplicates
        $name = array_key_exists("name", $data) ? $data['name'] : "";
        $this->folderValidateName($name, true, $parent);
        // actually perform the insert
        return $this->db->prepare("INSERT INTO arsse_folders(owner,parent,name) values(?,?,?)", "str", "int", "str")->run($user, $parent, $name)->lastId();
    }

    /** Returns a result set listing a user's folders
     *
     * Each record in the result set contains:
     *
     * - "id":       The folder identifier, an integer
     * - "name":     The folder's name, a string
     * - "parent":   The integer identifier of the folder's parent, or null
     * - "children": The number of child folders contained in the given folder
     * - "feeds":    The number of newsfeed subscriptions contained in the given folder, not including subscriptions in descendent folders
     *
     * @param string $uer The user whose folders are to be listed
     * @param integer|null $parent Restricts the list to the descendents of the specified folder identifier
     * @param boolean $recursive Whether to list all descendents (true) or only direct children (false)
     */
    public function folderList(string $user, $parent = null, bool $recursive = true): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // check to make sure the parent exists, if one is specified
        $parent = $this->folderValidateId($user, $parent)['id'];
        $q = new Query(
            "SELECT
                id,
                name,
                arsse_folders.parent as parent,
                coalesce(children,0) as children, 
                coalesce(feeds,0) as feeds
            FROM arsse_folders
            left join (SELECT parent,count(id) as children from arsse_folders group by parent) as child_stats on child_stats.parent = arsse_folders.id
            left join (SELECT folder,count(id) as feeds from arsse_subscriptions group by folder) as sub_stats on sub_stats.folder = arsse_folders.id"
        );
        if (!$recursive) {
            $q->setWhere("owner = ?", "str", $user);
            $q->setWhere("coalesce(arsse_folders.parent,0) = ?", "strict int", $parent);
        } else {
            $q->setCTE("folders", "SELECT id from arsse_folders where owner = ? and coalesce(parent,0) = ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id", ["str", "strict int"], [$user, $parent]);
            $q->setWhere("id in (SELECT id from folders)");
        }
        $q->setOrder("name");
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    /** Deletes a folder from the database
     *
     * Any descendent folders are also deleted, as are all newsfeed subscriptions contained in the deleted folder tree
     *
     * @param string $user The user to whom the folder to be deleted belongs
     * @param integer $id The identifier of the folder to delete
     */
    public function folderRemove(string $user, $id): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "folder", 'type' => "int > 0"]);
        }
        $changes = $this->db->prepare("WITH RECURSIVE folders(folder) as (SELECT ? union select id from arsse_folders join folders on parent = folder) DELETE FROM arsse_folders where owner = ? and id in (select folder from folders)", "int", "str")->run($id, $user)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        }
        return true;
    }

    /** Returns the identifier, name, and parent of the given folder as an associative array */
    public function folderPropertiesGet(string $user, $id): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "folder", 'type' => "int > 0"]);
        }
        $props = $this->db->prepare("SELECT id,name,parent from arsse_folders where owner = ? and id = ?", "str", "int")->run($user, $id)->getRow();
        if (!$props) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        }
        return $props;
    }

    /** Modifies the properties of a folder
     *
     * The $data array must contain one or more of the following keys:
     *
     * - "name":   A new folder name, which must be a non-empty string not composed solely of whitespace
     * - "parent": An integer (or null) identifying a parent folder
     *
     * If a folder with the new name and parent combination already exists, this is an error; it is also an error to move a folder to itself or one of its descendents
     *
     * @param string $user The user who owns the folder to be modified
     * @param integer $id The identifier of the folder to be modified
     * @param array $data An associative array of properties to modify. Anything not specified will remain unchanged
     */
    public function folderPropertiesSet(string $user, $id, array $data): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // verify the folder belongs to the user
        $in = $this->folderValidateId($user, $id, true);
        $name = array_key_exists("name", $data);
        $parent = array_key_exists("parent", $data);
        if ($name && $parent) {
            // if a new name and parent are specified, validate both together
            $this->folderValidateName($data['name']);
            $in['name'] = $data['name'];
            $in['parent'] = $this->folderValidateMove($user, (int) $id, $data['parent'], $data['name']);
        } elseif ($name) {
            // if we're trying to rename the root folder, this simply fails
            if (!$id) {
                return false;
            }
            // if a new name is specified, validate it
            $this->folderValidateName($data['name'], true, $in['parent']);
            $in['name'] = $data['name'];
        } elseif ($parent) {
            // if a new parent is specified, validate it
            $in['parent'] = $this->folderValidateMove($user, (int) $id, $data['parent']);
        } else {
            // if no changes would actually be applied, just return
            return false;
        }
        $valid = [
            'name' => "str",
            'parent' => "int",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($in, $valid);
        return (bool) $this->db->prepare("UPDATE arsse_folders set $setClause, modified = CURRENT_TIMESTAMP where owner = ? and id = ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
    }

    /** Ensures the specified folder exists and raises an exception otherwise
     *
     * Returns an associative array containing the id, name, and parent of the folder if it exists
     *
     * @param string $user The user who owns the folder to be validated
     * @param integer|null $id The identifier of the folder to validate; null or zero represent the implied root folder
     * @param boolean $subject Whether the folder is the subject (true) rather than the object (false) of the operation being performed; this only affects the semantics of the error message if validation fails
     */
    protected function folderValidateId(string $user, $id = null, bool $subject = false): array {
        // if the specified ID is not a non-negative integer (or null), this will always fail
        if (!ValueInfo::id($id, true)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "folder", 'type' => "int >= 0"]);
        }
        // if a null or zero ID is specified this is a no-op
        if (!$id) {
            return ['id' => null, 'name' => null, 'parent' => null];
        }
        // check whether the folder exists and is owned by the user
        $f = $this->db->prepare("SELECT id,name,parent from arsse_folders where owner = ? and id = ?", "str", "int")->run($user, $id)->getRow();
        if (!$f) {
            throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "folder", 'id' => $id]);
        }
        return $f;
    }

    /** Ensures an operation to rename and/or move a folder does not result in a conflict or circular dependence, and raises an exception otherwise */
    protected function folderValidateMove(string $user, $id = null, $parent = null, string $name = null) {
        $errData = ["action" => $this->caller(), "field" => "parent", 'id' => $parent];
        if (!$id) {
            // the root cannot be moved
            throw new Db\ExceptionInput("circularDependence", $errData);
        }
        $info = ValueInfo::int($parent);
        // the root is always a valid parent
        if ($info & (ValueInfo::NULL | ValueInfo::ZERO)) {
            $parent = null;
        } else {
            // if a negative integer or non-integer is specified this will always fail
            if (!($info & ValueInfo::VALID) || (($info & ValueInfo::NEG))) {
                throw new Db\ExceptionInput("idMissing", $errData);
            }
            $parent = (int) $parent;
        }
        // if the target parent is the folder itself, this is a circular dependence
        if ($id == $parent) {
            throw new Db\ExceptionInput("circularDependence", $errData);
        }
        // make sure both that the prospective parent exists, and that the it is not one of its children (a circular dependence);
        // also make sure that a folder with the same prospective name and parent does not already exist: if the parent is null,
        // SQL will happily accept duplicates (null is not unique), so we must do this check ourselves
        $p = $this->db->prepare(
            "WITH RECURSIVE
                target as (select ? as userid, ? as source, ? as dest, ? as new_name),
                folders as (SELECT id from arsse_folders join target on owner = userid and coalesce(parent,0) = source union select arsse_folders.id as id from arsse_folders join folders on arsse_folders.parent=folders.id)
            ".
            "SELECT
                case when ((select dest from target) is null or exists(select id from arsse_folders join target on owner = userid and coalesce(id,0) = coalesce(dest,0))) then 1 else 0 end as extant,
                case when not exists(select id from folders where id = coalesce((select dest from target),0)) then 1 else 0 end as valid,
                case when not exists(select id from arsse_folders join target on coalesce(parent,0) = coalesce(dest,0) and name = coalesce((select new_name from target),(select name from arsse_folders join target on id = source))) then 1 else 0 end as available
            ",
            "str",
            "strict int",
            "int",
            "str"
        )->run($user, $id, $parent, $name)->getRow();
        if (!$p['extant']) {
            // if the parent doesn't exist or doesn't below to the user, throw an exception
            throw new Db\ExceptionInput("idMissing", $errData);
        } elseif (!$p['valid']) {
            // if using the desired parent would create a circular dependence, throw a different exception
            throw new Db\ExceptionInput("circularDependence", $errData);
        } elseif (!$p['available']) {
            // if a folder with the same parent and name already exists, throw another different exception
            throw new Db\ExceptionInput("constraintViolation", ["action" => $this->caller(), "field" => (is_null($name) ? "parent" : "name")]);
        }
        return $parent;
    }

    /** Ensures a prospective folder name is valid, and optionally ensure it is not a duplicate if renamed
     *
     * @param string $name The name to check
     * @param boolean $checkDuplicates Whether to also check if the new name would cause a collision
     * @param integer|null $parent The parent folder context in which to check for duplication
     */
    protected function folderValidateName($name, bool $checkDuplicates = false, $parent = null): bool {
        $info = ValueInfo::str($name);
        if ($info & (ValueInfo::NULL | ValueInfo::EMPTY)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } elseif ($info & ValueInfo::WHITE) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } elseif (!($info & ValueInfo::VALID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "name", 'type' => "string"]);
        } elseif ($checkDuplicates) {
            // make sure that a folder with the same prospective name and parent does not already exist: if the parent is null,
            // SQL will happily accept duplicates (null is not unique), so we must do this check ourselves
            $parent = $parent ? $parent : null;
            if ($this->db->prepare("SELECT count(*) from arsse_folders where coalesce(parent,0) = ? and name = ?", "strict int", "str")->run($parent, $name)->getValue()) {
                throw new Db\ExceptionInput("constraintViolation", ["action" => $this->caller(), "field" => "name"]);
            }
            return true;
        } else {
            return true;
        }
    }

    /** Adds a subscription to a newsfeed, and returns the numeric identifier of the added subscription
     *
     * @param string $user The user which will own the subscription
     * @param string $url The URL of the newsfeed or discovery source
     * @param string $fetchUser The user name required to access the newsfeed, if applicable
     * @param string $fetchPassword The password required to fetch the newsfeed, if applicable; this will be stored in cleartext
     * @param boolean $discover Whether to perform newsfeed discovery if $url points to an HTML document
     */
    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = "", bool $discover = true): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // get the ID of the underlying feed, or add it if it's not yet in the database
        $feedID = $this->feedAdd($url, $fetchUser, $fetchPassword, $discover);
        // Add the feed to the user's subscriptions and return the new subscription's ID.
        return $this->db->prepare('INSERT INTO arsse_subscriptions(owner,feed) values(?,?)', 'str', 'int')->run($user, $feedID)->lastId();
    }

    /** Lists a user's subscriptions, returning various data
     *
     * @param string $user The user whose subscriptions are to be listed
     * @param integer|null $folder The identifier of the folder under which to list subscriptions; by default the root folder is used
     * @param boolean $recursive Whether to list subscriptions of descendent folders as well as the selected folder
     * @param integer|null $id The numeric identifier of a particular subscription; used internally by subscriptionPropertiesGet
     */
    public function subscriptionList(string $user, $folder = null, bool $recursive = true, int $id = null): Db\Result {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate inputs
        $folder = $this->folderValidateId($user, $folder)['id'];
        // create a complex query
        $q = new Query(
            "SELECT
                arsse_subscriptions.id as id,
                arsse_subscriptions.feed as feed,
                url,favicon,source,folder,pinned,err_count,err_msg,order_type,added,
                arsse_feeds.updated as updated,
                arsse_feeds.modified as edited,
                arsse_subscriptions.modified as modified,
                topmost.top as top_folder,
                coalesce(arsse_subscriptions.title, arsse_feeds.title) as title,
                (articles - marked) as unread
            FROM arsse_subscriptions
                left join topmost on topmost.f_id = arsse_subscriptions.folder
                join arsse_feeds on arsse_feeds.id = arsse_subscriptions.feed
                left join (select feed, count(*) as articles from arsse_articles group by feed) as article_stats on article_stats.feed = arsse_subscriptions.feed
                left join (select subscription, sum(\"read\") as marked from arsse_marks group by subscription) as mark_stats on mark_stats.subscription = arsse_subscriptions.id"
        );
        $q->setWhere("arsse_subscriptions.owner = ?", ["str"], [$user]);
        $nocase = $this->db->sqlToken("nocase");
        $q->setOrder("pinned desc, coalesce(arsse_subscriptions.title, arsse_feeds.title) collate $nocase");
        // topmost folders belonging to the user
        $q->setCTE("topmost(f_id,top)", "SELECT id,id from arsse_folders where owner = ? and parent is null union select id,top from arsse_folders join topmost on parent=f_id", ["str"], [$user]);
        if ($id) {
            // this condition facilitates the implementation of subscriptionPropertiesGet, which would otherwise have to duplicate the complex query; it takes precedence over a specified folder
            // if an ID is specified, add a suitable WHERE condition and bindings
            $q->setWhere("arsse_subscriptions.id = ?", "int", $id);
        } elseif ($folder && $recursive) {
            // if a folder is specified and we're listing recursively, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on parent = folder", "int", $folder);
            // add a suitable WHERE condition
            $q->setWhere("folder in (select folder from folders)");
        } elseif (!$recursive) {
            // if we're not listing recursively, match against only the specified folder (even if it is null)
            $q->setWhere("coalesce(folder,0) = ?", "strict int", $folder);
        }
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    /** Returns the number of subscriptions in a folder, counting recursively */
    public function subscriptionCount(string $user, $folder = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate inputs
        $folder = $this->folderValidateId($user, $folder)['id'];
        // create a complex query
        $q = new Query("SELECT count(*) from arsse_subscriptions");
        $q->setWhere("owner = ?", "str", $user);
        if ($folder) {
            // if the specified folder exists, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on parent = folder", "int", $folder);
            // add a suitable WHERE condition
            $q->setWhere("folder in (select folder from folders)");
        }
        return (int) $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

    /** Deletes a subscription from the database
     *
     * This has the side effect of deleting all marks the user has set on articles
     * belonging to the newsfeed, but may not delete the articles themselves, as
     * other users may also be subscribed to the same newsfeed. There is also a
     * configurable retention period for newsfeeds
     */
    public function subscriptionRemove(string $user, $id): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'type' => "int > 0"]);
        }
        $changes = $this->db->prepare("DELETE from arsse_subscriptions where owner = ? and id = ?", "str", "int")->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return true;
    }

    /** Retrieves data about a particular subscription, as an associative array with the following keys:
     *
     * - "id": The numeric identifier of the subscription
     * - "feed": The numeric identifier of the underlying newsfeed
     * - "url": The URL of the newsfeed, after discovery and HTTP redirects
     * - "title": The title of the newsfeed
     * - "favicon": The URL of an icon representing the newsfeed or its source
     * - "source": The URL of the source of the newsfeed i.e. its parent Web site
     * - "folder": The numeric identifier (or null) of the subscription's folder
     * - "top_folder": The numeric identifier (or null) of the top-level folder for the subscription
     * - "pinned": Whether the subscription is pinned
     * - "err_count": The count of times attempting to refresh the newsfeed has resulted in an error since the last successful retrieval
     * - "err_msg": The error message of the last unsuccessful retrieval
     * - "order_type": Whether articles should be sorted in reverse cronological order (2), chronological order (1), or the default (0)
     * - "added": The date and time at which the subscription was added
     * - "updated": The date and time at which the newsfeed was last updated (not when it was last refreshed)
     * - "unread": The number of unread articles associated with the subscription
     */
    public function subscriptionPropertiesGet(string $user, $id): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'type' => "int > 0"]);
        }
        $sub = $this->subscriptionList($user, null, true, (int) $id)->getRow();
        if (!$sub) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return $sub;
    }

    /** Modifies the properties of a subscription
     *
     * The $data array must contain one or more of the following keys:
     *
     * - "title": The title of the newsfeed
     * - "folder": The numeric identifier (or null) of the subscription's folder
     * - "pinned": Whether the subscription is pinned
     * - "order_type": Whether articles should be sorted in reverse cronological order (2), chronological order (1), or the default (0)
     *
     * @param string $user The user whose subscription is to be modified
     * @param integer $id the numeric identifier of the subscription to modfify
     * @param array $data An associative array of properties to modify; any keys not specified will be left unchanged
     */
    public function subscriptionPropertiesSet(string $user, $id, array $data): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $tr = $this->db->begin();
        // validate the ID
        $id = $this->subscriptionValidateId($user, $id, true)['id'];
        if (array_key_exists("folder", $data)) {
            // ensure the target folder exists and belong to the user
            $data['folder'] = $this->folderValidateId($user, $data['folder'])['id'];
        }
        if (array_key_exists("title", $data)) {
            // if the title is null, this signals intended use of the default title; otherwise make sure it's not effectively an empty string
            if (!is_null($data['title'])) {
                $info = ValueInfo::str($data['title']);
                if ($info & ValueInfo::EMPTY) {
                    throw new Db\ExceptionInput("missing", ["action" => __FUNCTION__, "field" => "title"]);
                } elseif ($info & ValueInfo::WHITE) {
                    throw new Db\ExceptionInput("whitespace", ["action" => __FUNCTION__, "field" => "title"]);
                } elseif (!($info & ValueInfo::VALID)) {
                    throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "title", 'type' => "string"]);
                }
            }
        }
        $valid = [
            'title'      => "str",
            'folder'     => "int",
            'order_type' => "strict int",
            'pinned'     => "strict bool",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($data, $valid);
        if (!$setClause) {
            // if no changes would actually be applied, just return
            return false;
        }
        $out = (bool) $this->db->prepare("UPDATE arsse_subscriptions set $setClause, modified = CURRENT_TIMESTAMP where owner = ? and id = ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
        $tr->commit();
        return $out;
    }

    /** Returns an indexed array listing the tags assigned to a subscription
     *
     * @param string $user The user whose tags are to be listed
     * @param integer $id The numeric identifier of the subscription whose tags are to be listed
     * @param boolean $byName Whether to return the tag names (true) instead of the numeric tag identifiers (false)
     */
    public function subscriptionTagsGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->subscriptionValidateId($user, $id, true);
        $field = !$byName ? "id" : "name";
        $out = $this->db->prepare("SELECT $field from arsse_tags where id in (select tag from arsse_tag_members where subscription = ? and assigned = 1) order by $field", "int")->run($id)->getAll();
        return $out ? array_column($out, $field) : [];
    }

    /** Retrieves the URL of the icon for a subscription.
     *
     * Note that while the $user parameter is optional, it
     * is NOT recommended to omit it, as this can lead to
     * leaks of private information. The parameter is only
     * optional because this is required for Tiny Tiny RSS,
     * the original implementation of which leaks private
     * information due to a design flaw.
     *
     * @param integer $id The numeric identifier of the subscription
     * @param string|null $user The user who owns the subscription being queried
     */
    public function subscriptionFavicon(int $id, string $user = null): string {
        $q = new Query("SELECT favicon from arsse_feeds join arsse_subscriptions on feed = arsse_feeds.id");
        $q->setWhere("arsse_subscriptions.id = ?", "int", $id);
        if (isset($user)) {
            if (!Arsse::$user->authorize($user, __FUNCTION__)) {
                throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
            }
            $q->setWhere("arsse_subscriptions.owner = ?", "str", $user);
        }
        return (string) $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

    /** Returns the time at which any of a user's subscriptions (or a specific subscription) was last refreshed, as a DateTimeImmutable object */
    public function subscriptionRefreshed(string $user, int $id = null) {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $q = new Query("SELECT max(arsse_feeds.updated) from arsse_feeds join arsse_subscriptions on arsse_subscriptions.feed = arsse_feeds.id");
        $q->setWhere("arsse_subscriptions.owner = ?", "str", $user);
        if ($id) {
            $q->setWhere("arsse_subscriptions.id = ?", "int", $id);
        }
        $out = $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
        if (!$out && $id) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return ValueInfo::normalize($out, ValueInfo::T_DATE | ValueInfo::M_NULL, "sql");
    }

    /** Ensures the specified subscription exists and raises an exception otherwise
     *
     * Returns an associative array containing the id of the subscription and the id of the underlying newsfeed
     *
     * @param string $user The user who owns the subscription to be validated
     * @param integer $id The identifier of the subscription to validate
     * @param boolean $subject Whether the subscription is the subject (true) rather than the object (false) of the operation being performed; this only affects the semantics of the error message if validation fails
     */
    protected function subscriptionValidateId(string $user, $id, bool $subject = false): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "feed", 'type' => "int > 0"]);
        }
        $out = $this->db->prepare("SELECT id,feed from arsse_subscriptions where id = ? and owner = ?", "int", "str")->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "subscription", 'id' => $id]);
        }
        return $out;
    }

    /** Adds a newsfeed to the database without adding any subscriptions, and returns the numeric identifier of the added feed
     *
     * If the feed already exists in the database, the existing ID is returned
     *
     * @param string $url The URL of the newsfeed or discovery source
     * @param string $fetchUser The user name required to access the newsfeed, if applicable
     * @param string $fetchPassword The password required to fetch the newsfeed, if applicable; this will be stored in cleartext
     * @param boolean $discover Whether to perform newsfeed discovery if $url points to an HTML document
     */
    public function feedAdd(string $url, string $fetchUser = "", string $fetchPassword = "", bool $discover = true): int {
        // normalize the input URL
        $url = URL::normalize($url);
        // check to see if the feed already exists
        $check = $this->db->prepare("SELECT id from arsse_feeds where url = ? and username = ? and password = ?", "str", "str", "str");
        $feedID = $check->run($url, $fetchUser, $fetchPassword)->getValue();
        if ($discover && is_null($feedID)) {
            // if the feed doesn't exist, first perform discovery if requested and check for the existence of that URL
            $url = Feed::discover($url, $fetchUser, $fetchPassword);
            $feedID = $check->run($url, $fetchUser, $fetchPassword)->getValue();
        }
        if (is_null($feedID)) {
            // if the feed still doesn't exist in the database, add it to the database; we do this unconditionally so as to lock SQLite databases for as little time as possible
            $feedID = $this->db->prepare('INSERT INTO arsse_feeds(url,username,password) values(?,?,?)', 'str', 'str', 'str')->run($url, $fetchUser, $fetchPassword)->lastId();
            try {
                // perform an initial update on the newly added feed
                $this->feedUpdate($feedID, true);
            } catch (\Throwable $e) {
                // if the update fails, delete the feed we just added
                $this->db->prepare('DELETE from arsse_feeds where id = ?', 'int')->run($feedID);
                throw $e;
            }
        }
        return (int) $feedID;
    }

    /** Returns an indexed array of numeric identifiers for newsfeeds which should be refreshed */
    public function feedListStale(): array {
        $feeds = $this->db->query("SELECT id from arsse_feeds where next_fetch <= CURRENT_TIMESTAMP")->getAll();
        return array_column($feeds, 'id');
    }

    /** Attempts to refresh a newsfeed, returning an indication of success
     *
     * @param integer $feedID The numerical identifier of the newsfeed to refresh
     * @param boolean $throwError Whether to throw an exception on failure in addition to storing error information in the database
     */
    public function feedUpdate($feedID, bool $throwError = false): bool {
        // check to make sure the feed exists
        if (!ValueInfo::id($feedID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'id' => $feedID, 'type' => "int > 0"]);
        }
        $f = $this->db->prepare("SELECT url, username, password, modified, etag, err_count, scrape FROM arsse_feeds where id = ?", "int")->run($feedID)->getRow();
        if (!$f) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $feedID]);
        }
        // determine whether the feed's items should be scraped for full content from the source Web site
        $scrape = (Arsse::$conf->fetchEnableScraping && $f['scrape']);
        // the Feed object throws an exception when there are problems, but that isn't ideal
        // here. When an exception is thrown it should update the database with the
        // error instead of failing; if other exceptions are thrown, we should simply roll back
        try {
            $feed = new Feed((int) $feedID, $f['url'], (string) Date::transform($f['modified'], "http", "sql"), $f['etag'], $f['username'], $f['password'], $scrape);
            if (!$feed->modified) {
                // if the feed hasn't changed, just compute the next fetch time and record it
                $this->db->prepare("UPDATE arsse_feeds SET updated = CURRENT_TIMESTAMP, next_fetch = ? WHERE id = ?", 'datetime', 'int')->run($feed->nextFetch, $feedID);
                return false;
            }
        } catch (Feed\Exception $e) {
            // update the database with the resultant error and the next fetch time, incrementing the error count
            $this->db->prepare(
                "UPDATE arsse_feeds SET updated = CURRENT_TIMESTAMP, next_fetch = ?, err_count = err_count + 1, err_msg = ? WHERE id = ?",
                'datetime',
                'str',
                'int'
            )->run(Feed::nextFetchOnError($f['err_count']), $e->getMessage(), $feedID);
            if ($throwError) {
                throw $e;
            }
            return false;
        }
        //prepare the necessary statements to perform the update
        if (sizeof($feed->newItems) || sizeof($feed->changedItems)) {
            $qInsertEnclosure = $this->db->prepare("INSERT INTO arsse_enclosures(article,url,type) values(?,?,?)", 'int', 'str', 'str');
            $qInsertCategory = $this->db->prepare("INSERT INTO arsse_categories(article,name) values(?,?)", 'int', 'str');
            $qInsertEdition = $this->db->prepare("INSERT INTO arsse_editions(article) values(?)", 'int');
        }
        if (sizeof($feed->newItems)) {
            $qInsertArticle = $this->db->prepare(
                "INSERT INTO arsse_articles(url,title,author,published,edited,guid,content,url_title_hash,url_content_hash,title_content_hash,feed) values(?,?,?,?,?,?,?,?,?,?,?)",
                'str',
                'str',
                'str',
                'datetime',
                'datetime',
                'str',
                'str',
                'str',
                'str',
                'str',
                'int'
            );
        }
        if (sizeof($feed->changedItems)) {
            $qDeleteEnclosures = $this->db->prepare("DELETE FROM arsse_enclosures WHERE article = ?", 'int');
            $qDeleteCategories = $this->db->prepare("DELETE FROM arsse_categories WHERE article = ?", 'int');
            $qClearReadMarks = $this->db->prepare("UPDATE arsse_marks SET \"read\" = 0, modified = CURRENT_TIMESTAMP WHERE article = ? and \"read\" = 1", 'int');
            $qUpdateArticle = $this->db->prepare(
                "UPDATE arsse_articles SET url = ?, title = ?, author = ?, published = ?, edited = ?, modified = CURRENT_TIMESTAMP, guid = ?, content = ?, url_title_hash = ?, url_content_hash = ?, title_content_hash = ? WHERE id = ?",
                'str',
                'str',
                'str',
                'datetime',
                'datetime',
                'str',
                'str',
                'str',
                'str',
                'str',
                'int'
            );
        }
        // actually perform updates
        $tr = $this->db->begin();
        foreach ($feed->newItems as $article) {
            $articleID = $qInsertArticle->run(
                $article->url,
                $article->title,
                $article->author,
                $article->publishedDate,
                $article->updatedDate,
                $article->id,
                $article->content,
                $article->urlTitleHash,
                $article->urlContentHash,
                $article->titleContentHash,
                $feedID
            )->lastId();
            if ($article->enclosureUrl) {
                $qInsertEnclosure->run($articleID, $article->enclosureUrl, $article->enclosureType);
            }
            foreach ($article->categories as $c) {
                $qInsertCategory->run($articleID, $c);
            }
            $qInsertEdition->run($articleID);
        }
        foreach ($feed->changedItems as $articleID => $article) {
            $qUpdateArticle->run(
                $article->url,
                $article->title,
                $article->author,
                $article->publishedDate,
                $article->updatedDate,
                $article->id,
                $article->content,
                $article->urlTitleHash,
                $article->urlContentHash,
                $article->titleContentHash,
                $articleID
            );
            $qDeleteEnclosures->run($articleID);
            $qDeleteCategories->run($articleID);
            if ($article->enclosureUrl) {
                $qInsertEnclosure->run($articleID, $article->enclosureUrl, $article->enclosureType);
            }
            foreach ($article->categories as $c) {
                $qInsertCategory->run($articleID, $c);
            }
            $qInsertEdition->run($articleID);
            $qClearReadMarks->run($articleID);
        }
        // lastly update the feed database itself with updated information.
        $this->db->prepare(
            "UPDATE arsse_feeds SET title = ?, favicon = ?, source = ?, updated = CURRENT_TIMESTAMP, modified = ?, etag = ?, err_count = 0, err_msg = '', next_fetch = ?, size = ? WHERE id = ?",
            'str',
            'str',
            'str',
            'datetime',
            'str',
            'datetime',
            'int',
            'int'
        )->run(
            $feed->data->title,
            $feed->favicon,
            $feed->data->siteUrl,
            $feed->lastModified,
            $feed->resource->getEtag(),
            $feed->nextFetch,
            sizeof($feed->data->items),
            $feedID
        );
        $tr->commit();
        return true;
    }

    /** Deletes orphaned newsfeeds from the database
     *
     * Newsfeeds are orphaned if no users are subscribed to them. Deleting a newsfeed also deletes its articles
     */
    public function feedCleanup(): bool {
        $tr = $this->begin();
        // first unmark any feeds which are no longer orphaned
        $this->db->query("WITH active_feeds as (select id from arsse_feeds left join (select feed, count(id) as count from arsse_subscriptions group by feed) as sub_stats on sub_stats.feed = arsse_feeds.id where orphaned is not null and count is not null) UPDATE arsse_feeds set orphaned = null where id in (select id from active_feeds)");
        // next mark any newly orphaned feeds with the current date and time
        $this->db->query("WITH orphaned_feeds as (select id from arsse_feeds left join (select feed, count(id) as count from arsse_subscriptions group by feed) as sub_stats on sub_stats.feed = arsse_feeds.id where orphaned is null and count is null) UPDATE arsse_feeds set orphaned = CURRENT_TIMESTAMP where id in (select id from orphaned_feeds)");
        // finally delete feeds that have been orphaned longer than the retention period, if a a purge threshold has been specified
        if (Arsse::$conf->purgeFeeds) {
            $limit = Date::sub(Arsse::$conf->purgeFeeds);
            $out = (bool) $this->db->prepare("DELETE from arsse_feeds where orphaned <= ?", "datetime")->run($limit);
        } else {
            $out = false;
        }
        $tr->commit();
        return $out;
    }

    /** Retrieves various identifiers for the latest $count articles in the given newsfeed. The identifiers are:
     *
     * - "id": The database record key for the article
     * - "guid": The (theoretically) unique identifier for the article
     * - "edited": The time at which the article was last edited, per the newsfeed
     * - "url_title_hash": A cryptographic hash of the article URL and its title
     * - "url_content_hash": A cryptographic hash of the article URL and its content
     * - "title_content_hash": A cryptographic hash of the article title and its content
     *
     * @param integer $feedID The numeric identifier of the feed
     * @param integer $count The number of records to return
     */
    public function feedMatchLatest(int $feedID, int $count): Db\Result {
        return $this->db->prepare(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed = ? ORDER BY modified desc, id desc limit ?",
            'int',
            'int'
        )->run($feedID, $count);
    }

    /** Retrieves various identifiers for articles in the given newsfeed which match the input identifiers. The output identifiers are:
     *
     * - "id": The database record key for the article
     * - "guid": The (theoretically) unique identifier for the article
     * - "edited": The time at which the article was last edited, per the newsfeed
     * - "url_title_hash": A cryptographic hash of the article URL and its title
     * - "url_content_hash": A cryptographic hash of the article URL and its content
     * - "title_content_hash": A cryptographic hash of the article title and its content
     *
     * @param integer $feedID The numeric identifier of the feed
     * @param array $ids An array of GUIDs of articles
     * @param array $hashesUT An array of hashes of articles' URL and title
     * @param array $hashesUC An array of hashes of articles' URL and content
     * @param array $hashesTC An array of hashes of articles' title and content
     */
    public function feedMatchIds(int $feedID, array $ids = [], array $hashesUT = [], array $hashesUC = [], array $hashesTC = []): Db\Result {
        // compile SQL IN() clauses and necessary type bindings for the four identifier lists
        list($cId, $tId, $vId)             = $this->generateIn($ids, "str");
        list($cHashUT, $tHashUT, $vHashUT) = $this->generateIn($hashesUT, "str");
        list($cHashUC, $tHashUC, $vHashUC) = $this->generateIn($hashesUC, "str");
        list($cHashTC, $tHashTC, $vHashTC) = $this->generateIn($hashesTC, "str");
        // perform the query
        return $articles = $this->db->prepare(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed = ? and (guid in($cId) or url_title_hash in($cHashUT) or url_content_hash in($cHashUC) or title_content_hash in($cHashTC))",
            'int',
            $tId,
            $tHashUT,
            $tHashUC,
            $tHashTC
        )->run($feedID, $vId, $vHashUT, $vHashUC, $vHashTC);
    }

    /** Returns an associative array of result column names and their SQL computations for article queries
     *
     * This is used for whitelisting and defining both output column and order-by columns, as well as for resolution of some context options
     */
    protected function articleColumns(): array {
        $greatest = $this->db->sqlToken("greatest");
        return [
            'id' => "arsse_articles.id",
            'edition' => "latest_editions.edition",
            'url' => "arsse_articles.url",
            'title' => "arsse_articles.title",
            'author' => "arsse_articles.author",
            'content' => "arsse_articles.content",
            'guid' => "arsse_articles.guid",
            'fingerprint' => "arsse_articles.url_title_hash || ':' || arsse_articles.url_content_hash || ':' || arsse_articles.title_content_hash",
            'folder' => "coalesce(arsse_subscriptions.folder,0)",
            'subscription' => "arsse_subscriptions.id",
            'feed' => "arsse_subscriptions.feed",
            'starred' => "coalesce(arsse_marks.starred,0)",
            'unread' => "abs(coalesce(arsse_marks.read,0) - 1)",
            'note' => "coalesce(arsse_marks.note,'')",
            'published_date' => "arsse_articles.published",
            'edited_date' => "arsse_articles.edited",
            'modified_date' => "arsse_articles.modified",
            'marked_date' => "$greatest(arsse_articles.modified, coalesce(arsse_marks.modified, '0001-01-01 00:00:00'), coalesce(label_stats.modified, '0001-01-01 00:00:00'))",
            'subscription_title' => "coalesce(arsse_subscriptions.title, arsse_feeds.title)",
            'media_url' => "arsse_enclosures.url",
            'media_type' => "arsse_enclosures.type",
        ];
    }

    /** Computes an SQL query to find and retrieve data about articles in the database
     *
     * If an empty column list is supplied, a count of articles matching the context is queried instead
     *
     * @param string $user The user whose articles are to be queried
     * @param Context $context The search context
     * @param array $cols The columns to request in the result set
     */
    protected function articleQuery(string $user, Context $context, array $cols = ["id"]): Query {
        // validate input
        if ($context->subscription()) {
            $this->subscriptionValidateId($user, $context->subscription);
        }
        if ($context->folder()) {
            $this->folderValidateId($user, $context->folder);
        }
        if ($context->folderShallow()) {
            $this->folderValidateId($user, $context->folderShallow);
        }
        if ($context->edition()) {
            $this->articleValidateEdition($user, $context->edition);
        }
        if ($context->article()) {
            $this->articleValidateId($user, $context->article);
        }
        if ($context->label()) {
            $this->labelValidateId($user, $context->label, false);
        }
        if ($context->labelName()) {
            $this->labelValidateId($user, $context->labelName, true);
        }
        // prepare the output column list; the column definitions are also used later
        $colDefs = $this->articleColumns();
        if (!$cols) {
            // if no columns are specified return a count; don't borther with sorting
            $outColumns = "count(distinct arsse_articles.id) as count";
        } else {
            // normalize requested output and sorting columns
            $norm = function($v) {
                return trim(strtolower(ValueInfo::normalize($v, ValueInfo::T_STRING)));
            };
            $cols = array_map($norm, $cols);
            // make an output column list
            $outColumns = [];
            foreach ($cols as $col) {
                if (!isset($colDefs[$col])) {
                    continue;
                }
                $outColumns[] = $colDefs[$col]." as ".$col;
            }
            $outColumns = implode(",", $outColumns);
        }
        // define the basic query, to which we add lots of stuff where necessary
        $q = new Query(
            "SELECT 
                $outColumns
            from arsse_articles
            join arsse_subscriptions on arsse_subscriptions.feed = arsse_articles.feed and arsse_subscriptions.owner = ?
            join arsse_feeds on arsse_subscriptions.feed = arsse_feeds.id
            left join arsse_marks on arsse_marks.subscription = arsse_subscriptions.id and arsse_marks.article = arsse_articles.id
            left join arsse_enclosures on arsse_enclosures.article = arsse_articles.id
            join (
                SELECT article, max(id) as edition from arsse_editions group by article
            ) as latest_editions on arsse_articles.id = latest_editions.article
            left join (
                SELECT arsse_label_members.article, max(arsse_label_members.modified) as modified, sum(arsse_label_members.assigned) as assigned from arsse_label_members join arsse_labels on arsse_labels.id = arsse_label_members.label where arsse_labels.owner = ? group by arsse_label_members.article
            ) as label_stats on label_stats.article = arsse_articles.id",
            ["str", "str"],
            [$user, $user]
        );
        $q->setLimit($context->limit, $context->offset);
        // handle the simple context options
        $options = [
            // each context array consists of a column identifier (see $colDefs above), a comparison operator, a data type, and an option to pair with for BETWEEN evaluation
            "edition"          => ["edition",       "=",  "int",      ""],
            "editions"         => ["edition",       "in", "int",      ""],
            "article"          => ["id",            "=",  "int",      ""],
            "articles"         => ["id",            "in", "int",      ""],
            "oldestArticle"    => ["id",            ">=", "int",      "latestArticle"],
            "latestArticle"    => ["id",            "<=", "int",      "oldestArticle"],
            "oldestEdition"    => ["edition",       ">=", "int",      "latestEdition"],
            "latestEdition"    => ["edition",       "<=", "int",      "oldestEdition"],
            "modifiedSince"    => ["modified_date", ">=", "datetime", "notModifiedSince"],
            "notModifiedSince" => ["modified_date", "<=", "datetime", "modifiedSince"],
            "markedSince"      => ["marked_date",   ">=", "datetime", "notMarkedSince"],
            "notMarkedSince"   => ["marked_date",   "<=", "datetime", "markedSince"],
            "folderShallow"    => ["folder",        "=",  "int",      ""],
            "foldersShallow"   => ["folder",        "in", "int",      ""],
            "subscription"     => ["subscription",  "=",  "int",      ""],
            "subscriptions"    => ["subscription",  "in", "int",      ""],
            "unread"           => ["unread",        "=",  "bool",     ""],
            "starred"          => ["starred",       "=",  "bool",     ""],
        ];
        foreach ($options as $m => list($col, $op, $type, $pair)) {
            if (!$context->$m()) {
                // context is not being used
                continue;
            } elseif (is_array($context->$m)) {
                // context option is an array of values
                if (!$context->$m) {
                    throw new Db\ExceptionInput("tooShort", ['field' => $m, 'action' => $this->caller(), 'min' => 1]); // must have at least one array element
                }
                list($clause, $types, $values) = $this->generateIn($context->$m, $type);
                $q->setWhere("{$colDefs[$col]} $op ($clause)", $types, $values);
            } elseif ($pair && $context->$pair()) {
                // option is paired with another which is also being used
                if ($op === ">=") {
                    $q->setWhere("{$colDefs[$col]} BETWEEN ? AND ?", [$type, $type], [$context->$m, $context->$pair]);
                } else {
                    // option has already been paired
                    continue;
                }
            } else {
                $q->setWhere("{$colDefs[$col]} $op ?", $type, $context->$m);
            }
        }
        // further handle exclusionary options if specified
        foreach ($options as $m => list($col, $op, $type, $pair)) {
            if (!method_exists($context->not, $m) || !$context->not->$m()) {
                // context option is not being used
                continue;
            } elseif (is_array($context->not->$m)) {
                if (!$context->not->$m) {
                    // for exclusions we don't care if the array is empty
                    continue;
                }
                list($clause, $types, $values) = $this->generateIn($context->not->$m, $type);
                $q->setWhereNot("{$colDefs[$col]} $op ($clause)", $types, $values);
            } elseif ($pair && $context->not->$pair()) {
                // option is paired with another which is also being used
                if ($op === ">=") {
                    $q->setWhereNot("{$colDefs[$col]} BETWEEN ? AND ?", [$type, $type], [$context->not->$m, $context->not->$pair]);
                } else {
                    // option has already been paired
                    continue;
                }
            } else {
                $q->setWhereNot("{$colDefs[$col]} $op ?", $type, $context->not->$m);
            }
        }
        // handle labels and tags
        $options = [
            'label' => [
                'match_col' => "arsse_articles.id",
                'cte_name' => "labelled",
                'cte_cols' => ["article", "label_id", "label_name"],
                'cte_body' => "SELECT m.article, l.id, l.name from arsse_label_members as m join arsse_labels as l on l.id = m.label where l.owner = ? and m.assigned = 1",
                'cte_types' => ["str"],
                'cte_values' => [$user],
                'options' => [
                    'label'      => ['use_name' => false, 'multi' => false],
                    'labels'     => ['use_name' => false, 'multi' => true],
                    'labelName'  => ['use_name' => true,  'multi' => false],
                    'labelNames' => ['use_name' => true,  'multi' => true],
                ],
            ],
            'tag' => [
                'match_col' => "arsse_subscriptions.id",
                'cte_name' => "tagged",
                'cte_cols' => ["subscription", "tag_id", "tag_name"],
                'cte_body' => "SELECT m.subscription, t.id, t.name from arsse_tag_members as m join arsse_tags as t on t.id = m.tag where t.owner = ? and m.assigned = 1",
                'cte_types' => ["str"],
                'cte_values' => [$user],
                'options' => [
                    'tag'      => ['use_name' => false, 'multi' => false],
                    'tags'     => ['use_name' => false, 'multi' => true],
                    'tagName'  => ['use_name' => true,  'multi' => false],
                    'tagNames' => ['use_name' => true,  'multi' => true],
                ],
            ],
        ];
        foreach ($options as $opt) {
            $seen = false;
            $match = $opt['match_col'];
            $table = $opt['cte_name'];
            foreach ($opt['options'] as $m => $props) {
                $named = $props['use_name'];
                $multi = $props['multi'];
                $selection = $opt['cte_cols'][0];
                $col = $opt['cte_cols'][$named ? 2 : 1];
                if ($context->$m()) {
                    $seen = true;
                    if (!$context->$m) {
                        throw new Db\ExceptionInput("tooShort", ['field' => $m, 'action' => $this->caller(), 'min' => 1]); // must have at least one array element
                    }
                    if ($multi) {
                        list($test, $types, $values) = $this->generateIn($context->$m, $named ? "str" : "int");
                        $test = "in ($test)";
                    } else {
                        $test = "= ?";
                        $types = $named ? "str" : "int";
                        $values = $context->$m;
                    }
                    $q->setWhere("$match in (select $selection from $table where $col $test)", $types, $values);
                }
                if ($context->not->$m()) {
                    $seen = true;
                    if ($multi) {
                        list($test, $types, $values) = $this->generateIn($context->not->$m, $named ? "str" : "int");
                        $test = "in ($test)";
                    } else {
                        $test = "= ?";
                        $types = $named ? "str" : "int";
                        $values = $context->not->$m;
                    }
                    $q->setWhereNot("$match in (select $selection from $table where $col $test)", $types, $values);
                }
            }
            if ($seen) {
                $spec = $opt['cte_name']."(".implode(",", $opt['cte_cols']).")";
                $q->setCTE($spec, $opt['cte_body'], $opt['cte_types'], $opt['cte_values']);
            }
        }
        // handle complex context options
        if ($context->annotated()) {
            $comp = ($context->annotated) ? "<>" : "=";
            $q->setWhere("coalesce(arsse_marks.note,'') $comp ''");
        }
        if ($context->labelled()) {
            // any label (true) or no label (false)
            $op = $context->labelled ? ">" : "=";
            $q->setWhere("coalesce(label_stats.assigned,0) $op 0");
        }
        if ($context->folder()) {
            // add a common table expression to list the folder and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on coalesce(parent,0) = folder", "int", $context->folder);
            // limit subscriptions to the listed folders
            $q->setWhere("coalesce(arsse_subscriptions.folder,0) in (select folder from folders)");
        }
        if ($context->folders()) {
            list($inClause, $inTypes, $inValues) = $this->generateIn($context->folders, "int");
            // add a common table expression to list the folders and their children so that we select from the entire subtree
            $q->setCTE("folders_multi(folder)", "SELECT id as folder from (select id from (select 0 as id union select id from arsse_folders where owner = ?) as f where id in ($inClause)) as folders_multi union select id from arsse_folders join folders_multi on coalesce(parent,0) = folder", ["str", $inTypes], [$user, $inValues]);
            // limit subscriptions to the listed folders
            $q->setWhere("coalesce(arsse_subscriptions.folder,0) in (select folder from folders_multi)");
        }
        if ($context->not->folder()) {
            // add a common table expression to list the folder and its children so that we exclude from the entire subtree
            $q->setCTE("folders_excluded(folder)", "SELECT ? union select id from arsse_folders join folders_excluded on coalesce(parent,0) = folder", "int", $context->not->folder);
            // excluded any subscriptions in the listed folders
            $q->setWhereNot("coalesce(arsse_subscriptions.folder,0) in (select folder from folders_excluded)");
        }
        if ($context->not->folders()) {
            list($inClause, $inTypes, $inValues) = $this->generateIn($context->not->folders, "int");
            // add a common table expression to list the folders and their children so that we select from the entire subtree
            $q->setCTE("folders_multi_excluded(folder)", "SELECT id as folder from (select id from (select 0 as id union select id from arsse_folders where owner = ?) as f where id in ($inClause)) as folders_multi_excluded union select id from arsse_folders join folders_multi_excluded on coalesce(parent,0) = folder", ["str", $inTypes], [$user, $inValues]);
            // limit subscriptions to the listed folders
            $q->setWhereNot("coalesce(arsse_subscriptions.folder,0) in (select folder from folders_multi_excluded)");
        }
        // handle text-matching context options
        $options = [
            "titleTerms"      => ["arsse_articles.title"],
            "searchTerms"     => ["arsse_articles.title", "arsse_articles.content"],
            "authorTerms"     => ["arsse_articles.author"],
            "annotationTerms" => ["arsse_marks.note"],
        ];
        foreach ($options as $m => $columns) {
            if (!$context->$m()) {
                continue;
            } elseif (!$context->$m) {
                throw new Db\ExceptionInput("tooShort", ['field' => $m, 'action' => $this->caller(), 'min' => 1]); // must have at least one array element
            }
            $q->setWhere(...$this->generateSearch($context->$m, $columns));
        }
        // further handle exclusionary text-matching context options
        foreach ($options as $m => $columns) {
            if (!$context->not->$m() || !$context->not->$m) {
                continue;
            }
            $q->setWhereNot(...$this->generateSearch($context->not->$m, $columns, true));
        }
        // return the query
        return $q;
    }

    /** Lists articles in the database which match a given query context
     *
     * If an empty column list is supplied, a count of articles is returned instead
     *
     * @param string $user The user whose articles are to be listed
     * @param Context $context The search context
     * @param array $fieldss The columns to return in the result set, any of: id, edition, url, title, author, content, guid, fingerprint, folder, subscription, feed, starred, unread, note, published_date, edited_date, modified_date, marked_date, subscription_title, media_url, media_type
     * @param array $sort The columns to sort the result by eg. "edition desc" in decreasing order of importance
     */
    public function articleList(string $user, Context $context = null, array $fields = ["id"], array $sort = []): Db\Result {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // make a base query based on context and output columns
        $context = $context ?? new Context;
        $q = $this->articleQuery($user, $context, $fields);
        // make an ORDER BY column list
        $colDefs = $this->articleColumns();
        // normalize requested output and sorting columns
        $norm = function($v) {
            return trim(strtolower((string) $v));
        };
        $fields = array_map($norm, $fields);
        $sort = array_map($norm, $sort);
        foreach ($sort as $spec) {
            $col = explode(" ", $spec, 2);
            $order = $col[1] ?? "";
            $col = $col[0];
            if ($order === "desc") {
                $order = " desc";
            } elseif ($order === "asc" || $order === "") {
                $order = "";
            } else {
                // column direction spec is bogus
                continue;
            }
            if (!isset($colDefs[$col])) {
                // column name spec is bogus
                continue;
            } elseif (in_array($col, $fields)) {
                // if the sort column is also an output column, use it as-is
                $q->setOrder($col.$order);
            } else {
                // otherwise if the column name is valid, use its expression
                $q->setOrder($colDefs[$col].$order);
            }
        }
        // perform the query and return results
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    /** Returns a count of articles which match the given query context
     *
     * @param string $user The user whose articles are to be counted
     * @param Context $context The search context
     */
    public function articleCount(string $user, Context $context = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        $q = $this->articleQuery($user, $context, []);
        return (int) $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

    /** Applies one or multiple modifications to all articles matching the given query context
     *
     * The $data array enumerates the modifications to perform and must contain one or more of the following keys:
     *
     * - "read":    Whether the article should be marked as read (true) or unread (false)
     * - "starred": Whether the article should (true) or should not (false) be marked as starred/favourite
     * - "note":    A string containing a freeform plain-text note for the article
     *
     * @param string $user The user who owns the articles to be modified
     * @param array $data An associative array of properties to modify. Anything not specified will remain unchanged
     * @param Context $context The query context to match articles against
     */
    public function articleMark(string $user, array $data, Context $context = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $data = [
            'read' => $data['read'] ?? null,
            'starred' => $data['starred'] ?? null,
            'note' => $data['note'] ?? null,
        ];
        if (!isset($data['read']) && !isset($data['starred']) && !isset($data['note'])) {
            return 0;
        }
        $context = $context ?? new Context;
        $tr = $this->begin();
        $out = 0;
        if ($data['read'] || $data['starred'] || strlen($data['note'] ?? "")) {
            // first prepare a query to insert any missing marks rows for the articles we want to mark
            // but only insert new mark records if we're setting at least one "positive" mark
            $q = $this->articleQuery($user, $context, ["id", "subscription", "note"]);
            $q->setWhere("arsse_marks.starred is null"); // null means there is no marks row for the article
            $this->db->prepare("INSERT INTO arsse_marks(article,subscription,note) ".$q->getQuery(), $q->getTypes())->run($q->getValues());
        }
        if (isset($data['read']) && (isset($data['starred']) || isset($data['note'])) && ($context->edition() || $context->editions())) {
            // if marking by edition both read and something else, do separate marks for starred and note than for read
            // marking as read is ignored if the edition is not the latest, but the same is not true of the other two marks
            $this->db->query("UPDATE arsse_marks set touched = 0 where touched <> 0");
            // set read marks
            $q = $this->articleQuery($user, $context, ["id", "subscription"]);
            $q->setWhere("arsse_marks.read <> coalesce(?,arsse_marks.read)", "bool", $data['read']);
            $q->pushCTE("target_articles(article,subscription)");
            $q->setBody("UPDATE arsse_marks set \"read\" = ?, touched = 1 where article in(select article from target_articles) and subscription in(select distinct subscription from target_articles)", "bool", $data['read']);
            $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
            // get the articles associated with the requested editions
            if ($context->edition()) {
                $context->article($this->articleValidateEdition($user, $context->edition)['article'])->edition(null);
            } else {
                $context->articles($this->editionArticle(...$context->editions))->editions(null);
            }
            // set starred and/or note marks (unless all requested editions actually do not exist)
            if ($context->article || $context->articles) {
                $q = $this->articleQuery($user, $context, ["id", "subscription"]);
                $q->setWhere("(arsse_marks.note <> coalesce(?,arsse_marks.note) or arsse_marks.starred <> coalesce(?,arsse_marks.starred))", ["str", "bool"], [$data['note'], $data['starred']]);
                $q->pushCTE("target_articles(article,subscription)");
                $data = array_filter($data, function($v) {
                    return isset($v);
                });
                list($set, $setTypes, $setValues) = $this->generateSet($data, ['starred' => "bool", 'note' => "str"]);
                $q->setBody("UPDATE arsse_marks set touched = 1, $set where article in(select article from target_articles) and subscription in(select distinct subscription from target_articles)", $setTypes, $setValues);
                $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
            }
            // finally set the modification date for all touched marks and return the number of affected marks
            $out = $this->db->query("UPDATE arsse_marks set modified = CURRENT_TIMESTAMP, touched = 0 where touched = 1")->changes();
        } else {
            if (!isset($data['read']) && ($context->edition() || $context->editions())) {
                // get the articles associated with the requested editions
                if ($context->edition()) {
                    $context->article($this->articleValidateEdition($user, $context->edition)['article'])->edition(null);
                } else {
                    $context->articles($this->editionArticle(...$context->editions))->editions(null);
                }
                if (!$context->article && !$context->articles) {
                    return 0;
                }
            }
            $q = $this->articleQuery($user, $context, ["id", "subscription"]);
            $q->setWhere("(arsse_marks.note <> coalesce(?,arsse_marks.note) or arsse_marks.starred <> coalesce(?,arsse_marks.starred) or arsse_marks.read <> coalesce(?,arsse_marks.read))", ["str", "bool", "bool"], [$data['note'], $data['starred'], $data['read']]);
            $q->pushCTE("target_articles(article,subscription)");
            $data = array_filter($data, function($v) {
                return isset($v);
            });
            list($set, $setTypes, $setValues) = $this->generateSet($data, ['read' => "bool", 'starred' => "bool", 'note' => "str"]);
            $q->setBody("UPDATE arsse_marks set $set, modified = CURRENT_TIMESTAMP where article in(select article from target_articles) and subscription in(select distinct subscription from target_articles)", $setTypes, $setValues);
            $out = $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        }
        $tr->commit();
        return $out;
    }

    /** Returns statistics about the articles starred by the given user
     *
     * The associative array returned has the following keys:
     *
     * - "total":  The count of all starred articles
     * - "unread": The count of starred articles which are unread
     * - "read":   The count of starred articles which are read
     */
    public function articleStarred(string $user): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare(
            "SELECT
                count(*) as total,
                coalesce(sum(abs(\"read\" - 1)),0) as unread,
                coalesce(sum(\"read\"),0) as \"read\"
            FROM (
                select \"read\" from arsse_marks where starred = 1 and subscription in (select id from arsse_subscriptions where owner = ?)
            ) as starred_data",
            "str"
        )->run($user)->getRow();
    }

    /** Returns an indexed array listing the labels assigned to an article
     *
     * @param string $user The user whose labels are to be listed
     * @param integer $id The numeric identifier of the article whose labels are to be listed
     * @param boolean $byName Whether to return the label names (true) instead of the numeric label identifiers (false)
     */
    public function articleLabelsGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $id = $this->articleValidateId($user, $id)['article'];
        $field = !$byName ? "id" : "name";
        $out = $this->db->prepare("SELECT $field from arsse_labels join arsse_label_members on arsse_label_members.label = arsse_labels.id where owner = ? and article = ? and assigned = 1 order by $field", "str", "int")->run($user, $id)->getAll();
        return $out ? array_column($out, $field) : [];
    }

    /** Returns the author-supplied categories associated with an article */
    public function articleCategoriesGet(string $user, $id): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $id = $this->articleValidateId($user, $id)['article'];
        $out = $this->db->prepare("SELECT name from arsse_categories where article = ? order by name", "int")->run($id)->getAll();
        if (!$out) {
            return $out;
        } else {
            // flatten the result
            return array_column($out, "name");
        }
    }

    /** Deletes from the database articles which are beyond the configured clean-up threshold */
    public function articleCleanup(): bool {
        $query = $this->db->prepare(
            "WITH RECURSIVE
                exempt_articles as (SELECT id from arsse_articles join (SELECT article, max(id) as edition from arsse_editions group by article) as latest_editions on arsse_articles.id = latest_editions.article where feed = ? order by edition desc limit ?),
                target_articles as (
                    select id from arsse_articles
                        left join (select article, sum(starred) as starred, sum(\"read\") as \"read\", max(arsse_marks.modified) as marked_date from arsse_marks join arsse_subscriptions on arsse_subscriptions.id = arsse_marks.subscription group by article) as mark_stats on mark_stats.article = arsse_articles.id
                        left join (select feed, count(*) as subs from arsse_subscriptions group by feed) as feed_stats on feed_stats.feed = arsse_articles.feed
                    where arsse_articles.feed = ? and coalesce(starred,0) = 0 and (coalesce(marked_date,modified) <= ? or (coalesce(\"read\",0) = coalesce(subs,0) and coalesce(marked_date,modified) <= ?))
                )
            DELETE FROM arsse_articles WHERE id not in (select id from exempt_articles) and id in (select id from target_articles)",
            "int",
            "int",
            "int",
            "datetime",
            "datetime"
        );
        $limitRead = null;
        $limitUnread = null;
        if (Arsse::$conf->purgeArticlesRead) {
            $limitRead = Date::sub(Arsse::$conf->purgeArticlesRead);
        }
        if (Arsse::$conf->purgeArticlesUnread) {
            $limitUnread = Date::sub(Arsse::$conf->purgeArticlesUnread);
        }
        $feeds = $this->db->query("SELECT id, size from arsse_feeds")->getAll();
        $deleted = 0;
        foreach ($feeds as $feed) {
            $deleted += $query->run($feed['id'], $feed['size'], $feed['id'], $limitUnread, $limitRead)->changes();
        }
        return (bool) $deleted;
    }

    /** Ensures the specified article exists and raises an exception otherwise
     *
     * Returns an associative array containing the id and latest edition of the article if it exists
     *
     * @param string $user The user who owns the article to be validated
     * @param integer $id The identifier of the article to validate
     */
    protected function articleValidateId(string $user, $id): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "article", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepare(
            "SELECT articles.article as article, max(arsse_editions.id)  as edition from (
                select arsse_articles.id as article
                FROM arsse_articles
                    join arsse_subscriptions on arsse_subscriptions.feed = arsse_articles.feed
                WHERE arsse_articles.id = ? and arsse_subscriptions.owner = ?
            ) as articles join arsse_editions on arsse_editions.article = articles.article group by articles.article",
            "int",
            "str"
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "article", 'id' => $id]);
        }
        return $out;
    }

    /** Ensures the specified article edition exists and raises an exception otherwise
     *
     * Returns an associative array containing the edition id, article id, and latest edition of the edition if it exists
     *
     * @param string $user The user who owns the edition to be validated
     * @param integer $id The identifier of the edition to validate
     */
    protected function articleValidateEdition(string $user, int $id): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "edition", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepare(
            "SELECT
                arsse_editions.id, arsse_editions.article, edition_stats.edition as current
            from arsse_editions 
                join arsse_articles on arsse_articles.id = arsse_editions.article
                join arsse_subscriptions on arsse_subscriptions.feed = arsse_articles.feed
                join (select article, max(id) as edition from arsse_editions group by article) as edition_stats on edition_stats.article = arsse_editions.article
            where arsse_editions.id = ? and arsse_subscriptions.owner = ?",
            "int",
            "str"
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "edition", 'id' => $id]);
        }
        return array_map("intval", $out);
    }

    /** Returns the numeric identifier of the most recent edition of an article matching the given context */
    public function editionLatest(string $user, Context $context = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        $q = new Query("SELECT max(arsse_editions.id) from arsse_editions left join arsse_articles on article = arsse_articles.id join arsse_subscriptions on arsse_articles.feed = arsse_subscriptions.feed and arsse_subscriptions.owner = ?", "str", $user);
        if ($context->subscription()) {
            // if a subscription is specified, make sure it exists
            $this->subscriptionValidateId($user, $context->subscription);
            // a simple WHERE clause is required here
            $q->setWhere("arsse_subscriptions.id = ?", "int", $context->subscription);
        }
        return (int) $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

    /** Returns a map between all the given edition identifiers and their associated article identifiers */
    public function editionArticle(int ...$edition): array {
        $out = [];
        $context = (new Context)->editions($edition);
        list($in, $inTypes, $inValues) = $this->generateIn($context->editions, "int");
        $out = $this->db->prepare("SELECT id as edition, article from arsse_editions where id in($in)", $inTypes)->run($inValues)->getAll();
        return $out ? array_combine(array_column($out, "edition"), array_column($out, "article")) : [];
    }

    /** Creates a label, and returns its numeric identifier
     *
     * Labels are discrete objects in the database and can be associated with multiple articles; an article may in turn be associated with multiple labels
     *
     * @param string $user The user who will own the created label
     * @param array $data An associative array defining the label's properties; currently only "name" is understood
     */
    public function labelAdd(string $user, array $data): int {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate the label name
        $name = array_key_exists("name", $data) ? $data['name'] : "";
        $this->labelValidateName($name, true);
        // perform the insert
        return $this->db->prepare("INSERT INTO arsse_labels(owner,name) values(?,?)", "str", "str")->run($user, $name)->lastId();
    }

    /** Lists a user's article labels
     *
     * The following keys are included in each record:
     *
     * - "id": The label's numeric identifier
     * - "name" The label's textual name
     * - "articles": The count of articles which have the label assigned to them
     * - "read": How many of the total articles assigned to the label are read
     *
     * @param string $user The user whose labels are to be listed
     * @param boolean $includeEmpty Whether to include (true) or supress (false) labels which have no articles assigned to them
     */
    public function labelList(string $user, bool $includeEmpty = true): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare(
            "SELECT * FROM (
                SELECT
                    id,name,coalesce(articles,0) as articles,coalesce(marked,0) as \"read\"
                from arsse_labels
                    left join (
                        SELECT label, sum(assigned) as articles from arsse_label_members group by label
                    ) as label_stats on label_stats.label = arsse_labels.id
                    left join (
                        SELECT 
                            label, sum(\"read\") as marked
                        from arsse_marks
                            join arsse_subscriptions on arsse_subscriptions.id = arsse_marks.subscription
                            join arsse_label_members on arsse_label_members.article = arsse_marks.article
                        where arsse_subscriptions.owner = ?
                        group by label
                    ) as mark_stats on mark_stats.label = arsse_labels.id
                WHERE owner = ?
            ) as label_data
            where articles >= ? order by name
            ",
            "str",
            "str",
            "int"
        )->run($user, $user, !$includeEmpty);
    }

    /** Deletes a label from the database
     *
     * Any articles associated with the label remains untouched
     *
     * @param string $user The owner of the label to remove
     * @param integer|string $id The numeric identifier or name of the label
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     */
    public function labelRemove(string $user, $id, bool $byName = false): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->labelValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $changes = $this->db->prepare("DELETE FROM arsse_labels where owner = ? and $field = ?", "str", $type)->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
        }
        return true;
    }

    /** Retrieves the properties of a label
     *
     * The following keys are included in the output array:
     *
     * - "id": The label's numeric identifier
     * - "name" The label's textual name
     * - "articles": The count of articles which have the label assigned to them
     * - "read": How many of the total articles assigned to the label are read
     *
     * @param string $user The owner of the label to remove
     * @param integer|string $id The numeric identifier or name of the label
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     */
    public function labelPropertiesGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->labelValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $out = $this->db->prepare(
            "SELECT
                id,name,coalesce(articles,0) as articles,coalesce(marked,0) as \"read\"
            FROM arsse_labels
                left join (
                    SELECT label, sum(assigned) as articles from arsse_label_members group by label
                ) as label_stats on label_stats.label = arsse_labels.id
                left join (
                    SELECT 
                        label, sum(\"read\") as marked
                    from arsse_marks
                    join arsse_subscriptions on arsse_subscriptions.id = arsse_marks.subscription
                    join arsse_label_members on arsse_label_members.article = arsse_marks.article
                    where arsse_subscriptions.owner = ?
                    group by label
                ) as mark_stats on mark_stats.label = arsse_labels.id
            WHERE $field = ? and owner = ?
            ",
            "str",
            $type,
            "str"
        )->run($user, $id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
        }
        return $out;
    }

    /** Sets the properties of a label
     *
     * @param string $user The owner of the label to query
     * @param integer|string $id The numeric identifier or name of the label
     * @param array $data An associative array defining the label's properties; currently only "name" is understood
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     */
    public function labelPropertiesSet(string $user, $id, array $data, bool $byName = false): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->labelValidateId($user, $id, $byName, false);
        if (isset($data['name'])) {
            $this->labelValidateName($data['name']);
        }
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $valid = [
            'name'      => "str",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($data, $valid);
        if (!$setClause) {
            // if no changes would actually be applied, just return
            return false;
        }
        $out = (bool) $this->db->prepare("UPDATE arsse_labels set $setClause, modified = CURRENT_TIMESTAMP where owner = ? and $field = ?", $setTypes, "str", $type)->run($setValues, $user, $id)->changes();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
        }
        return $out;
    }

    /** Returns an indexed array of article identifiers assigned to a label
     *
     * @param string $user The owner of the label to query
     * @param integer|string $id The numeric identifier or name of the label
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     */
    public function labelArticlesGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // just do a syntactic check on the label ID
        $this->labelValidateId($user, $id, $byName, false);
        $field = !$byName ? "id" : "name";
        $type = !$byName ? "int" : "str";
        $out = $this->db->prepare("SELECT article from arsse_label_members join arsse_labels on label = id where assigned = 1 and $field = ? and owner = ? order by article", $type, "str")->run($id, $user)->getAll();
        if (!$out) {
            // if no results were returned, do a full validation on the label ID
            $this->labelValidateId($user, $id, $byName, true, true);
            // if the validation passes, return the empty result
            return $out;
        } else {
            // flatten the result to return just the article IDs in a simple array
            return array_column($out, "article");
        }
    }

    /** Makes or breaks associations between a given label and articles matching the given query context
     *
     * @param string $user The owner of the label
     * @param integer|string $id The numeric identifier or name of the label
     * @param Context $context The query context matching the desired articles
     * @param int $mode Whether to add (ASSOC_ADD), remove (ASSOC_REMOVE), or replace with (ASSOC_REPLACE) the matching associations
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     */
    public function labelArticlesSet(string $user, $id, Context $context, int $mode = self::ASSOC_ADD, bool $byName = false): int {
        assert(in_array($mode, [self::ASSOC_ADD, self::ASSOC_REMOVE, self::ASSOC_REPLACE]), new Exception("constantUnknown", $mode));
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate the tag ID, and get the numeric ID if matching by name
        $id = $this->labelValidateId($user, $id, $byName, true)['id'];
        // get the list of articles matching the context
        $articles = iterator_to_array($this->articleList($user, $context ?? new Context));
        // an empty article list is a special case
        if (!sizeof($articles)) {
            if ($mode == self::ASSOC_REPLACE) {
                // replacing with an empty set means setting everything to zero
                return $this->db->prepare("UPDATE arsse_label_members set assigned = 0, modified = CURRENT_TIMESTAMP where label = ? and assigned = 1", "int")->run($id)->changes();
            } else {
                // adding or removing is a no-op
                return 0;
            }
        } else {
            $articles = array_column($articles, "id");
        }
        // prepare up to three queries: removing requires one, adding two, and replacing three
        list($inClause, $inTypes, $inValues) = $this->generateIn($articles, "int");
        $updateQ = "UPDATE arsse_label_members set assigned = ?, modified = CURRENT_TIMESTAMP where label = ? and assigned <> ? and article %in% ($inClause)";
        $updateT = ["bool", "int", "bool", $inTypes];
        $insertQ = "INSERT INTO arsse_label_members(label,article,subscription) SELECT ?,a.id,s.id from arsse_articles as a join arsse_subscriptions as s on a.feed = s.feed where s.owner = ? and a.id not in (select article from arsse_label_members where label = ?) and a.id in ($inClause)";
        $insertT = ["int", "str", "int", $inTypes];
        $clearQ = str_replace("%in%", "not in", $updateQ);
        $clearT = $updateT;
        $updateQ = str_replace("%in%", "in", $updateQ);
        $qList = [];
        switch ($mode) {
            case self::ASSOC_REMOVE:
                $qList[] = [$updateQ, $updateT, [false, $id, false, $inValues]]; // soft-delete any existing associations
                break;
            case self::ASSOC_ADD:
                $qList[] = [$updateQ, $updateT, [true, $id, true, $inValues]]; // re-enable any previously soft-deleted association
                $qList[] = [$insertQ, $insertT, [$id, $user, $id, $inValues]]; // insert any newly-required associations
                break;
            case self::ASSOC_REPLACE:
                $qList[] = [$clearQ, $clearT, [false, $id, false, $inValues]]; // soft-delete any existing associations for articles not in the list
                $qList[] = [$updateQ, $updateT, [true, $id, true, $inValues]]; // re-enable any previously soft-deleted association
                $qList[] = [$insertQ, $insertT, [$id, $user, $id, $inValues]]; // insert any newly-required associations
                break;
        }
        // execute them in a transaction
        $out = 0;
        $tr = $this->begin();
        foreach ($qList as list($q, $t, $v)) {
            $out += $this->db->prepare($q, ...$t)->run(...$v)->changes();
        }
        $tr->commit();
        return $out;
    }

    /** Ensures the specified label identifier or name is valid (and optionally whether it exists) and raises an exception otherwise
     *
     * Returns an associative array containing the id, name of the label if it exists
     *
     * @param string $user The user who owns the label to be validated
     * @param integer|string $id The numeric identifier or name of the label to validate
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     * @param boolean $checkDb Whether to check whether the label exists (true) or only if the identifier or name is syntactically valid (false)
     * @param boolean $subject Whether the label is the subject (true) rather than the object (false) of the operation being performed; this only affects the semantics of the error message if validation fails
     */
    protected function labelValidateId(string $user, $id, bool $byName, bool $checkDb = true, bool $subject = false): array {
        if (!$byName && !ValueInfo::id($id)) {
            // if we're not referring to a label by name and the ID is invalid, throw an exception
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "label", 'type' => "int > 0"]);
        } elseif ($byName && !(ValueInfo::str($id) & ValueInfo::VALID)) {
            // otherwise if we are referring to a label by name but the ID is not a string, also throw an exception
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "label", 'type' => "string"]);
        } elseif ($checkDb) {
            $field = !$byName ? "id" : "name";
            $type = !$byName ? "int" : "str";
            $l = $this->db->prepare("SELECT id,name from arsse_labels where $field = ? and owner = ?", $type, "str")->run($id, $user)->getRow();
            if (!$l) {
                throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "label", 'id' => $id]);
            } else {
                return $l;
            }
        }
        return [
            'id'   => !$byName ? $id : null,
            'name' => $byName ? $id : null,
        ];
    }

    /** Ensures a prospective label name is syntactically valid and raises an exception otherwise */
    protected function labelValidateName($name): bool {
        $info = ValueInfo::str($name);
        if ($info & (ValueInfo::NULL | ValueInfo::EMPTY)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } elseif ($info & ValueInfo::WHITE) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } elseif (!($info & ValueInfo::VALID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "name", 'type' => "string"]);
        } else {
            return true;
        }
    }

    /** Creates a tag, and returns its numeric identifier
     *
     * Tags are discrete objects in the database and can be associated with multiple subscriptions; a subscription may in turn be associated with multiple tags
     *
     * @param string $user The user who will own the created tag
     * @param array $data An associative array defining the tag's properties; currently only "name" is understood
     */
    public function tagAdd(string $user, array $data): int {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate the tag name
        $name = array_key_exists("name", $data) ? $data['name'] : "";
        $this->tagValidateName($name, true);
        // perform the insert
        return $this->db->prepare("INSERT INTO arsse_tags(owner,name) values(?,?)", "str", "str")->run($user, $name)->lastId();
    }

    /** Lists a user's subscription tags
     *
     * The following keys are included in each record:
     *
     * - "id": The tag's numeric identifier
     * - "name" The tag's textual name
     * - "subscriptions": The count of subscriptions which have the tag assigned to them
     *
     * @param string $user The user whose tags are to be listed
     * @param boolean $includeEmpty Whether to include (true) or supress (false) tags which have no subscriptions assigned to them
     */
    public function tagList(string $user, bool $includeEmpty = true): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare(
            "SELECT * FROM (
                SELECT
                    id,name,coalesce(subscriptions,0) as subscriptions
                from arsse_tags 
                    left join (SELECT tag, sum(assigned) as subscriptions from arsse_tag_members group by tag) as tag_stats on tag_stats.tag = arsse_tags.id
                WHERE owner = ?
            ) as tag_data
            where subscriptions >= ? order by name
            ",
            "str",
            "int"
        )->run($user, !$includeEmpty);
    }

    /** Lists the associations between all tags and subscription
     *
     * The following keys are included in each record:
     *
     * - "tag_id": The tag's numeric identifier
     * - "tag_name" The tag's textual name
     * - "subscription_id": The numeric identifier of the associated subscription
     * - "subscription_name" The subscription's textual name
     *
     * @param string $user The user whose tags are to be listed
     */
    public function tagSummarize(string $user): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare(
            "SELECT
                arsse_tags.id as id,
                arsse_tags.name as name,
                arsse_tag_members.subscription as subscription
            FROM arsse_tag_members
                join arsse_tags on arsse_tags.id = arsse_tag_members.tag
            WHERE arsse_tags.owner = ? and assigned = 1",
            "str"
        )->run($user);
    }

    /** Deletes a tag from the database
     *
     * Any subscriptions associated with the tag remains untouched
     *
     * @param string $user The owner of the tag to remove
     * @param integer|string $id The numeric identifier or name of the tag
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     */
    public function tagRemove(string $user, $id, bool $byName = false): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->tagValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $changes = $this->db->prepare("DELETE FROM arsse_tags where owner = ? and $field = ?", "str", $type)->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "tag", 'id' => $id]);
        }
        return true;
    }

    /** Retrieves the properties of a tag
     *
     * The following keys are included in the output array:
     *
     * - "id": The tag's numeric identifier
     * - "name" The tag's textual name
     * - "subscriptions": The count of subscriptions which have the tag assigned to them
     *
     * @param string $user The owner of the tag to remove
     * @param integer|string $id The numeric identifier or name of the tag
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     */
    public function tagPropertiesGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->tagValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $out = $this->db->prepare(
            "SELECT
                id,name,coalesce(subscriptions,0) as subscriptions
            FROM arsse_tags
                left join (SELECT tag, sum(assigned) as subscriptions from arsse_tag_members group by tag) as tag_stats on tag_stats.tag = arsse_tags.id
            WHERE $field = ? and owner = ?
            ",
            $type,
            "str"
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "tag", 'id' => $id]);
        }
        return $out;
    }

    /** Sets the properties of a tag
     *
     * @param string $user The owner of the tag to query
     * @param integer|string $id The numeric identifier or name of the tag
     * @param array $data An associative array defining the tag's properties; currently only "name" is understood
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     */
    public function tagPropertiesSet(string $user, $id, array $data, bool $byName = false): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->tagValidateId($user, $id, $byName, false);
        if (isset($data['name'])) {
            $this->tagValidateName($data['name']);
        }
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $valid = [
            'name'      => "str",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($data, $valid);
        if (!$setClause) {
            // if no changes would actually be applied, just return
            return false;
        }
        $out = (bool) $this->db->prepare("UPDATE arsse_tags set $setClause, modified = CURRENT_TIMESTAMP where owner = ? and $field = ?", $setTypes, "str", $type)->run($setValues, $user, $id)->changes();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "tag", 'id' => $id]);
        }
        return $out;
    }

    /** Returns an indexed array of subscription identifiers assigned to a tag
     *
     * @param string $user The owner of the tag to query
     * @param integer|string $id The numeric identifier or name of the tag
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     */
    public function tagSubscriptionsGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // just do a syntactic check on the tag ID
        $this->tagValidateId($user, $id, $byName, false);
        $field = !$byName ? "id" : "name";
        $type = !$byName ? "int" : "str";
        $out = $this->db->prepare("SELECT subscription from arsse_tag_members join arsse_tags on tag = id where assigned = 1 and $field = ? and owner = ? order by subscription", $type, "str")->run($id, $user)->getAll();
        if (!$out) {
            // if no results were returned, do a full validation on the tag ID
            $this->tagValidateId($user, $id, $byName, true, true);
            // if the validation passes, return the empty result
            return $out;
        } else {
            // flatten the result to return just the subscription IDs in a simple array
            return array_column($out, "subscription");
        }
    }

    /** Makes or breaks associations between a given tag and specified subscriptions
     *
     * @param string $user The owner of the tag
     * @param integer|string $id The numeric identifier or name of the tag
     * @param integer[] $subscriptions An array listing the desired subscriptions
     * @param int $mode Whether to add (ASSOC_ADD), remove (ASSOC_REMOVE), or replace with (ASSOC_REPLACE) the listed associations
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     */
    public function tagSubscriptionsSet(string $user, $id, array $subscriptions, int $mode = self::ASSOC_ADD, bool $byName = false): int {
        assert(in_array($mode, [self::ASSOC_ADD, self::ASSOC_REMOVE, self::ASSOC_REPLACE]), new Exception("constantUnknown", $mode));
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate the tag ID, and get the numeric ID if matching by name
        $id = $this->tagValidateId($user, $id, $byName, true)['id'];
        // an empty subscription list is a special case
        if (!sizeof($subscriptions)) {
            if ($mode == self::ASSOC_REPLACE) {
                // replacing with an empty set means setting everything to zero
                return $this->db->prepare("UPDATE arsse_tag_members set assigned = 0, modified = CURRENT_TIMESTAMP where tag = ? and assigned = 1", "int")->run($id)->changes();
            } else {
                // adding or removing is a no-op
                return 0;
            }
        }
        // prepare up to three queries: removing requires one, adding two, and replacing three
        list($inClause, $inTypes, $inValues) = $this->generateIn($subscriptions, "int");
        $updateQ = "UPDATE arsse_tag_members set assigned = ?, modified = CURRENT_TIMESTAMP where tag = ? and assigned <> ? and subscription in (select id from arsse_subscriptions where owner = ? and id %in% ($inClause))";
        $updateT = ["bool", "int", "bool", "str", $inTypes];
        $insertQ = "INSERT INTO arsse_tag_members(tag,subscription) SELECT ?,id from arsse_subscriptions where id not in (select subscription from arsse_tag_members where tag = ?) and owner = ? and id in ($inClause)";
        $insertT = ["int", "int", "str", $inTypes];
        $clearQ = str_replace("%in%", "not in", $updateQ);
        $clearT = $updateT;
        $updateQ = str_replace("%in%", "in", $updateQ);
        $qList = [];
        switch ($mode) {
            case self::ASSOC_REMOVE:
                $qList[] = [$updateQ, $updateT, [0, $id, 0, $user, $inValues]]; // soft-delete any existing associations
                break;
            case self::ASSOC_ADD:
                $qList[] = [$updateQ, $updateT, [1, $id, 1, $user, $inValues]]; // re-enable any previously soft-deleted association
                $qList[] = [$insertQ, $insertT, [$id, $id, $user, $inValues]]; // insert any newly-required associations
                break;
            case self::ASSOC_REPLACE:
                $qList[] = [$clearQ, $clearT, [0, $id, 0, $user, $inValues]]; // soft-delete any existing associations for subscriptions not in the list
                $qList[] = [$updateQ, $updateT, [1, $id, 1, $user, $inValues]]; // re-enable any previously soft-deleted association
                $qList[] = [$insertQ, $insertT, [$id, $id, $user, $inValues]]; // insert any newly-required associations
                break;
        }
        // execute them in a transaction
        $out = 0;
        $tr = $this->begin();
        foreach ($qList as list($q, $t, $v)) {
            $out += $this->db->prepare($q, ...$t)->run(...$v)->changes();
        }
        $tr->commit();
        return $out;
    }

    /** Ensures the specified tag identifier or name is valid (and optionally whether it exists) and raises an exception otherwise
     *
     * Returns an associative array containing the id, name of the tag if it exists
     *
     * @param string $user The user who owns the tag to be validated
     * @param integer|string $id The numeric identifier or name of the tag to validate
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     * @param boolean $checkDb Whether to check whether the tag exists (true) or only if the identifier or name is syntactically valid (false)
     * @param boolean $subject Whether the tag is the subject (true) rather than the object (false) of the operation being performed; this only affects the semantics of the error message if validation fails
     */
    protected function tagValidateId(string $user, $id, bool $byName, bool $checkDb = true, bool $subject = false): array {
        if (!$byName && !ValueInfo::id($id)) {
            // if we're not referring to a tag by name and the ID is invalid, throw an exception
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "tag", 'type' => "int > 0"]);
        } elseif ($byName && !(ValueInfo::str($id) & ValueInfo::VALID)) {
            // otherwise if we are referring to a tag by name but the ID is not a string, also throw an exception
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "tag", 'type' => "string"]);
        } elseif ($checkDb) {
            $field = !$byName ? "id" : "name";
            $type = !$byName ? "int" : "str";
            $l = $this->db->prepare("SELECT id,name from arsse_tags where $field = ? and owner = ?", $type, "str")->run($id, $user)->getRow();
            if (!$l) {
                throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "tag", 'id' => $id]);
            } else {
                return $l;
            }
        }
        return [
            'id'   => !$byName ? $id : null,
            'name' => $byName ? $id : null,
        ];
    }

    /** Ensures a prospective tag name is syntactically valid and raises an exception otherwise */
    protected function tagValidateName($name): bool {
        $info = ValueInfo::str($name);
        if ($info & (ValueInfo::NULL | ValueInfo::EMPTY)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } elseif ($info & ValueInfo::WHITE) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } elseif (!($info & ValueInfo::VALID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "name", 'type' => "string"]);
        } else {
            return true;
        }
    }
}
