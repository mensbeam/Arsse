<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\DrUUID\UUID;
use JKingWeb\Arsse\Db\Statement;
use JKingWeb\Arsse\Misc\Query;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

/** The high-level interface with the database
 * 
 * The database stores information on the following things:
 * 
 * - Users
 * - Subscriptions to feeds, which belong to users
 * - Folders, which belong to users and contain subscriptions
 * - Feeds to which users are subscribed
 * - Articles, which belong to feeds and for which users can only affect metadata
 * - Editions, identifying authorial modifications to articles
 * - Labels, which belong to users and can be assigned to multiple articles
 * - Sessions, used by some protocols to identify users across periods of time
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
    const SCHEMA_VERSION = 4;
    /** The maximum number of articles to mark in one query without chunking */
    const LIMIT_ARTICLES = 50;
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

    /** Conputes the contents of an SQL "IN()" clause, producing one parameter placeholder for each input value
     * 
     * Returns an indexed array containing the clause text, an array of types, and the array of values
     * 
     * @param array $values Arbitrary values
     * @param string $type A single data type applied to each value
     */
    protected function generateIn(array $values, string $type): array {
        $out = [
            "", // query clause
            [], // binding types
            $values, // binding values
        ];
        if (sizeof($values)) {
            // the query clause is just a series of question marks separated by commas
            $out[0] = implode(",", array_fill(0, sizeof($values), "?"));
            // the binding types are just a repetition of the supplied type
            $out[1] = array_fill(0, sizeof($values), $type);
        } else {
            // if the set is empty, some databases require an explicit null
            $out[0] = "null";
        }
        return $out;
    }

    /** Computes basic LIKE-based text search constraints for use in a WHERE clause
     * 
     * Returns an indexed array containing the clause text, an array of types, and another array of values
     * 
     * The clause is structured such that all terms must be present across any of the columns
     * 
     * @param string[] $terms The terms to search for
     * @param string[] $cols The columns to match against; these are -not- sanitized, so much -not- come directly from user input
     */
    protected function generateSearch(array $terms, array $cols): array {
        $clause = [];
        $types = [];
        $values = [];
        $like = $this->db->sqlToken("like");
        foreach($terms as $term) {
            $term = str_replace(["%", "_", "^"], ["^%", "^_", "^^"], $term);
            $term = "%$term%";
            $spec = [];
            foreach ($cols as $col) {
                $spec[] = "$col $like ? escape '^'";
                $types[] = "str";
                $values[] = $term;
            }
            $clause[] = "(".implode(" or ", $spec).")";
        }
        $clause = "(".implode(" and ", $clause).")";
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
    public function userPasswordGet(string $user): string {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return (string) $this->db->prepare("SELECT password from arsse_users where id = ?", "str")->run($user)->getValue();
    }

    /** Sets the password of an existing user
     * 
     * @param string $user The user for whom to set the password
     * @param string $password The new password, in cleartext. The password will be stored hashed
     */
    public function userPasswordSet(string $user, string $password): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $hash = (strlen($password) > 0) ? password_hash($password, \PASSWORD_DEFAULT) : "";
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
     * @param string $id The identifier of the session to destroy
     */
    public function sessionDestroy(string $user, string $id): bool {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // delete the session and report success.
        return (bool) $this->db->prepare("DELETE FROM arsse_sessions where id = ? and \"user\" = ?", "str", "str")->run($id, $user)->changes();
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
     * @param boolean $recursive Whether to list all descendents, or only direct children
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
                id,name,parent,
                (select count(*) from arsse_folders as parents where coalesce(parents.parent,0) = coalesce(arsse_folders.id,0)) as children,
                (select count(*) from arsse_subscriptions where coalesce(folder,0) = coalesce(arsse_folders.id,0)) as feeds
            FROM arsse_folders"
        );
        if (!$recursive) {
            $q->setWhere("owner = ?", "str", $user);
            $q->setWhere("coalesce(parent,0) = ?", "strict int", $parent);
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
     * @param boolean $subject Whether the folder is the subject rather than the object of the operation being performed; this only affects the semantics of the error message if validation fails
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
     * @param boolean $discovery Whether to perform newsfeed discovery if $url points to an HTML document
     */
    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = "", bool $discover = true): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // check to see if the feed exists
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
                feed,url,favicon,source,folder,pinned,err_count,err_msg,order_type,added,
                arsse_feeds.updated as updated,
                topmost.top as top_folder,
                coalesce(arsse_subscriptions.title, arsse_feeds.title) as title,
                (SELECT count(*) from arsse_articles where feed = arsse_subscriptions.feed) - (SELECT count(*) from arsse_marks where subscription = arsse_subscriptions.id and \"read\" = 1) as unread
             from arsse_subscriptions
                join userdata on userid = owner
                join arsse_feeds on feed = arsse_feeds.id
                left join topmost on folder=f_id"
        );
        $nocase = $this->db->sqlToken("nocase");
        $q->setOrder("pinned desc, coalesce(arsse_subscriptions.title, arsse_feeds.title) collate $nocase");
        // define common table expressions
        $q->setCTE("userdata(userid)", "SELECT ?", "str", $user);  // the subject user; this way we only have to pass it to prepare() once
        // topmost folders belonging to the user
        $q->setCTE("topmost(f_id,top)", "SELECT id,id from arsse_folders join userdata on owner = userid where parent is null union select id,top from arsse_folders join topmost on parent=f_id");
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
     * @param integer|null $id the numeric identifier of the subscription to modfify
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

    /** Ensures the specified subscription exists and raises an exception otherwise
     * 
     * Returns an associative array containing the id of the subscription and the id of the underlying newsfeed
     * 
     * @param string $user The user who owns the subscription to be validated
     * @param integer|null $id The identifier of the subscription to validate
     * @param boolean $subject Whether the subscription is the subject rather than the object of the operation being performed; this only affects the semantics of the error message if validation fails
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
            "UPDATE arsse_feeds SET url = ?, title = ?, favicon = ?, source = ?, updated = CURRENT_TIMESTAMP, modified = ?, etag = ?, err_count = 0, err_msg = '', next_fetch = ?, size = ? WHERE id = ?",
            'str',
            'str',
            'str',
            'str',
            'datetime',
            'str',
            'datetime',
            'int',
            'int'
        )->run(
            $feed->data->feedUrl,
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
        $this->db->query("UPDATE arsse_feeds set orphaned = null where exists(SELECT id from arsse_subscriptions where feed = arsse_feeds.id)");
        // next mark any newly orphaned feeds with the current date and time
        $this->db->query("UPDATE arsse_feeds set orphaned = CURRENT_TIMESTAMP where orphaned is null and not exists(SELECT id from arsse_subscriptions where feed = arsse_feeds.id)");
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
        list($cId, $tId)     = $this->generateIn($ids, "str");
        list($cHashUT, $tHashUT) = $this->generateIn($hashesUT, "str");
        list($cHashUC, $tHashUC) = $this->generateIn($hashesUC, "str");
        list($cHashTC, $tHashTC) = $this->generateIn($hashesTC, "str");
        // perform the query
        return $articles = $this->db->prepare(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed = ? and (guid in($cId) or url_title_hash in($cHashUT) or url_content_hash in($cHashUC) or title_content_hash in($cHashTC))",
            'int',
            $tId,
            $tHashUT,
            $tHashUC,
            $tHashTC
        )->run($feedID, $ids, $hashesUT, $hashesUC, $hashesTC);
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
            // dereference the label name to an ID
            $context->label((int) $this->labelValidateId($user, $context->labelName, true)['id']);
            $context->labelName(null);
        }
        // prepare the output column list; the column definitions are also used later
        $greatest = $this->db->sqlToken("greatest");
        $colDefs = [
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
            'marked_date' => "$greatest(arsse_articles.modified, coalesce(arsse_marks.modified, '0001-01-01 00:00:00'), coalesce(arsse_label_members.modified, '0001-01-01 00:00:00'))",
            'subscription_title' => "coalesce(arsse_subscriptions.title, arsse_feeds.title)",
            'media_url' => "arsse_enclosures.url",
            'media_type' => "arsse_enclosures.type",
        ];
        if (!$cols) {
            // if no columns are specified return a count
            $columns = "count(distinct arsse_articles.id) as count";
        } else {
            $columns = [];
            foreach ($cols as $col) {
                $col = trim(strtolower($col));
                if (!isset($colDefs[$col])) {
                    continue;
                }
                $columns[] = $colDefs[$col]." as ".$col;
            }
            $columns = implode(",", $columns);
        }
        // define the basic query, to which we add lots of stuff where necessary
        $q = new Query(
            "SELECT 
                $columns
            from arsse_articles
            join arsse_subscriptions on arsse_subscriptions.feed = arsse_articles.feed and arsse_subscriptions.owner = ?
            join arsse_feeds on arsse_subscriptions.feed = arsse_feeds.id
            left join arsse_marks on arsse_marks.subscription = arsse_subscriptions.id and arsse_marks.article = arsse_articles.id
            left join arsse_enclosures on arsse_enclosures.article = arsse_articles.id
            left join arsse_label_members on arsse_label_members.subscription = arsse_subscriptions.id and arsse_label_members.article = arsse_articles.id and arsse_label_members.assigned = 1
            left join arsse_labels on arsse_labels.owner = arsse_subscriptions.owner and arsse_label_members.label = arsse_labels.id",
            ["str"],
            [$user]
        );
        $q->setLimit($context->limit, $context->offset);
        $q->setCTE("latest_editions(article,edition)", "SELECT article,max(id) from arsse_editions group by article", [], [], "join latest_editions on arsse_articles.id = latest_editions.article");
        if ($cols) {
            // if there are no output columns requested we're getting a count and should not group, but otherwise we should
            $q->setGroup("arsse_articles.id", "arsse_marks.note", "arsse_enclosures.url", "arsse_enclosures.type", "arsse_subscriptions.title", "arsse_feeds.title", "arsse_subscriptions.id", "arsse_marks.modified", "arsse_label_members.modified", "arsse_marks.read", "arsse_marks.starred", "latest_editions.edition");
        }
        // handle the simple context options
        foreach ([
            // each context array consists of a column identifier (see $colDefs above), a comparison operator, a data type, and an upper bound if the value is an array
            "edition"          => ["edition",       "=",  "int",      1],
            "editions"         => ["edition",       "in", "int",      self::LIMIT_ARTICLES],
            "article"          => ["id",            "=",  "int",      1],
            "articles"         => ["id",            "in", "int",      self::LIMIT_ARTICLES],
            "oldestArticle"    => ["id",            ">=", "int",      1],
            "latestArticle"    => ["id",            "<=", "int",      1],
            "oldestEdition"    => ["edition",       ">=", "int",      1],
            "latestEdition"    => ["edition",       "<=", "int",      1],
            "modifiedSince"    => ["modified_date", ">=", "datetime", 1],
            "notModifiedSince" => ["modified_date", "<=", "datetime", 1],
            "markedSince"      => ["marked_date",   ">=", "datetime", 1],
            "notMarkedSince"   => ["marked_date",   "<=", "datetime", 1],
            "folderShallow"    => ["folder",        "=",  "int",      1],
            "subscription"     => ["subscription",  "=",  "int",      1],
            "unread"           => ["unread",        "=",  "bool",     1],
            "starred"          => ["starred",       "=",  "bool",     1],
        ] as $m => list($col, $op, $type, $max)) {
            if (!$context->$m()) {
                // context is not being used
                continue;
            } elseif (is_array($context->$m)) {
                if (!$context->$m) {
                    throw new Db\ExceptionInput("tooShort", ['field' => $m, 'action' => $this->caller(), 'min' => 1]); // must have at least one array element
                } elseif (sizeof($context->$m) > $max) {
                    throw new Db\ExceptionInput("tooLong", ['field' => $m, 'action' => $this->caller(), 'max' => $max]); // @codeCoverageIgnore
                }
                list($clause, $types, $values) = $this->generateIn($context->$m, $type);
                $q->setWhere("{$colDefs[$col]} $op ($clause)", $types, $values);
            } else {
                $q->setWhere("{$colDefs[$col]} $op ?", $type, $context->$m);
            }
        }
        // handle complex context options
        if ($context->labelled()) {
            // any label (true) or no label (false)
            $isOrIsNot = (!$context->labelled ? "is" : "is not");
            $q->setWhere("arsse_labels.id $isOrIsNot null");
        }
        if ($context->label()) {
            // label ID (label names are dereferenced during input validation above)
            $q->setWhere("arsse_labels.id = ?", "int", $context->label);
        }
        if ($context->annotated()) {
            $comp = ($context->annotated) ? "<>" : "=";
            $q->setWhere("coalesce(arsse_marks.note,'') $comp ''");
        }
        if ($context->folder()) {
            // add a common table expression to list the folder and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on parent = folder", "int", $context->folder);
            // limit subscriptions to the listed folders
            $q->setWhere("arsse_subscriptions.folder in (select folder from folders)");
        }
        // handle text-matching context options
        foreach ([
            "titleTerms"      => [10, ["arsse_articles.title"]],
            "searchTerms"     => [20, ["arsse_articles.title", "arsse_articles.content"]],
            "authorTerms"     => [10, ["arsse_articles.author"]],
            "annotationTerms" => [20, ["arsse_marks.note"]],
        ] as $m => list($max, $cols)) {
            if (!$context->$m()) {
                continue;
            } elseif (!$context->$m) {
                throw new Db\ExceptionInput("tooShort", ['field' => $m, 'action' => $this->caller(), 'min' => 1]); // must have at least one array element
            } elseif (sizeof($context->$m) > $max) {
                throw new Db\ExceptionInput("tooLong", ['field' => $m, 'action' => $this->caller(), 'max' => $max]);
            }
            $q->setWhere(...$this->generateSearch($context->$m, $cols));
        }
        // return the query
        return $q;
    }

    /** Chunk a context with more than the maximum number of articles or editions into an array of contexts */
    protected function contextChunk(Context $context): array {
        $exception = "";
        if ($context->editions()) {
            // editions take precedence over articles
            if (sizeof($context->editions) > self::LIMIT_ARTICLES) {
                $exception = "editions";
            }
        } elseif ($context->articles()) {
            if (sizeof($context->articles) > self::LIMIT_ARTICLES) {
                $exception = "articles";
            }
        }
        if ($exception) {
            $out = [];
            $list = array_chunk($context->$exception, self::LIMIT_ARTICLES);
            foreach ($list as $chunk) {
                $out[] = (clone $context)->$exception($chunk);
            }
            return $out;
        } else {
            return [];
        }
    }


    /** Lists articles in the database which match a given query context
     * 
     * If an empty column list is supplied, a count of articles is returned instead
     * 
     * @param string $user The user whose articles are to be listed
     * @param Context $context The search context
     * @param array $cols The columns to return in the result set, any of: id, edition, url, title, author, content, guid, fingerprint, folder, subscription, feed, starred, unread, note, published_date, edited_date, modified_date, marked_date, subscription_title, media_url, media_type
     */
    public function articleList(string $user, Context $context = null, array $fields = ["id"]): Db\Result {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        // if the context has more articles or editions than we can process in one query, perform a series of queries and return an aggregate result
        if ($contexts = $this->contextChunk($context)) {
            $out = [];
            $tr = $this->begin();
            foreach ($contexts as $context) {
                $out[] = $this->articleList($user, $context, $fields);
            }
            $tr->commit();
            return new Db\ResultAggregate(...$out);
        } else {
            $q = $this->articleQuery($user, $context, $fields);
            $q->setOrder("arsse_articles.edited".($context->reverse ? " desc" : ""));
            $q->setOrder("latest_editions.edition".($context->reverse ? " desc" : ""));
            // perform the query and return results
            return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
        }
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
        // if the context has more articles or editions than we can process in one query, perform a series of queries and return an aggregate result
        if ($contexts = $this->contextChunk($context)) {
            $out = 0;
            $tr = $this->begin();
            foreach ($contexts as $context) {
                $out += $this->articleCount($user, $context);
            }
            $tr->commit();
            return $out;
        } else {
            $q = $this->articleQuery($user, $context, []);
            return (int) $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
        }
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
        // if the context has more articles or editions than we can process in one query, perform a series of queries and return an aggregate result
        if ($contexts = $this->contextChunk($context)) {
            $out = 0;
            $tr = $this->begin();
            foreach ($contexts as $context) {
                $out += $this->articleMark($user, $data, $context);
            }
            $tr->commit();
            return $out;
        } else {
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
     * @param boolean $byName Whether to return the label names instead of the numeric label identifiers
     */
    public function articleLabelsGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $id = $this->articleValidateId($user, $id)['article'];
        $out = $this->db->prepare("SELECT id,name from arsse_labels where owner = ? and exists(select id from arsse_label_members where article = ? and label = arsse_labels.id and assigned = 1)", "str", "int")->run($user, $id)->getAll();
        // flatten the result to return just the label ID or name, sorted
        $out = $out ? array_column($out, !$byName ? "id" : "name") : [];
        sort($out);
        return $out;
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
            "WITH target_feed(id,subs) as (".
                "SELECT
                    id, (select count(*) from arsse_subscriptions where feed = arsse_feeds.id) as subs
                from arsse_feeds where id = ?".
            "), latest_editions(article,edition) as (".
                "SELECT article,max(id) from arsse_editions group by article".
            "), excepted_articles(id,edition) as (".
                "SELECT
                    arsse_articles.id as id,
                    latest_editions.edition as edition
                from arsse_articles
                    join target_feed on arsse_articles.feed = target_feed.id
                    join latest_editions on arsse_articles.id = latest_editions.article
                order by edition desc limit ?".
            ") ".
            "DELETE from arsse_articles where
                feed = (select max(id) from target_feed)
                and id not in (select id from excepted_articles)
                and (select count(*) from arsse_marks where article = arsse_articles.id and starred = 1) = 0
                and (
                    coalesce((select max(modified) from arsse_marks where article = arsse_articles.id),modified) <= ?
                    or ((select max(subs) from target_feed) = (select count(*) from arsse_marks where article = arsse_articles.id and \"read\" = 1) and coalesce((select max(modified) from arsse_marks where article = arsse_articles.id),modified) <= ?)
                )
            ",
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
        foreach ($feeds as $feed) {
            $query->run($feed['id'], $feed['size'], $limitUnread, $limitRead);
        }
        return true;
    }

    /** Ensures the specified article exists and raises an exception otherwise
     * 
     * Returns an associative array containing the id and latest edition of the article if it exists 
     * 
     * @param string $user The user who owns the article to be validated
     * @param integer|null $id The identifier of the article to validate
     */
    protected function articleValidateId(string $user, $id): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "article", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepare(
            "SELECT
                arsse_articles.id as article,
                (select max(id) from arsse_editions where article = arsse_articles.id) as edition
            FROM arsse_articles
                join arsse_feeds on arsse_feeds.id = arsse_articles.feed
                join arsse_subscriptions on arsse_subscriptions.feed = arsse_feeds.id
            WHERE
                arsse_articles.id = ? and arsse_subscriptions.owner = ?",
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
     * @param integer|null $id The identifier of the edition to validate
     */
    protected function articleValidateEdition(string $user, int $id): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "edition", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepare(
            "SELECT
                arsse_editions.id as edition,
                arsse_editions.article as article,
                (arsse_editions.id = (select max(id) from arsse_editions where article = arsse_editions.article)) as current
            FROM arsse_editions
                join arsse_articles on arsse_editions.article = arsse_articles.id
                join arsse_feeds on arsse_feeds.id = arsse_articles.feed
                join arsse_subscriptions on arsse_subscriptions.feed = arsse_feeds.id
            WHERE
                arsse_editions.id = ? and arsse_subscriptions.owner = ?",
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
        // if the context has more articles or editions than we can process in one query, perform a series of queries and return an aggregate result
        if ($contexts = $this->contextChunk($context)) {
            $articles = $editions = [];
            foreach ($contexts as $context) {
                $out = $this->editionArticle(...$context->editions);
                $editions = array_merge($editions, array_map("intval", array_keys($out)));
                $articles = array_merge($articles, array_map("intval", array_values($out)));
            }
            return array_combine($editions, $articles);
        } else {
            list($in, $inTypes) = $this->generateIn($context->editions, "int");
            $out = $this->db->prepare("SELECT id as edition, article from arsse_editions where id in($in)", $inTypes)->run($context->editions)->getAll();
            return $out ? array_combine(array_column($out, "edition"), array_column($out, "article")) : [];
        }
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
                    id,name,
                    (select count(*) from arsse_label_members where label = id and assigned = 1) as articles,
                    (select count(*) from arsse_label_members
                        join arsse_marks on arsse_label_members.article = arsse_marks.article and arsse_label_members.subscription = arsse_marks.subscription
                    where label = id and assigned = 1 and \"read\" = 1
                    ) as \"read\"
                FROM arsse_labels where owner = ?) as label_data
            where articles >= ? order by name
            ",
            "str",
            "int"
        )->run($user, !$includeEmpty);
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
                id,name,
                (select count(*) from arsse_label_members where label = id and assigned = 1) as articles,
                (select count(*) from arsse_label_members
                    join arsse_marks on arsse_label_members.article = arsse_marks.article and arsse_label_members.subscription = arsse_marks.subscription
                 where label = id and assigned = 1 and \"read\" = 1
                ) as \"read\"
            FROM arsse_labels where $field = ? and owner = ?
            ",
            $type,
            "str"
        )->run($id, $user)->getRow();
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
     * @param boolean $remove Whether to remove (true) rather than add (true) an association with the articles matching the context
     * @param boolean $byName Whether to interpret the $id parameter as the label's name (true) or identifier (false)
     */
    public function labelArticlesSet(string $user, $id, Context $context = null, bool $remove = false, bool $byName = false): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate the label ID, and get the numeric ID if matching by name
        $id = $this->labelValidateId($user, $id, $byName, true)['id'];
        $context = $context ?? new Context;
        $out = 0;
        // wrap this UPDATE and INSERT together into a transaction
        $tr = $this->begin();
        // first update any existing entries with the removal or re-addition of their association
        $q = $this->articleQuery($user, $context);
        $q->setWhere("exists(select article from arsse_label_members where label = ? and article = arsse_articles.id)", "int", $id);
        $q->pushCTE("target_articles");
        $q->setBody(
            "UPDATE arsse_label_members set assigned = ?, modified = CURRENT_TIMESTAMP where label = ? and assigned <> ? and article in (select id from target_articles)",
            ["bool","int","bool"],
            [!$remove, $id, !$remove]
        );
        $out += $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        // next, if we're not removing, add any new entries that need to be added
        if (!$remove) {
            $q = $this->articleQuery($user, $context, ["id", "feed"]);
            $q->setWhere("not exists(select article from arsse_label_members where label = ? and article = arsse_articles.id)", "int", $id);
            $q->pushCTE("target_articles");
            $q->setBody(
                "SELECT
                    ?,id,
                    (select id from arsse_subscriptions where owner = ? and arsse_subscriptions.feed = target_articles.feed)
                FROM target_articles",
                ["int", "str"],
                [$id, $user]
            );
            $out += $this->db->prepare("INSERT INTO arsse_label_members(label,article,subscription) ".$q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        }
        // commit the transaction
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
     * @param boolean $subject Whether the label is the subject rather than the object of the operation being performed; this only affects the semantics of the error message if validation fails
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
}
