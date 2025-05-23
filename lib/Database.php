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
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\Misc\QueryFilter;
use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\Misc\URL;
use JKingWeb\Arsse\Rule\Rule;
use JKingWeb\Arsse\Rule\Exception as RuleException;

/** The high-level interface with the database
 *
 * The database stores information on the following things:
 *
 * - Users
 * - Subscriptions to feeds, which belong to users
 * - Folders, which belong to users and contain subscriptions or other folders
 * - Tags, which belong to users and can be assigned to multiple subscriptions
 * - Icons, which are associated with subscriptions
 * - Articles, which belong to subscriptions
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
 * concerns, will typically follow different conventions.
 *
 * Note that operations on users should be performed with the User class rather
 * than the Database class directly. This is to allow for alternate user
 * databases e.g. LDAP, although not such support for alternatives exists yet.
 */
class Database {
    /** The version number of the latest schema the interface is aware of */
    public const SCHEMA_VERSION = 8;
    /** Makes tag/label association change operations remove members */
    public const ASSOC_REMOVE = 0;
    /** Makes tag/label association change operations add members */
    public const ASSOC_ADD = 1;
    /** Makes tag/label association change operations replace members */
    public const ASSOC_REPLACE = 2;
    /** A map of database driver short-names and their associated class names */
    public const DRIVER_NAMES = [
        'sqlite3'    => \JKingWeb\Arsse\Db\SQLite3\Driver::class,
        'postgresql' => \JKingWeb\Arsse\Db\PostgreSQL\Driver::class,
        'mysql'      => \JKingWeb\Arsse\Db\MySQL\Driver::class,
    ];
    /** The size of a set of values beyond which the set will be embedded into the query text */
    protected const LIMIT_SET_SIZE = 25;
    /** The length of a string in an embedded set beyond which a parameter placeholder will be used for the string */
    protected const LIMIT_SET_STRING_LENGTH = 200;

    /** @var Db\Driver */
    public $db;

    /** Constructs the database interface
     *
     * @param boolean $initialize Whether to attempt to upgrade the databse schema when constructing
     */
    public function __construct($initialize = true) {
        $driver = Arsse::$conf->dbDriver;
        $this->db = $driver::create();
        $this->checkSchemaVersion($initialize);
    }

    public function checkSchemaVersion(bool $initialize = false): void {
        $ver = $this->db->schemaVersion();
        if ($initialize) {
            if ($ver < self::SCHEMA_VERSION) {
                $this->db->schemaUpdate(self::SCHEMA_VERSION);
            } elseif ($ver != self::SCHEMA_VERSION) {// @codeCoverageIgnore
                // This will only occur if an old version of the software is used with a newer database schema
                throw new Db\Exception("updateSchemaDowngrade"); // @codeCoverageIgnore
            }
        }
    }

    /** Returns the bare name of the calling context's calling method, when __FUNCTION__ is not appropriate */
    protected function caller(): string {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $out = "";
        foreach ($trace as $step) {
            if (($step['class'] ?? "")  === __CLASS__) {
                $out = $step['function'];
            } else {
                break;
            }
        }
        return $out;
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
                $v = V::normalize($v, $convType, null, "sql");
                if (is_null($v)) {
                    // nulls are pointless to have
                    continue;
                } elseif (is_string($v)) {
                    if (strlen($v) > self::LIMIT_SET_STRING_LENGTH || strpos($v, "?") !== false) {
                        $clause[] = "?";
                        $params[] = $v;
                    } else {
                        $clause[] = $this->db->literalString($v);
                    }
                } else {
                    $clause[] = V::normalize($v, V::T_STRING, null, "sql");
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
     * @param string[] $cols The columns to match against; these are -not- sanitized, so must -not- come directly from user input
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
            $embedTerm = ($embedSet && strlen($term) <= self::LIMIT_SET_STRING_LENGTH && strpos($term, "?") === false);
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
    public function metaGet(string $key): ?string {
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
        return (bool) $this->db->prepare("SELECT count(*) from arsse_users where id = ?", "str")->run($user)->getValue();
    }

    /** Returns the username associated with a user number */
    public function userLookup(int $num): string {
        $out = $this->db->prepare("SELECT id from arsse_users where num = ?", "int")->run($num)->getValue();
        if ($out === null) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $num]);
        }
        return $out;
    }

    /** Adds a user to the database
     *
     * @param string $user The user to add
     * @param string|null $passwordThe user's password in cleartext. It will be stored hashed. If null is provided the user will not be able to log in
     */
    public function userAdd(string $user, ?string $password): bool {
        if ($this->userExists($user)) {
            throw new User\ExceptionConflict("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        }
        $hash = (strlen($password) > 0) ? password_hash($password, \PASSWORD_DEFAULT) : "";
        // NOTE: This roundabout construction (with 'select' rather than 'values') is required by MySQL, because MySQL is riddled with pitfalls and exceptions
        $this->db->prepare("INSERT INTO arsse_users(id,password,num) select ?, ?, (coalesce((select max(num) from arsse_users), 0) + 1)", "str", "str")->runArray([$user,$hash]);
        return true;
    }

    /** Renames a user
     *
     * This does not have an effect on their numeric ID, but has a cascading effect on many tables
     */
    public function userRename(string $user, string $name): bool {
        if ($user === $name) {
            return false;
        }
        try {
            if (!$this->db->prepare("UPDATE arsse_users set id = ? where id = ?", "str", "str")->run($name, $user)->changes()) {
                throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
            }
        } catch (Db\ExceptionInput $e) {
            throw new User\ExceptionConflict("alreadyExists", ["action" => __FUNCTION__, "user" => $name], $e);
        }
        return true;
    }

    /** Removes a user from the database */
    public function userRemove(string $user): bool {
        if ($this->db->prepare("DELETE from arsse_users where id = ?", "str")->run($user)->changes() < 1) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return true;
    }

    /** Returns a flat, indexed array of all users in the database */
    public function userList(): array {
        $out = [];
        foreach ($this->db->query("SELECT id from arsse_users") as $user) {
            $out[] = $user['id'];
        }
        return $out;
    }

    /** Retrieves the hashed password of a user */
    public function userPasswordGet(string $user): ?string {
        if (!$this->userExists($user)) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare("SELECT password from arsse_users where id = ?", "str")->run($user)->getValue();
    }

    /** Sets the password of an existing user
     *
     * @param string $user The user for whom to set the password
     * @param string|null $password The new password, in cleartext. The password will be stored hashed. If null is passed, the password is unset and authentication not possible
     */
    public function userPasswordSet(string $user, ?string $password): bool {
        if (!$this->userExists($user)) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $hash = (strlen($password ?? "") > 0) ? password_hash($password, \PASSWORD_DEFAULT) : $password;
        $this->db->prepare("UPDATE arsse_users set password = ? where id = ?", "str", "str")->run($hash, $user);
        return true;
    }

    /** Retrieves any metadata associated with a user
     *
     * @param string $user The user whose metadata is to be retrieved
     */
    public function userPropertiesGet(string $user): array {
        $basic = $this->db->prepare("SELECT num, admin from arsse_users where id = ?", "str")->run($user)->getRow();
        if (!$basic) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $exclude = ["num", "admin"];
        [$inClause, $inTypes, $inValues] = $this->generateIn($exclude, "str");
        $meta = $this->db->prepare("SELECT \"key\", value from arsse_user_meta where owner = ? and \"key\" not in ($inClause) order by \"key\"", "str", $inTypes)->run($user, $inValues)->getAll();
        $meta = array_merge($basic, array_combine(array_column($meta, "key"), array_column($meta, "value")));
        settype($meta['num'], "integer");
        settype($meta['admin'], "integer");
        return $meta;
    }

    /** Set one or more metadata properties for a user
     *
     * @param string $user The user whose metadata is to be set
     * @param array $data An associative array of property names and values
     */
    public function userPropertiesSet(string $user, array $data): bool {
        if (!$this->userExists($user)) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $tr = $this->begin();
        $find = $this->db->prepare("SELECT count(*) from arsse_user_meta where owner = ? and \"key\" = ?", "str", "strict str");
        $update = $this->db->prepare("UPDATE arsse_user_meta set value = ?, modified = CURRENT_TIMESTAMP where owner = ? and \"key\" = ?", "str", "str", "str");
        $insert = $this->db->prepare("INSERT INTO arsse_user_meta(owner, \"key\", value) values(?, ?, ?)", "str", "strict str", "str");
        foreach ($data as $k => $v) {
            if ($k === "admin") {
                $this->db->prepare("UPDATE arsse_users SET admin = ? where id = ?", "bool", "str")->run($v, $user);
            } elseif ($k === "num") {
                continue;
            } else {
                if ($find->run($user, $k)->getValue()) {
                    $update->run($v, $user, $k);
                } else {
                    $insert->run($user, $k, $v);
                }
            }
        }
        $tr->commit();
        return true;
    }

    /** Creates a new session for the given user and returns the session identifier */
    public function sessionCreate(string $user): string {
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
    public function sessionDestroy(string $user, ?string $id = null): bool {
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
        return ($now + $diff) >= $expiry->getTimestamp();
    }

    /** Creates a new token for the given user in the given class
     *
     * @param string $user The user for whom to create the token
     * @param string $class The class of the token e.g. the protocol name
     * @param string|null $id The value of the token; if none is provided a UUID will be generated
     * @param \DateTimeInterface|null $expires An optional expiry date and time for the token
     * @param string $data Application-specific data associated with a token
     */
    public function tokenCreate(string $user, string $class, ?string $id = null, ?\DateTimeInterface $expires = null, ?string $data = null): string {
        if (!$this->userExists($user)) {
            throw new User\ExceptionConflict("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        // generate a token if it's not provided
        $id = $id ?? UUID::mint()->hex;
        // save the token to the database
        $this->db->prepare("INSERT INTO arsse_tokens(id,class,\"user\",expires,data) values(?,?,?,?,?)", "str", "str", "str", "datetime", "str")->run($id, $class, $user, $expires, $data);
        // return the ID
        return $id;
    }

    /** Revokes one or all tokens for a user in a class
     *
     * @param string $user The user who owns the token to be revoked
     * @param string $class The class of the token e.g. the protocol name
     * @param string|null $id The ID of a specific token, or null for all tokens in the class
     */
    public function tokenRevoke(string $user, string $class, ?string $id = null): bool {
        if (is_null($id)) {
            $out = $this->db->prepare("DELETE FROM arsse_tokens where \"user\" = ? and class = ?", "str", "str")->run($user, $class)->changes();
        } else {
            $out = $this->db->prepare("DELETE FROM arsse_tokens where \"user\" = ? and class = ? and id = ?", "str", "str", "str")->run($user, $class, $id)->changes();
        }
        return (bool) $out;
    }

    /** Look up data associated with a token
     * 
     * @param string $class The type of token
     * @param string $id The token ID
     * @param ?string $user The user to whom the token belongs, if relevant. This parameter is useful for e.g. privilege tokens as opposed to login tokens
     */
    public function tokenLookup(string $class, string $id, ?string $user = null): array {
        $out = $this->db->prepare("SELECT id,class,\"user\",created,expires,data from arsse_tokens where class = ? and id = ? and \"user\" = coalesce(?, \"user\") and (expires is null or expires > CURRENT_TIMESTAMP)", "str", "str", "str")->run($class, $id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "token", 'id' => $id]);
        }
        return $out;
    }

    /** List tokens associated with a user */
    public function tokenList(string $user, string $class): Db\Result {
        return $this->db->prepare("SELECT id,created,expires,data from arsse_tokens where class = ? and \"user\" = ? and (expires is null or expires > CURRENT_TIMESTAMP)", "str", "str")->run($class, $user);
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
        // check to make sure the parent exists, if one is specified
        $parent = $this->folderValidateId($user, $parent)['id'];
        $q = new Query(
            "WITH RECURSIVE
            folders as (
                select id from arsse_folders where owner = ? and coalesce(parent,0) = ? union all select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id
            )
            select
                id,
                name,
                arsse_folders.parent as parent,
                coalesce(children,0) as children, 
                coalesce(feeds,0) as feeds
            from arsse_folders
            left join (select parent,count(id) as children from arsse_folders group by parent) as child_stats on child_stats.parent = arsse_folders.id
            left join (select folder,count(id) as feeds from arsse_subscriptions where deleted = 0 group by folder) as sub_stats on sub_stats.folder = arsse_folders.id",
            ["str", "strict int"],
            [$user, $parent]
        );
        if (!$recursive) {
            $q->setWhere("owner = ?", "str", $user);
            $q->setWhere("coalesce(arsse_folders.parent,0) = ?", "strict int", $parent);
        } else {
            $q->setWhere("id in (select id from folders)");
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
        if (!V::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "folder", 'type' => "int > 0"]);
        }
        $changes = $this->db->prepare("DELETE FROM arsse_folders where owner = ? and id = ?", "str", "int")->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        }
        return true;
    }

    /** Returns the identifier, name, and parent of the given folder as an associative array */
    public function folderPropertiesGet(string $user, $id): array {
        if (!V::id($id)) {
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
            'name'   => "str",
            'parent' => "int",
        ];
        [$setClause, $setTypes, $setValues] = $this->generateSet($in, $valid);
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
        if (!V::id($id, true)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "folder", 'type' => "int >= 0"]);
        }
        // if a null or zero ID is specified this is always acceptable
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
    protected function folderValidateMove(string $user, $id = null, $parent = null, ?string $name = null): ?int {
        $errData = ["action" => $this->caller(), "field" => "parent", 'id' => $parent];
        if (!$id) {
            // the root cannot be moved
            throw new Db\ExceptionInput("circularDependence", $errData);
        }
        $info = V::int($parent);
        // the root is always a valid parent
        if ($info & (V::NULL | V::ZERO)) {
            $parent = null;
        } else {
            // if a negative integer or non-integer is specified this will always fail
            if (!($info & V::VALID) || (($info & V::NEG))) {
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
        $p = $this->db->prepareArray(
            "WITH RECURSIVE
            target as (
                select ? as userid, ? as source, ? as dest, ? as new_name
            ),
            folders as (
                select id from arsse_folders join target on owner = userid and coalesce(parent,0) = source 
                union all 
                select arsse_folders.id as id from arsse_folders join folders on arsse_folders.parent=folders.id
            )
            select
                case when 
                    ((select dest from target) is null or exists(select id from arsse_folders join target on owner = userid and coalesce(id,0) = coalesce(dest,0))) 
                then 1 else 0 end as extant,
                case when 
                    not exists(select id from folders where id = coalesce((select dest from target),0)) 
                then 1 else 0 end as valid,
                case when 
                    not exists(select id from arsse_folders join target on coalesce(parent,0) = coalesce(dest,0) and name = coalesce((select new_name from target),(select name from arsse_folders join target on id = source))) 
                then 1 else 0 end as available",
            ["str", "strict int", "int", "str"]
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
        $info = V::str($name);
        if ($info & (V::NULL | V::EMPTY)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } elseif ($info & V::WHITE) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } elseif (!($info & V::VALID)) {
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
     * This is an all-in-one operation which reserves an ID, sets the subscription's 
     * properties, and based on whether the feed is fetched successfully, either makes
     * the subscription available to the user, or deletes the reservation.
     *
     * @param string $user The user who will own the subscription
     * @param string $url The URL of the newsfeed or discovery source
     * @param boolean $discover Whether to perform newsfeed discovery if $url points to an HTML document
     * @param array $properties An associative array of properties accepted by the `subscriptionPropertiesSet` function
     */
    public function subscriptionAdd(string $user, string $url, bool $discover = true, array $properties = []): int {
        $id = $this->subscriptionReserve($user, $url, $discover, $properties);
        try {
            if ($properties) {
                $this->subscriptionPropertiesSet($user, $id, $properties, true);
            }
            $this->subscriptionUpdate($user, $id, true);
            $this->subscriptionReveal($user, $id);
        } catch (\Throwable $e) {
            // if the process failed, the subscription should be deleted immediately rather than cluttering up the database
            $this->db->prepare("DELETE from arsse_subscriptions where id = ?", "int")->run($id);
            throw $e;
        }
        return $id;
    }

    /** Adds a subscription to the database without exposing it to the user, returning its ID
     *
     * If the subscription already exists in the database an exception is thrown, unless the 
     * subscription was soft-deleted; in this case the the existing ID is returned without 
     * clearing the delete flag
     * 
     * This function can used  with `subscriptionUpdate` and `subscriptionReveal` to simulate
     * atomic addition (or rollback) of multiple newsfeeds, something which is not normally
     * practical due to network retrieval and processing times
     *
     * @param string $user The user who will own the subscription
     * @param string $url The URL of the newsfeed or discovery source
     * @param boolean $discover Whether to perform newsfeed discovery if $url points to an HTML document
     * @param array $properties An associative array of properties accepted by the `subscriptionPropertiesSet` function
     */
    public function subscriptionReserve(string $user, string $url, bool $discover = true, array $properties = []): int {
        // validate the feed username
        if (HTTP::userInvalid($properties['username'] ?? "")) {
            throw new Db\ExceptionInput("invalidValue", ["action" => __FUNCTION__, "field" => "fetchUser"]);
        }
        // normalize the input URL
        $url = URL::normalize($url, $properties['username'] ?? null, $properties['password'] ?? null);
        // if discovery is enabled, check to see if the feed already exists; this will save us some network latency if it does
        if ($discover) {
            $id = $this->db->prepare("SELECT id from arsse_subscriptions where owner = ? and url = ?", "str", "str")->run($user, $url)->getValue();
            if (!$id) {
                // if it doesn't exist, perform discovery
                $url = Feed::discover($url, $properties['user_agent'] ?? null, $properties['cookie'] ?? null);
            }
        }
        try {
            return (int) $this->db->prepare('INSERT INTO arsse_subscriptions(owner, url, deleted) values(?,?,?)', 'str', 'str', 'bool')->run($user, $url, 1)->lastId();
        } catch (Db\ExceptionInput $e) {
            // if the insertion fails, throw if the delete flag is not set, otherwise return the existing ID
            $id = $this->db->prepare("SELECT id from arsse_subscriptions where owner = ? and url = ? and deleted = 1", "str", "str")->run($user, $url)->getValue();
            if (!$id) {
                throw $e;
            } else {
                // set the modification timestamp to the current time so it doesn't get cleaned up too soon
                $this->db->prepare("UPDATE arsse_subscriptions set modified = CURRENT_TIMESTAMP where id = ?", "int")->run($id);
            }
            return (int) $id;
        }
    }

    /** Clears the soft-delete flag from one or more subscriptions, making them visible to the user
     * 
     * @param string $user The user whose subscriptions to reveal
     * @param int $id The numerical identifier(s) of the subscription(s) to reveal
     */
    public function subscriptionReveal(string $user, int ...$id): void {
        [$inClause, $inTypes, $inValues] = $this->generateIn($id, "int");
        $this->db->prepare("UPDATE arsse_subscriptions set deleted = 0,  modified = CURRENT_TIMESTAMP where deleted = 1 and owner = ? and id in ($inClause)", "str", $inTypes)->run($user, $inValues);
    }

    /** Retrieves the ID of the subscription with the supplied URL for the given user, if any
     * 
     * @param string $user The user whose subscription to look up
     * @param string $url The URL of the subscription. This should include username and password, where appropriate
     */
    public function subscriptionLookup(string $user, string $url): int {
        $id = $this->db->prepare("SELECT id from arsse_subscriptions where owner = ? and url = ? and deleted = 0", "str", "str")->run($user, $url)->getValue();
        if (!$id) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "subscription", 'id' => $id]);
        }
        return (int) $id;
    }


    /** Lists a user's subscriptions, returning various data
     *
     * Each record has the following keys:
     *
     * - "id": The numeric identifier of the subscription
     * - "feed": The numeric identifier of the subscription (historical)
     * - "url": The URL of the newsfeed, after discovery and HTTP redirects, with username and password embedded where applicable
     * - "title": The title of the newsfeed
     * - "source": The URL of the source of the newsfeed i.e. its parent Web site
     * - "icon_id": The numeric identifier of an icon representing the newsfeed or its source
     * - "icon_url": The URL of an icon representing the newsfeed or its source
     * - "folder": The numeric identifier (or null) of the subscription's folder
     * - "top_folder": The numeric identifier (or null) of the top-level folder for the subscription
     * - "pinned": Whether the subscription is pinned
     * - "err_count": The count of times attempting to refresh the newsfeed has resulted in an error since the last successful retrieval
     * - "err_msg": The error message of the last unsuccessful retrieval
     * - "order_type": Whether articles should be sorted in reverse cronological order (2), chronological order (1), or the default (0)
     * - "keep_rule": The subscription's "keep" filter rule; articles which do not match this are hidden
     * - "block_rule": The subscription's "block" filter rule; articles which match this are hidden
     * - "user_agent": An HTTP User-Agent value to use when fetching the feed rather than the default
     * - "cookie": The cookie value, if any, which is sent when fetching feeds
     * - "added": The date and time at which the subscription was added
     * - "updated": The date and time at which the newsfeed was last updated in the database
     * - "edited": The date and time at which the newsfeed was last modified by its authors
     * - "modified": The date and time at which the subscription properties were last changed by the user
     * - "next_fetch": The date and time and which the feed will next be fetched
     * - "etag": The ETag header-field in the last fetch response
     * - "scrape": Whether the user wants scrape full-article content
     * - "read": The number of read articles associated with the subscription
     * - "unread": The number of unread articles associated with the subscription
     * - "article_modified": The most recent modification date among articles belonging to the subscription
     *
     * @param string $user The user whose subscriptions are to be listed
     * @param integer|null $folder The identifier of the folder under which to list subscriptions; by default the root folder is used
     * @param boolean $recursive Whether to list subscriptions of descendent folders as well as the selected folder
     * @param integer|null $id The numeric identifier of a particular subscription; used internally by subscriptionPropertiesGet
     */
    public function subscriptionList(string $user, $folder = null, bool $recursive = true, ?int $id = null): Db\Result {
        // validate inputs
        $folder = $this->folderValidateId($user, $folder)['id'];
        // compile the query
        $integerType = $this->db->sqlToken("integer");
        $q = new Query(
            "WITH RECURSIVE
            topmost(f_id, top) as (
                select id,id from arsse_folders where owner = ? and parent is null union all select id,top from arsse_folders join topmost on parent=f_id
            ),
            folders(folder) as (
                select ? union all select id from arsse_folders join folders on parent = folder
            )
            select
                s.id as id,
                s.id as feed,
                s.url,
                s.source,
                s.pinned,
                s.err_count,
                s.err_msg,
                s.order_type,
                s.added,
                s.keep_rule,
                s.block_rule,
                s.user_agent,
                s.cookie,
                s.etag,
                s.updated as updated,
                s.modified as edited,
                s.modified as modified,
                s.next_fetch,
                s.scrape,
                case when i.data is not null then i.id end as icon_id,
                i.url as icon_url,
                folder, t.top as top_folder, d.name as folder_name, dt.name as top_folder_name,
                coalesce(s.title, s.feed_title) as title,
                cast(coalesce((articles - hidden - marked), coalesce(articles,0)) as $integerType) as unread, -- this cast is required for MySQL for unclear reasons
                cast(coalesce(marked,0) as $integerType) as \"read\", -- this cast is required for MySQL for unclear reasons
                article_modified
            from arsse_subscriptions as s
                left join topmost as t on t.f_id = s.folder
                left join arsse_folders as d on s.folder = d.id
                left join arsse_folders as dt on t.top = dt.id
                left join arsse_icons as i on i.id = s.icon
                left join (
                    select 
                        subscription,
                        count(*) as articles,
                        sum(hidden) as hidden,
                        sum(case when \"read\" = 1 and hidden = 0 then 1 else 0 end) as marked,
                        max(modified) as article_modified
                    from arsse_articles
                    group by subscription
                ) as article_stats on article_stats.subscription = s.id",
            ["str", "int"],
            [$user, $folder]
        );
        $q->setWhere("s.owner = ?", ["str"], [$user]);
        $q->setWhere("s.deleted = 0");
        $nocase = $this->db->sqlToken("nocase");
        $q->setOrder("pinned desc, coalesce(s.title, s.feed_title) collate $nocase");
        if ($id) {
            // if an ID is specified, add a suitable WHERE condition and bindings
            // this condition facilitates the implementation of subscriptionPropertiesGet, which would otherwise have to duplicate the complex query; it takes precedence over a specified folder
            $q->setWhere("s.id = ?", "int", $id);
        } elseif ($folder && $recursive) {
            // if a folder is specified and we're listing recursively, add a suitable WHERE condition
            $q->setWhere("folder in (select folder from folders)");
        } elseif (!$recursive) {
            // if we're not listing recursively, match against only the specified folder (even if it is null)
            $q->setWhere("coalesce(folder,0) = ?", "strict int", $folder);
        }
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    /** Returns the number of subscriptions in a folder, counting recursively
     *
     * @param string $user The user whose subscriptions are to be counted
     * @param integer|null $folder The identifier of the folder under which to count subscriptions; by default the root folder is used
     */
    public function subscriptionCount(string $user, $folder = null): int {
        // validate inputs
        $folder = $this->folderValidateId($user, $folder)['id'];
        // create a complex query
        $q = new Query(
            "WITH RECURSIVE
            folders(folder) as (
                select ? union all select id from arsse_folders join folders on parent = folder
            )
            select count(*) from arsse_subscriptions",
            ["int"],
            [$folder]
        );
        $q->setWhere("owner = ?", "str", $user);
        $q->setWhere("deleted = 0");
        if ($folder) {
            // if the specified folder exists, add a suitable WHERE condition
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
        if (!V::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'type' => "int > 0"]);
        }
        $changes = $this->db->prepare("UPDATE arsse_subscriptions set deleted = 1, modified = CURRENT_TIMESTAMP where owner = ? and id = ? and deleted = 0", "str", "int")->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return true;
    }

    /** Retrieves data about a particular subscription, as an associative array; see subscriptionList for details */
    public function subscriptionPropertiesGet(string $user, $id): array {
        if (!V::id($id)) {
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
     * The $data array may contain one or more of the following keys:
     *
     * - "url": The URL of the subscription's newsfeed
     * - "title": The title of the subscription
     * - "folder": The numeric identifier (or null) of the subscription's folder
     * - "pinned": Whether the subscription is pinned
     * - "scrape": Whether to scrape full article contents from the HTML article
     * - "order_type": Whether articles should be sorted in reverse cronological order (2), chronological order (1), or the default (0)
     * - "keep_rule": The subscription's "keep" filter rule; articles which do not match this are hidden
     * - "block_rule": The subscription's "block" filter rule; articles which match this are hidden
     * - "user_agent": An HTTP User-Agent value to use when fetching the feed rather than the default
     * - "cookie": An HTTP cookie to send when fetching feeds
     * - "username": The username to present to the foreign server when fetching the feed; this is intergrated into the URL
     * - "password": The password to present to the foreign server when fetching the feed; this is intergrated into the URL
     *
     * @param string $user The user whose subscription is to be modified
     * @param integer $id the numeric identifier of the subscription to modfify
     * @param array $data An associative array of properties to modify; any keys not specified will be left unchanged
     * @param boolean $deleted Whether to process the operation when the subscription is soft-deleted. This is required to be true when subscriptions are reserved but not yet fetched
     */
    public function subscriptionPropertiesSet(string $user, $id, array $data, bool $acceptDeleted = false): bool {
        $tr = $this->db->begin();
        // validate the ID
        $id = (int) $this->subscriptionValidateId($user, $id, true, $acceptDeleted)['id'];
        if (array_key_exists("folder", $data)) {
            // ensure the target folder exists and belong to the user
            $data['folder'] = $this->folderValidateId($user, $data['folder'])['id'];
        }
        if (isset($data['title'])) {
            // if the title is null, this signals intended use of the default title; otherwise make sure it's not effectively an empty string
            $info = V::str($data['title']);
            if ($info & V::EMPTY) {
                throw new Db\ExceptionInput("missing", ["action" => __FUNCTION__, "field" => "title"]);
            } elseif ($info & V::WHITE) {
                throw new Db\ExceptionInput("whitespace", ["action" => __FUNCTION__, "field" => "title"]);
            } elseif (!($info & V::VALID)) {
                throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "title", 'type' => "string"]);
            }
        }
        // validate any filter rules
        if (isset($data['keep_rule'])) {
            if (!is_string($data['keep_rule'])) {
                throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "keep_rule", 'type' => "string"]);
            } elseif (!Rule::validate($data['keep_rule'])) {
                throw new Db\ExceptionInput("invalidValue", ["action" => __FUNCTION__, "field" => "keep_rule"]);
            }
        }
        if (isset($data['block_rule'])) {
            if (!is_string($data['block_rule'])) {
                throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "block_rule", 'type' => "string"]);
            } elseif (!Rule::validate($data['block_rule'])) {
                throw new Db\ExceptionInput("invalidValue", ["action" => __FUNCTION__, "field" => "block_rule"]);
            }
        }
        // construct the new feed URL from components, if applicable; we have
        //   to do this because we store any username and password within the
        //   URL itself, an arrangement which simplifies indexing in MySQL
        //   among other considerations
        if (isset($data['url']) || isset($data['username']) || isset($data['password'])) {
            // if we're setting the URL we must check it and extract credentials from it
            if (isset($data['url'])) {
                // we can't practically check if the URL actually points to a
                //   feed (we risk a database deadlock if we take too long with
                //   the transaction), but we can at least check if it is
                //   syntactically an absolute URI
                if (!URL::absolute($data['url'])) {
                    throw new Db\ExceptionInput("invalidValue", ["action" => __FUNCTION__, "field" => "url"]);
                }
                $u = parse_url($data['url'], \PHP_URL_USER);
                $p = parse_url($data['url'], \PHP_URL_PASS);
                // if there is a username in the URL, use these credentials
                //   unless they are explicitly supplied; this prevents
                //   existing credentials in the current URL from taking
                //   precedence when they shouldn't
                if (strlen($u ?? "")) {
                    $data['username'] = $data['username'] ?? $u;
                    $data['password'] = $data['password'] ?? $p ?? "";
                }
            }
            // validate the username
            if (isset($data['username']) && HTTP::userInvalid($data['username'])) {
                throw new Db\ExceptionInput("invalidValue", ["action" => __FUNCTION__, "field" => "username"]);
            }
            // retrieve the current values for the URL, username, and password
            // the URL from the database can be assumed to be a string because the subscription ID is already validated above
            $url = $this->db->prepare("SELECT url from arsse_subscriptions where id = ? and owner = ?", "int", "str")->run($id, $user)->getValue();
            $u = parse_url($url, \PHP_URL_USER);
            $p = parse_url($url, \PHP_URL_PASS);
            // construct the new URL
            $data['url'] = URL::normalize($data['url'] ?? $url, $data['username'] ?? $u, $data['password'] ?? $p);
        }
        // perform the update
        $valid = [
            'title'      => "str",
            'folder'     => "int",
            'order_type' => "strict int",
            'pinned'     => "strict bool",
            'keep_rule'  => "str",
            'block_rule' => "str",
            'scrape'     => "strict bool",
            'user_agent' => "str",
            'url'        => "strict str",
            'cookie'     => "str",
            // "username" doesn't apply because it is part of the URL
            // "password" doesn't apply because it is part of the URL
        ];
        [$setClause, $setTypes, $setValues] = $this->generateSet($data, $valid);
        if (!$setClause) {
            // if no changes would actually be applied, just return
            return false;
        }
        $out = (bool) $this->db->prepare("UPDATE arsse_subscriptions set $setClause, modified = CURRENT_TIMESTAMP where owner = ? and id = ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
        $tr->commit();
        // if filter rules were changed, apply them; this is done outside the transaction because it may take some time
        if (array_key_exists("keep_rule", $data) || array_key_exists("block_rule", $data)) {
            $this->subscriptionRulesApply($user, $id);
        }
        return $out;
    }

    /** Returns an indexed array listing the tags assigned to a subscription
     *
     * @param string $user The user whose tags are to be listed
     * @param integer $id The numeric identifier of the subscription whose tags are to be listed
     * @param boolean $byName Whether to return the tag names (true) instead of the numeric tag identifiers (false)
     */
    public function subscriptionTagsGet(string $user, $id, bool $byName = false): array {
        $this->subscriptionValidateId($user, $id, true);
        $field = !$byName ? "id" : "name";
        $out = $this->db->prepare("SELECT $field from arsse_tags where id in (select tag from arsse_tag_members where subscription = ? and assigned = 1) order by $field", "int")->run($id)->getAll();
        return $out ? array_column($out, $field) : [];
    }

    /** Retrieves detailed information about the icon for a subscription.
     *
     * The returned information is:
     *
     * - "id": The umeric identifier of the icon (not the subscription)
     * - "url": The URL of the icon
     * - "type": The Content-Type of the icon e.g. "image/png"
     * - "data": The icon itself, as a binary sring; if $withData is false this will be null
     *
     * If the subscription has no icon null is returned instead of an array
     *
     * @param string|null $user The user who owns the subscription being queried; using null here is supported for TT-RSS and SHOULD NOT be used elsewhere as it leaks information
     * @param int $subscription The numeric identifier of the subscription
     * @param bool $includeData Whether to include the binary data of the icon itself in the result
     */
    public function subscriptionIcon(?string $user, int $id, bool $includeData = true): ?array {
        $data = $includeData ? "i.data" : "null as data";
        $q = new Query("SELECT i.id, i.url, i.type, $data from arsse_subscriptions as s left join arsse_icons as i on s.icon = i.id");
        $q->setWhere("s.id = ?", "int", $id);
        $q->setWhere("s.deleted = 0");
        if (isset($user)) {
            $q->setWhere("s.owner = ?", "str", $user);
        }
        $out = $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "subscription", 'id' => $id]);
        } elseif (!$out['id']) {
            return null;
        }
        return $out;
    }

    /** Returns the time at which any of a user's subscriptions (or a specific subscription) was last refreshed, as a DateTimeImmutable object */
    public function subscriptionRefreshed(string $user, ?int $id = null): ?\DateTimeImmutable {
        $q = new Query("SELECT max(updated) from arsse_subscriptions");
        $q->setWhere("owner = ?", "str", $user);
        $q->setWhere("deleted = 0");
        if ($id) {
            $q->setWhere("id = ?", "int", $id);
        }
        $out = $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
        if (!$out && $id) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return V::normalize($out, V::T_DATE | V::M_NULL, "sql");
    }

    /** Evalutes the filter rules specified for a subscription against every article associated with the subscription
     *
     * @param string $user The user who owns the subscription
     * @param integer $id The identifier of the subscription whose rules are to be evaluated
     */
    protected function subscriptionRulesApply(string $user, int $id): void {
        // start a transaction for read isolation
        $tr = $this->begin();
        $sub = $this->db->prepare("SELECT id, coalesce(keep_rule, '') as keep, coalesce(block_rule, '') as block from arsse_subscriptions where owner = ? and id = ?", "str", "int")->run($user, $id)->getRow();
        try {
            $keep = Rule::prep($sub['keep']);
            $block = Rule::prep($sub['block']);
        } catch (RuleException $e) { // @codeCoverageIgnore
            // invalid rules should not normally appear in the database, but it's possible
            // in this case we should halt evaluation and just leave things as they are
            return; // @codeCoverageIgnore
        }
        $articles = $this->db->prepare("SELECT id, url, title, coalesce(categories, 0) as categories from arsse_articles as a left join (select article, count(*) as categories from arsse_categories group by article) as c on a.id = c.article where a.subscription = ?", "int")->run($id)->getAll();
        $hide = [];
        $unhide = [];
        foreach ($articles as $r) {
            // retrieve the list of categories if the article has any
            $categories = $r['categories'] ? $this->articleCategoriesGet($user, (int) $r['id']) : [];
            // evaluate the rule for the article
            if (Rule::apply($keep, $block, $r['url'] ?? "", $r['title'] ?? "", $r['author'] ?? "", $categories)) {
                $unhide[] = $r['id'];
            } else {
                $hide[] = $r['id'];
            }
        }
        // roll back the read transation
        $tr->rollback();
        // apply any marks
        if ($hide) {
            $this->articleMark($user, ['hidden' => true], (new Context)->articles($hide), false);
        }
        if ($unhide) {
            $this->articleMark($user, ['hidden' => false], (new Context)->articles($unhide), false);
        }
    }

    /** Ensures the specified subscription exists and raises an exception otherwise
     *
     * Returns an associative array containing the id of the subscription and the id of the underlying newsfeed
     *
     * @param string $user The user who owns the subscription to be validated
     * @param integer $id The identifier of the subscription to validate
     * @param boolean $subject Whether the subscription is the subject (true) rather than the object (false) of the operation being performed; this only affects the semantics of the error message if validation fails
     * @param boolean $acceptDeleted Whether to consider a soft-deleted subscription as valid
     */
    protected function subscriptionValidateId(string $user, $id, bool $subject = false, bool $acceptDeleted = false): array {
        if (!V::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "feed", 'type' => "int > 0"]);
        }
        $deleted = $acceptDeleted ? "deleted" : "0";
        $out = $this->db->prepare("SELECT id, id from arsse_subscriptions where id = ? and owner = ? and deleted = $deleted", "int", "str")->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "subscription", 'id' => $id]);
        }
        return $out;
    }

    /** Returns an indexed array of numeric identifiers for newsfeeds which should be refreshed */
    public function subscriptionListStale(): array {
        $feeds = $this->db->query("SELECT id from arsse_subscriptions where next_fetch <= CURRENT_TIMESTAMP")->getAll();
        return array_column($feeds, 'id');
    }

    /** Attempts to refresh a subscribed newsfeed, returning an indication of success
     *
     * @param string|null $user The user whose subscribed newsfeed is to be updated; this may be null to facilitate refreshing feeds from the CLI
     * @param integer $subID The numerical identifier of the subscription to refresh
     * @param boolean $throwError Whether to throw an exception on failure in addition to storing error information in the database
     */
    public function subscriptionUpdate(?string $user, $subID, bool $throwError = false): bool {
        // check to make sure the feed exists
        if (!V::id($subID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'id' => $subID, 'type' => "int > 0"]);
        }
        $f = $this->db->prepareArray(
            "SELECT 
                url, last_mod as modified, etag, err_count, scrape, keep_rule, block_rule, user_agent, cookie
            FROM arsse_subscriptions
            where id = ? and owner = coalesce(?, owner)",
            ["int", "str"]
        )->run($subID, $user)->getRow();
        if (!$f) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $subID]);
        }
        // determine whether the feed's items should be scraped for full content from the source Web site
        $scrape = (Arsse::$conf->fetchEnableScraping && $f['scrape']);
        // the Feed object throws an exception when there are problems, but that isn't ideal
        // here. When an exception is thrown it should update the database with the
        // error instead of failing; if other exceptions are thrown, we should simply roll back
        try {
            $feed = new Feed((int) $subID, $f['url'], (string) Date::transform($f['modified'], "http", "sql"), $f['etag'], $f['user_agent'], $f['cookie'], $scrape);
            if (!$feed->modified) {
                // if the feed hasn't changed, just compute the next fetch time and record it
                $this->db->prepare("UPDATE arsse_subscriptions SET updated = CURRENT_TIMESTAMP, next_fetch = ? WHERE id = ?", 'datetime', 'int')->run($feed->nextFetch, $subID);
                return false;
            }
        } catch (Feed\Exception $e) {
            // update the database with the resultant error and the next fetch time, incrementing the error count
            $this->db->prepareArray(
                "UPDATE arsse_subscriptions SET updated = CURRENT_TIMESTAMP, next_fetch = ?, err_count = err_count + 1, err_msg = ? WHERE id = ?",
                ['datetime', 'str', 'int']
            )->run(Feed::nextFetchOnError($f['err_count']), $e->getMessage(), $subID);
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
            $qInsertArticle = $this->db->prepareArray(
                "INSERT INTO arsse_articles(url,title,author,published,edited,guid,url_title_hash,url_content_hash,title_content_hash,subscription,hidden) values(?,?,?,?,?,?,?,?,?,?,?)",
                ["str", "str", "str", "datetime", "datetime", "str", "str", "str", "str", "int", "bool"]
            );
            $qInsertContent = $this->db->prepareArray("INSERT INTO arsse_article_contents(id, content) values(?,?)", ["int", "str"]);
        }
        if (sizeof($feed->changedItems)) {
            $qDeleteEnclosures = $this->db->prepare("DELETE FROM arsse_enclosures WHERE article = ?", 'int');
            $qDeleteCategories = $this->db->prepare("DELETE FROM arsse_categories WHERE article = ?", 'int');
            $qUpdateArticle = $this->db->prepareArray(
                "UPDATE arsse_articles SET \"read\" = 0, hidden = ?, url = ?, title = ?, author = ?, published = ?, edited = ?, modified = CURRENT_TIMESTAMP, guid = ?, url_title_hash = ?, url_content_hash = ?, title_content_hash = ? WHERE id = ?",
                ["bool", "str", "str", "str", "datetime", "datetime", "str", "str", "str", "str", "int"]
            );
            $qUpdateContent = $this->db->prepareArray("UPDATE arsse_article_contents set content = ? where id = ?", ["str", "int"]); 
        }
        // prepare the keep and block rules
        try {
            $keep = Rule::prep($f['keep_rule'] ?? "");
        } catch (RuleException $e) { // @codeCoverageIgnore
            // invalid rules should not normally appear in the database, but it's possible
            // in this case we act as if the rule were not defined
            $keep = ""; // @codeCoverageIgnore
        }
        try {
            $block = Rule::prep($f['block_rule'] ?? "");
        } catch (RuleException $e) { // @codeCoverageIgnore
            // invalid rules should not normally appear in the database, but it's possible
            // in this case we act as if the rule were not defined
            $block = ""; // @codeCoverageIgnore
        }
        // determine if the feed icon needs to be updated, and update it if appropriate
        $tr = $this->db->begin();
        $icon = null;
        if ($feed->iconUrl) {
            $icon = $this->db->prepare("SELECT id, url, type, data from arsse_icons where url = ?", "str")->run($feed->iconUrl)->getRow();
            if ($icon) {
                // update the existing icon if necessary
                if ($feed->iconType !== $icon['type'] || $feed->iconData !== $icon['data']) {
                    $this->db->prepare("UPDATE arsse_icons set type = ?, data = ? where id = ?", "str", "blob", "int")->run($feed->iconType, $feed->iconData, $icon['id']);
                }
                $icon = $icon['id'];
            } else {
                // add the new icon to the cache
                $icon = $this->db->prepare("INSERT INTO arsse_icons(url, type, data) values(?, ?, ?)", "str", "str", "blob")->run($feed->iconUrl, $feed->iconType, $feed->iconData)->lastId();
            }
        }
        $articleMap = [];
        // actually perform updates, starting with inserting new articles
        foreach ($feed->newItems as $k => $article) {
            $articleID = $qInsertArticle->run(
                $article->url,
                $article->title,
                $article->author,
                $article->publishedDate,
                $article->updatedDate,
                $article->id,
                $article->urlTitleHash,
                $article->urlContentHash,
                $article->titleContentHash,
                $subID,
                !Rule::apply($keep, $block, $article->url ?? "", $article->title ?? "", $article->author ?? "", $article->categories)
            )->lastId();
            $qInsertContent->run($articleID, $article->scrapedContent ?? $article->content);
            // note the new ID for later use
            $articleMap[$k] = $articleID;
            // insert any enclosures
            if ($article->enclosureUrl) {
                $qInsertEnclosure->run($articleID, $article->enclosureUrl, $article->enclosureType);
            }
            // insert any categories
            foreach ($article->categories as $c) {
                $qInsertCategory->run($articleID, $c);
            }
            // assign a new edition ID to the article
            $qInsertEdition->run($articleID);
        }
        // next update existing artricles which have been edited
        foreach ($feed->changedItems as $articleID => $article) {
            $qUpdateArticle->run(
                !Rule::apply($keep, $block, $article->url ?? "", $article->title ?? "", $article->author ?? "", $article->categories),
                $article->url,
                $article->title,
                $article->author,
                $article->publishedDate,
                $article->updatedDate,
                $article->id,
                $article->urlTitleHash,
                $article->urlContentHash,
                $article->titleContentHash,
                $articleID
            );
            $qUpdateContent->run($article->scrapedContent ?? $article->content, $articleID);
            // delete all enclosures and categories and re-insert them
            $qDeleteEnclosures->run($articleID);
            $qDeleteCategories->run($articleID);
            if ($article->enclosureUrl) {
                $qInsertEnclosure->run($articleID, $article->enclosureUrl, $article->enclosureType);
            }
            foreach ($article->categories as $c) {
                $qInsertCategory->run($articleID, $c);
            }
            // assign a new edition ID to this version of the article
            $qInsertEdition->run($articleID);
        }
        // lastly update the feed database itself with updated information.
        $this->db->prepareArray(
            "UPDATE arsse_subscriptions SET feed_title = ?, source = ?, updated = CURRENT_TIMESTAMP, last_mod = ?, etag = ?, err_count = 0, err_msg = '', next_fetch = ?, size = ?, icon = ? WHERE id = ?",
            ["str", "str", "datetime", "strict str", "datetime", "int", "int", "int"]
        )->run(
            $feed->title,
            $feed->siteUrl,
            $feed->lastModified,
            $feed->etag,
            $feed->nextFetch,
            sizeof($feed->items),
            $icon,
            $subID
        );
        $tr->commit();
        return true;
    }

    /** Deletes soft-deleted newsfeed subscriptions from the database, subject to the retention period */
    public function subscriptionCleanup(): bool {
        // delete subscriptions that have been soft-deleted longer than the retention period, if a a purge threshold has been specified
        if (Arsse::$conf->purgeFeeds) {
            $limit = Date::sub(Arsse::$conf->purgeFeeds);
            $out = (bool) $this->db->prepare("DELETE from arsse_subscriptions where deleted = 1 and modified <= ?", "datetime")->run($limit);
        } else {
            $out = false;
        }
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
     * @param integer $subID The numeric identifier of the feed
     * @param integer $count The number of records to return
     */
    public function subscriptionMatchLatest(int $subID, int $count): Db\Result {
        return $this->db->prepare(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE subscription = ? ORDER BY modified desc, id desc limit ?",
            'int',
            'int'
        )->run($subID, $count);
    }

    /** Retrieves various identifiers for articles in the given subscription which match the input identifiers. The output identifiers are:
     *
     * - "id": The database record key for the article
     * - "guid": The (theoretically) unique identifier for the article
     * - "edited": The time at which the article was last edited, per the newsfeed
     * - "url_title_hash": A cryptographic hash of the article URL and its title
     * - "url_content_hash": A cryptographic hash of the article URL and its content
     * - "title_content_hash": A cryptographic hash of the article title and its content
     *
     * @param integer $subID The numeric identifier of the feed
     * @param array $ids An array of GUIDs of articles
     * @param array $hashesUT An array of hashes of articles' URL and title
     * @param array $hashesUC An array of hashes of articles' URL and content
     * @param array $hashesTC An array of hashes of articles' title and content
     */
    public function subscriptionMatchIds(int $subID, array $ids = [], array $hashesUT = [], array $hashesUC = [], array $hashesTC = []): Db\Result {
        // compile SQL IN() clauses and necessary type bindings for the four identifier lists
        [$cId, $tId, $vId] = $this->generateIn($ids, "str");
        [$cHashUT, $tHashUT, $vHashUT] = $this->generateIn($hashesUT, "str");
        [$cHashUC, $tHashUC, $vHashUC] = $this->generateIn($hashesUC, "str");
        [$cHashTC, $tHashTC, $vHashTC] = $this->generateIn($hashesTC, "str");
        // perform the query
        return $this->db->prepareArray(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE subscription = ? and (guid in($cId) or url_title_hash in($cHashUT) or url_content_hash in($cHashUC) or title_content_hash in($cHashTC))",
            ['int', $tId, $tHashUT, $tHashUC, $tHashTC]
        )->run($subID, $vId, $vHashUT, $vHashUC, $vHashTC);
    }

    /** Lists icons for feeds to which a user is subscribed
     *
     * The returned information for each icon is:
     *
     * - "id": The umeric identifier of the icon
     * - "url": The URL of the icon
     * - "type": The Content-Type of the icon e.g. "image/png"
     * - "data": The icon itself, as a binary sring
     *
     * @param string $user The user whose subscription icons are to be retrieved
     */
    public function iconList(string $user): Db\Result {
        return $this->db->prepare("SELECT distinct i.id, i.url, i.type, i.data from arsse_icons as i join arsse_subscriptions as s on s.icon = i.id where s.owner = ? and s.deleted = 0", "str")->run($user);
    }

    /** Retrieve data on a single icon, but its ID
     *
     * The returned information for the icon is identical to `iconList` above
     *
     * @param ?string $user The user whose subscription icon is to be retrieved, which may be omitted when necessary
     * @param int $id The numeric identifier of the icon
     */
    public function iconPropertiesGet(?string $user, int $id): array {
        if (!V::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "icon", 'type' => "int > 0"]);
        }
        $out = $this->db->prepare(
            "SELECT distinct
                i.id, i.url, i.type, i.data
            from arsse_icons as i
            join arsse_subscriptions as s on s.icon = i.id
            where
                s.owner = coalesce(?, s.owner)
                and s.deleted = 0
                and i.id = ?",
            "str", "int"
        )->run($user, $id)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "icon", 'id' => $id]);
        }
        return $out;
    }

    /** Deletes orphaned icons from the database
     *
     * Icons are orphaned if no subscribed newsfeed uses them.
     */
    public function iconCleanup(): int {
        $tr = $this->begin();
        // first unmark any icons which are no longer orphaned; an icon is considered orphaned if it is not used or only used by feeds which are themselves orphaned
        $this->db->query("UPDATE arsse_icons set orphaned = null where id in (select distinct icon from arsse_subscriptions where icon is not null and deleted = 0)");
        // next mark any newly orphaned icons with the current date and time
        $this->db->query("UPDATE arsse_icons set orphaned = CURRENT_TIMESTAMP where orphaned is null and id not in (select distinct icon from arsse_subscriptions where icon is not null and deleted = 0)");
        // finally delete icons that have been orphaned longer than the feed retention period, if a a purge threshold has been specified
        $out = 0;
        if (Arsse::$conf->purgeFeeds) {
            $limit = Date::sub(Arsse::$conf->purgeFeeds);
            $out += $this->db->prepare("DELETE from arsse_icons where orphaned <= ?", "datetime")->run($limit)->changes();
        }
        $tr->commit();
        return $out;
    }

    /** Returns an associative array of result column names and their SQL computations for article queries
     *
     * This is used for whitelisting and defining both output column and order-by columns, as well as for resolution of some context options
     */
    protected function articleColumns(): array {
        $greatest = $this->db->sqlToken("greatest");
        $least = $this->db->sqlToken("least");
        return [
            'id'                 => "arsse_articles.id",                                                                                                                                 // The article's unchanging numeric ID
            'edition'            => "latest_editions.edition",                                                                                                                           // The article's numeric ID which increases each time it is modified in the feed
            'latest_edition'     => "max(latest_editions.edition)",                                                                                                                      // The most recent of all editions
            'url'                => "arsse_articles.url",                                                                                                                                // The URL of the article's full content
            'title'              => "arsse_articles.title",                                                                                                                              // The title
            'author'             => "arsse_articles.author",                                                                                                                             // The name of the author
            'content'            => "arsse_article_contents.content",                                                                                                                    // The article content
            'guid'               => "arsse_articles.guid",                                                                                                                               // The GUID of the article, as presented in the feed (NOTE: Picofeed actually provides a hash of the ID)
            'fingerprint'        => "arsse_articles.url_title_hash || ':' || arsse_articles.url_content_hash || ':' || arsse_articles.title_content_hash",                               // A combination of three hashes
            'folder'             => "coalesce(arsse_subscriptions.folder,0)",                                                                                                            // The folder of the article's feed. This is mainly for use in WHERE clauses
            'top_folder'         => "coalesce(folder_data.top,0)",                                                                                                                       // The top-most folder of the article's feed. This is mainly for use in WHERE clauses
            'folder_name'        => "folder_data.name",                                                                                                                                  // The name of the folder of the article's feed. This is mainly for use in WHERE clauses
            'top_folder_name'    => "folder_data.top_name",                                                                                                                              // The name of the top-most folder of the article's feed. This is mainly for use in WHERE clauses
            'subscription'       => "arsse_subscriptions.id",                                                                                                                            // The article's parent subscription ID
            'subscription_url'   => "arsse_subscriptions.url",                                                                                                                           // The article's parent subscription URL
            'subscription_title' => "coalesce(arsse_subscriptions.title, arsse_subscriptions.feed_title)",                                                                               // The parent subscription's title
            'hidden'             => "coalesce(arsse_articles.hidden,0)",                                                                                                                 // Whether the article is hidden
            'starred'            => "coalesce(arsse_articles.starred,0)",                                                                                                                // Whether the article is starred
            'unread'             => "abs(coalesce(arsse_articles.read,0) - 1)",                                                                                                          // Whether the article is unread
            'labelled'           => "$least(coalesce(label_stats.assigned,0),1)",                                                                                                        // Whether the article has at least one label
            'annotated'          => "(case when coalesce(arsse_articles.note,'') <> '' then 1 else 0 end)",                                                                              // Whether the article has a note
            'note'               => "coalesce(arsse_articles.note,'')",                                                                                                                  // The article's note, if any
            'published_date'     => "coalesce(arsse_articles.published, arsse_articles.added)",                                                                                          // The date at which the article was first published i.e. its creation date
            'edited_date'        => "coalesce(arsse_articles.edited, arsse_articles.published, arsse_articles.modified)",                                                                // The date at which the article was last edited according to the feed
            'modified_date'      => "arsse_articles.modified",                                                                                                                           // The date at which the article was last updated in our database
            'added_date'         => "arsse_articles.added",                                                                                                                              // The date at which the article was created in our database
            'marked_date'        => "$greatest(arsse_articles.modified, coalesce(arsse_articles.marked, '0001-01-01 00:00:00'), coalesce(label_stats.modified, '0001-01-01 00:00:00'))", // The date at which the article metadata was last modified by the user
            'media_url'          => "arsse_enclosures.url",                                                                                                                              // The URL of the article's enclosure, if any (NOTE: Picofeed only exposes one enclosure)
            'media_type'         => "arsse_enclosures.type",                                                                                                                             // The Content-Type of the article's enclosure, if any
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
        // prepare the output column list; the column definitions are also used for ordering
        $colDefs = $this->articleColumns();
        if (!$cols) {
            // if no columns are specified return a count; don't borther with sorting
            $outColumns = "count(distinct arsse_articles.id) as count";
        } else {
            // normalize requested output and sorting columns
            $norm = function($v) {
                return trim(strtolower(V::normalize($v, V::T_STRING)));
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
        assert(strlen($outColumns) > 0, new \Exception("No input columns matched whitelist"));
        // define the basic query, to which we add lots of stuff where necessary
        $q = new Query(
            "WITH RECURSIVE
            folders(id,req) as (
                select 0, 0 union all select f.id, f.id from arsse_folders as f where owner = ? union all select f1.id, req from arsse_folders as f1 join folders on coalesce(parent,0)=folders.id
            ),
            folders_top(id,top) as (
                select f.id, f.id from arsse_folders as f where owner = ? and parent is null union all select f.id, top from arsse_folders as f join folders_top as t on parent=t.id
            ),
            folder_data(id,name,top,top_name) as (
                select f1.id, f1.name, top, f2.name from arsse_folders as f1 join folders_top as f0 on f1.id = f0.id join arsse_folders as f2 on f2.id = top
            ),
            labelled(article,label_id,label_name) as (
                select m.article, l.id, l.name from arsse_label_members as m join arsse_labels as l on l.id = m.label where l.owner = ? and m.assigned = 1
            ),
            tagged(subscription,tag_id,tag_name) as (
                select m.subscription, t.id, t.name from arsse_tag_members as m join arsse_tags as t on t.id = m.tag where t.owner = ? and m.assigned = 1
            )
            select 
                $outColumns
            from arsse_articles
            join arsse_subscriptions on arsse_subscriptions.id = arsse_articles.subscription and arsse_subscriptions.owner = ? and arsse_subscriptions.deleted = 0
            left join arsse_article_contents on arsse_article_contents.id = arsse_articles.id
            left join folder_data on arsse_subscriptions.folder = folder_data.id
            left join arsse_enclosures on arsse_enclosures.article = arsse_articles.id
            left join (
                select article, max(id) as edition from arsse_editions group by article
            ) as latest_editions on arsse_articles.id = latest_editions.article
            left join (
                select arsse_label_members.article, max(arsse_label_members.modified) as modified, sum(arsse_label_members.assigned) as assigned from arsse_label_members join arsse_labels on arsse_labels.id = arsse_label_members.label where arsse_labels.owner = ? group by arsse_label_members.article
            ) as label_stats on label_stats.article = arsse_articles.id",
            ["str", "str", "str", "str", "str", "str"],
            [$user, $user, $user, $user, $user, $user]
        );
        $q->setLimit($context->limit, $context->offset);
        // validate input to catch 404s and the like
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
        // ensure any used array-type context options contain at least one member
        foreach ([
            "articles",
            "editions",
            "subscriptions",
            "folders",
            "foldersShallow",
            "labels",
            "labelNames",
            "tags",
            "tagNames",
            "searchTerms",
            "titleTerms",
            "authorTerms",
            "annotationTerms",
            "modifiedRanges",
            "markedRanges",
            "addedRanges",
            "publishedRanges",
        ] as $m) {
            if ($context->$m() && !$context->$m) {
                throw new Db\ExceptionInput("tooShort", ['field' => $m, 'action' => $this->caller(), 'min' => 1]);
            }
        }
        // next compute the context, supplying the query to manipulate directly
        $this->articleFilter($context, $q);
        // return the query
        return $q;
    }

    /** Transforms a selection context for articles into a set of terms for an SQL "where" clause */
    protected function articleFilter(Context $context, ?QueryFilter $q = null): QueryFilter {
        $q = $q ?? new QueryFilter;
        $colDefs = $this->articleColumns();
        // handle the simple context options
        $options = [
            // each context array consists of a column identifier (see $colDefs above), a comparison operator, and a data type; the "between" operator has special handling
            "edition"          => ["edition",        "=",       "int"],
            "editions"         => ["edition",        "in",      "int"],
            "article"          => ["id",             "=",       "int"],
            "articles"         => ["id",             "in",      "int"],
            "articleRange"     => ["id",             "between", "int"],
            "editionRange"     => ["edition",        "between", "int"],
            "modifiedRange"    => ["modified_date",  "between", "datetime"],
            "markedRange"      => ["marked_date",    "between", "datetime"],
            "addedRange"       => ["added_date",     "between", "datetime"],
            "publishedRange"   => ["published_date", "between", "datetime"],
            "folderShallow"    => ["folder",         "=",       "int"],
            "foldersShallow"   => ["folder",         "in",      "int"],
            "subscription"     => ["subscription",   "=",       "int"],
            "subscriptions"    => ["subscription",   "in",      "int"],
            "unread"           => ["unread",         "=",       "bool"],
            "starred"          => ["starred",        "=",       "bool"],
            "hidden"           => ["hidden",         "=",       "bool"],
            "labelled"         => ["labelled",       "=",       "bool"],
            "annotated"        => ["annotated",      "=",       "bool"],
        ];
        foreach ($options as $m => [$col, $op, $type]) {
            if ($context->$m()) {
                if ($op === "between") {
                    // option is a range
                    if ($context->$m[0] === null) {
                        // range is open at the low end
                        $q->setWhere("{$colDefs[$col]} <= ?", $type, $context->$m[1]);
                    } elseif ($context->$m[1] === null) {
                        // range is open at the high end
                        $q->setWhere("{$colDefs[$col]} >= ?", $type, $context->$m[0]);
                    } else {
                        // range is bounded in both directions
                        $q->setWhere("{$colDefs[$col]} BETWEEN ? AND ?", [$type, $type], $context->$m);
                    }
                } elseif (is_array($context->$m)) {
                    // context option is an array of values
                    [$clause, $types, $values] = $this->generateIn($context->$m, $type);
                    $q->setWhere("{$colDefs[$col]} $op ($clause)", $types, $values);
                } else {
                    $q->setWhere("{$colDefs[$col]} $op ?", $type, $context->$m);
                }
            }
            // handle the exclusionary version
            if (property_exists($context->not, $m) && method_exists($context->not, $m) && $context->not->$m()) {
                if ($op === "between") {
                    // option is a range
                    if ($context->not->$m[0] === null) {
                        // range is open at the low end
                        $q->setWhereNot("{$colDefs[$col]} <= ?", $type, $context->not->$m[1]);
                    } elseif ($context->not->$m[1] === null) {
                        // range is open at the high end
                        $q->setWhereNot("{$colDefs[$col]} >= ?", $type, $context->not->$m[0]);
                    } else {
                        // range is bounded in both directions
                        $q->setWhereNot("{$colDefs[$col]} BETWEEN ? AND ?", [$type, $type], $context->not->$m);
                    }
                } elseif (is_array($context->not->$m)) {
                    if (!$context->not->$m) {
                        // for exclusions we don't care if the array is empty
                        continue;
                    }
                    [$clause, $types, $values] = $this->generateIn($context->not->$m, $type);
                    $q->setWhereNot("{$colDefs[$col]} $op ($clause)", $types, $values);
                } else {
                    $q->setWhereNot("{$colDefs[$col]} $op ?", $type, $context->not->$m);
                }
            }
        }
        // handle folder trees, labels, and tags
        $options = [
            // each context array consists of a common table expression to select from, the column to match in the main join, the column to match in the CTE, the column to select in the CTE, an operator, and a type for the match in the CTE
            'folder'     => ["folders",  "folder",       "folders.id",          "req",        "=",  "int"],
            'folders'    => ["folders",  "folder",       "folders.id",          "req",        "in", "int"],
            'label'      => ["labelled", "id",           "labelled.article",    "label_id",   "=",  "int"],
            'labels'     => ["labelled", "id",           "labelled.article",    "label_id",   "in", "int"],
            'labelName'  => ["labelled", "id",           "labelled.article",    "label_name", "=",  "str"],
            'labelNames' => ["labelled", "id",           "labelled.article",    "label_name", "in", "str"],
            'tag'        => ["tagged",   "subscription", "tagged.subscription", "tag_id",     "=",  "int"],
            'tags'       => ["tagged",   "subscription", "tagged.subscription", "tag_id",     "in", "int"],
            'tagName'    => ["tagged",   "subscription", "tagged.subscription", "tag_name",   "=",  "str"],
            'tagNames'   => ["tagged",   "subscription", "tagged.subscription", "tag_name",   "in", "str"],
        ];
        foreach ($options as $m => [$cte, $outerCol, $selection, $innerCol, $op, $type]) {
            if ($context->$m()) {
                if ($op === "in") {
                    [$inClause, $inTypes, $inValues] = $this->generateIn($context->$m, $type);
                    $q->setWhere("{$colDefs[$outerCol]} in (select $selection from $cte where $innerCol in($inClause))", $inTypes, $inValues);
                } else {
                    $q->setWhere("{$colDefs[$outerCol]} in (select $selection from $cte where $innerCol = ?)", $type, $context->$m);
                }
            }
            // handle the exclusionary version
            if ($context->not->$m()) {
                if ($op === "in") {
                    if (!$context->not->$m) {
                        // for exclusions we don't care if the array is empty
                        continue;
                    }
                    [$inClause, $inTypes, $inValues] = $this->generateIn($context->not->$m, $type);
                    $q->setWhereNot("{$colDefs[$outerCol]} in (select $selection from $cte where $innerCol in($inClause))", $inTypes, $inValues);
                } else {
                    $q->setWhereNot("{$colDefs[$outerCol]} in (select $selection from $cte where $innerCol = ?)", $type, $context->not->$m);
                }
            }
        }
        // handle text-matching context options
        $options = [
            "titleTerms"      => ["title"],
            "searchTerms"     => ["title", "content"],
            "authorTerms"     => ["author"],
            "annotationTerms" => ["note"],
        ];
        foreach ($options as $m => $columns) {
            $columns = array_map(function($c) use ($colDefs) {
                assert(isset($colDefs[$c]), new Exception("constantUnknown", $c));
                return $colDefs[$c];
            }, $columns);
            if ($context->$m()) {
                $q->setWhere(...$this->generateSearch($context->$m, $columns));
            }
            // handle the exclusionary version
            if ($context->not->$m() && $context->not->$m) {
                $q->setWhereNot(...$this->generateSearch($context->not->$m, $columns, true));
            }
        }
        // handle arrays of ranges
        $options = [
            'modifiedRanges'  => ["modified_date",  "datetime"],
            'markedRanges'    => ["marked_date",    "datetime"],
            'addedRanges'     => ["added_date",     "datetime"],
            'publishedRanges' => ["published_date", "datetime"],
        ];
        foreach ($options as $m => [$col, $type]) {
            if ($context->$m()) {
                $subq = new QueryFilter;
                foreach ($context->$m as $r) {
                    if ($r[0] === null) {
                        // range is open at the low end
                        $subq->setWhere("{$colDefs[$col]} <= ?", $type, $r[1]);
                    } elseif ($r[1] === null) {
                        // range is open at the high end
                        $subq->setWhere("{$colDefs[$col]} >= ?", $type, $r[0]);
                    } else {
                        // range is bounded in both directions
                        $subq->setWhere("{$colDefs[$col]} BETWEEN ? AND ?", [$type, $type], $r);
                    }
                }
                $q->setWhereGroup($subq, false);
            }
            // handle the exclusionary version
            if ($context->not->$m() && $context->not->$m) {
                foreach ($context->not->$m as $r) {
                    if ($r[0] === null) {
                        // range is open at the low end
                        $q->setWhereNot("{$colDefs[$col]} <= ?", $type, $r[1]);
                    } elseif ($r[1] === null) {
                        // range is open at the high end
                        $q->setWhereNot("{$colDefs[$col]} >= ?", $type, $r[0]);
                    } else {
                        // range is bounded in both directions
                        $q->setWhereNot("{$colDefs[$col]} BETWEEN ? AND ?", [$type, $type], $r);
                    }
                }
            }
        }
        // handle subgroups
        $options = [
            'orGroups'  => false,
            'andGroups' => true,
        ];
        foreach ($options as $m => $restrictive) {
            if ($context->$m()) {
                foreach ($context->$m as $c) {
                    $q->setWhereGroup($this->articleFilter($c), $restrictive);
                }
            }
            // handle the exclusionary version
            if ($context->not->$m()) {
                foreach ($context->$m as $c) {
                    $q->setWhereNotGroup($this->articleFilter($c), $restrictive);
                }
            }
        }
        return $q;
    }

    /** Lists articles in the database which match a given query context
     *
     * If an empty column list is supplied, a count of articles is returned instead
     *
     * @param string $user The user whose articles are to be listed
     * @param ?Context $context The search context
     * @param array $fields The columns to return in the result set, any of: id, edition, url, title, author, content, guid, fingerprint, folder, subscription, feed, starred, unread, note, published_date, edited_date, modified_date, marked_date, subscription_title, media_url, media_type
     * @param array $sort The columns to sort the result by eg. "edition desc" in decreasing order of importance
     */
    public function articleList(string $user, ?Context $context = null, array $fields = ["id"], array $sort = []): Db\Result {
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
                $order = " ".$this->db->sqlToken("desc");
            } elseif ($order === "asc" || $order === "") {
                $order = " ".$this->db->sqlToken("asc");
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
     * @param ?Context $context The search context
     */
    public function articleCount(string $user, ?Context $context = null): int {
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
     * - "hidden":  Whether the article should (true) or should not (false) be suppressed from normal listings; this is normally set by the system rather than the user directly
     * - "note":    A string containing a freeform plain-text note for the article
     *
     * @param string $user The user who owns the articles to be modified
     * @param array $data An associative array of properties to modify. Anything not specified will remain unchanged
     * @param Context $context The query context to match articles against
     * @param bool $updateTimestamp Whether to also update the timestamp. This should only be false if a mark is changed as a result of an automated action not taken by the user
     */
    public function articleMark(string $user, array $data, ?Context $context = null, bool $updateTimestamp = true): int {
        $data = [
            'read'    => $data['read'] ?? null,
            'starred' => $data['starred'] ?? null,
            'hidden'  => $data['hidden'] ?? null,
            'note'    => $data['note'] ?? null,
        ];
        if (!isset($data['read']) && !isset($data['starred']) && !isset($data['hidden']) && !isset($data['note'])) {
            return 0;
        }
        $context = $context ?? new Context;
        $tr = $this->begin();
        $out = 0;
        if (isset($data['read']) && (isset($data['starred']) || isset($data['hidden']) || isset($data['note'])) && ($context->edition() || $context->editions())) {
            // if marking by edition both read and something else, do separate marks for starred, hidden, and note than for read
            //   marking as read is ignored if the edition is not the latest, but the same is not true of the other two marks
            $subq = $this->articleQuery($user, $context);
            $subq->setWhere("arsse_articles.read <> coalesce(?,arsse_articles.read)", "bool", $data['read']);
            $q = new Query(
                "WITH RECURSIVE
                target_articles(article) as (
                    {$subq->getQuery()}
                )
                update arsse_articles
                set 
                    \"read\" = ?, 
                    touched = 1 
                where 
                    id in (select article from target_articles)", 
                [$subq->getTypes(), "bool"], 
                [$subq->getValues(), $data['read']]
            );
            $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
            // get the articles associated with the requested editions
            if ($context->edition()) {
                $context->article($this->articleValidateEdition($user, $context->edition)['article'])->edition(null);
            } else {
                $context->articles($this->editionArticle(...$context->editions))->editions(null);
            }
            // set starred, hidden, and/or note marks (unless all requested editions actually do not exist)
            if ($context->article || $context->articles) {
                $setData = array_filter($data, function($v) {
                    return isset($v);
                });
                [$set, $setTypes, $setValues] = $this->generateSet($setData, ['starred' => "bool", 'hidden' => "bool", 'note' => "str"]);
                $subq = $this->articleQuery($user, $context);
                $subq->setWhere("(arsse_articles.note <> coalesce(?,arsse_articles.note) or arsse_articles.starred <> coalesce(?,arsse_articles.starred) or arsse_articles.hidden <> coalesce(?,arsse_articles.hidden))", ["str", "bool", "bool"], [$data['note'], $data['starred'], $data['hidden']]);
                $q = new Query(
                    "WITH RECURSIVE
                    target_articles(article) as (
                        {$subq->getQuery()}
                    )
                    update arsse_articles
                    set 
                        touched = 1,
                        $set 
                    where 
                        id in (select article from target_articles)",
                    [$subq->getTypes(), $setTypes], 
                    [$subq->getValues(), $setValues]
                );
                $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
            }
            // finally set the modification date for all touched marks and return the number of affected marks
            if ($updateTimestamp) {
                $out = $this->db->query("UPDATE arsse_articles set marked = CURRENT_TIMESTAMP, touched = 0 where touched = 1")->changes();
            } else {
                $out = $this->db->query("UPDATE arsse_articles set touched = 0 where touched = 1")->changes();
            }
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
            $setData = array_filter($data, function($v) {
                return isset($v);
            });
            [$set, $setTypes, $setValues] = $this->generateSet($setData, ['read' => "bool", 'starred' => "bool", 'hidden' => "bool", 'note' => "str"]);
            if ($updateTimestamp) {
                $set .= ", marked = CURRENT_TIMESTAMP";
            }
            $subq = $this->articleQuery($user, $context);
            $subq->setWhere("(arsse_articles.note <> coalesce(?,arsse_articles.note) or arsse_articles.starred <> coalesce(?,arsse_articles.starred) or arsse_articles.read <> coalesce(?,arsse_articles.read) or arsse_articles.hidden <> coalesce(?,arsse_articles.hidden))", ["str", "bool", "bool", "bool"], [$data['note'], $data['starred'], $data['read'], $data['hidden']]);
            $q = new Query(
                "WITH RECURSIVE
                target_articles(article) as (
                    {$subq->getQuery()}
                )
                update arsse_articles
                set
                    $set
                where
                    id in (select article from target_articles)",
                [$subq->getTypes(), $setTypes],
                [$subq->getValues(), $setValues]
            );
            $out = $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        }
        $tr->commit();
        return $out;
    }

    /** Returns statistics about the articles starred by the given user. Hidden articles are excluded
     *
     * The associative array returned has the following keys:
     *
     * - "total":  The count of all starred articles
     * - "unread": The count of starred articles which are unread
     * - "read":   The count of starred articles which are read
     */
    public function articleStarred(string $user): array {
        return $this->db->prepare(
            "SELECT
                count(*) as total,
                coalesce(sum(abs(\"read\" - 1)),0) as unread,
                coalesce(sum(\"read\"),0) as \"read\"
            FROM (
                select \"read\" from arsse_articles where starred = 1 and hidden <> 1 and subscription in (select id from arsse_subscriptions where owner = ? and deleted = 0)
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
        $id = $this->articleValidateId($user, $id)['article'];
        $field = !$byName ? "id" : "name";
        $out = $this->db->prepare("SELECT $field from arsse_labels join arsse_label_members on arsse_label_members.label = arsse_labels.id where owner = ? and article = ? and assigned = 1 order by $field", "str", "int")->run($user, $id)->getAll();
        return $out ? array_column($out, $field) : [];
    }

    /** Returns the author-supplied categories associated with an article */
    public function articleCategoriesGet(string $user, $id): array {
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
        $query = $this->db->prepareArray(
            "WITH RECURSIVE
            exempt_articles as (
                SELECT 
                    id 
                from arsse_articles join (
                    SELECT article, max(id) as edition from arsse_editions group by article
                ) as latest_editions on arsse_articles.id = latest_editions.article 
                where subscription = ? order by edition desc limit ?
            )
            DELETE FROM 
                arsse_articles
            where
                subscription = ?
                and (starred = 0 or hidden = 1)
                and (
                    coalesce(marked,modified) <= ? 
                    or (coalesce(marked,modified) <= ? and (\"read\" = 1 or hidden = 1))
                )
                and id not in (select id from exempt_articles)",
            ["int", "int", "int", "datetime", "datetime"]
        );
        $limitRead = null;
        $limitUnread = null;
        if (Arsse::$conf->purgeArticlesRead) {
            $limitRead = Date::sub(Arsse::$conf->purgeArticlesRead);
        }
        if (Arsse::$conf->purgeArticlesUnread) {
            $limitUnread = Date::sub(Arsse::$conf->purgeArticlesUnread);
        }
        $deleted = 0;
        $tr = $this->begin();
        $feeds = $this->db->query("SELECT id, size from arsse_subscriptions")->getAll();
        foreach ($feeds as $feed) {
            $deleted += $query->run($feed['id'], $feed['size'], $feed['id'], $limitUnread, $limitRead)->changes();
        }
        $tr->commit();
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
        if (!V::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "article", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepareArray(
            "SELECT articles.article as article, max(arsse_editions.id)  as edition from (
                select arsse_articles.id as article
                FROM arsse_articles
                    join arsse_subscriptions on arsse_subscriptions.id = arsse_articles.subscription
                WHERE arsse_articles.id = ? and arsse_subscriptions.owner = ? and arsse_subscriptions.deleted = 0
            ) as articles left join arsse_editions on arsse_editions.article = articles.article group by articles.article",
            ["int", "str"]
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
        if (!V::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "edition", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepareArray(
            "SELECT
                arsse_editions.id, arsse_editions.article, edition_stats.edition as current
            from arsse_editions 
                join arsse_articles on arsse_articles.id = arsse_editions.article
                join arsse_subscriptions on arsse_subscriptions.id = arsse_articles.subscription
                join (select article, max(id) as edition from arsse_editions group by article) as edition_stats on edition_stats.article = arsse_editions.article
            where arsse_editions.id = ? and arsse_subscriptions.owner = ? and arsse_subscriptions.deleted = 0",
            ["int", "str"]
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "edition", 'id' => $id]);
        }
        return array_map("intval", $out);
    }

    /** Returns the numeric identifier of the most recent edition of an article matching the given context */
    public function editionLatest(string $user, ?Context $context = null): int {
        $context = $context ?? new Context;
        $q = $this->articleQuery($user, $context, ["latest_edition"]);
        return (int) $this->db->prepare((string) $q, $q->getTypes())->run($q->getValues())->getValue();
    }

    /** Returns a map between all the given edition identifiers and their associated article identifiers */
    public function editionArticle(int ...$edition): array {
        $out = [];
        $context = (new Context)->editions($edition);
        [$in, $inTypes, $inValues] = $this->generateIn($context->editions, "int");
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
        $integerType = $this->db->sqlToken("integer");
        return $this->db->prepareArray(
            "SELECT * FROM (
                SELECT
                    id,
                    name,
                    cast(coalesce(articles - coalesce(hidden, 0), 0) as $integerType) as articles, -- this cast is required for MySQL for unclear reasons
                    cast(coalesce(marked, 0) as $integerType) as \"read\" -- this cast is required for MySQL for unclear reasons
                from arsse_labels
                    left join (
                        SELECT
                            label, 
                            sum(assigned) as articles 
                        from arsse_label_members
                            join arsse_articles on arsse_articles.id = arsse_label_members.article
                            join arsse_subscriptions on arsse_articles.subscription = arsse_subscriptions.id and arsse_subscriptions.deleted = 0
                        group by label
                    ) as label_stats on label_stats.label = arsse_labels.id
                    left join (
                        SELECT
                            label,
                            sum(hidden) as hidden,
                            sum(case when \"read\" = 1 and hidden = 0 then 1 else 0 end) as marked
                        from arsse_articles
                            join arsse_subscriptions on arsse_subscriptions.id = arsse_articles.subscription
                            join arsse_label_members on arsse_label_members.article = arsse_articles.id
                        where arsse_subscriptions.owner = ? and arsse_subscriptions.deleted = 0
                        group by label
                    ) as mark_stats on mark_stats.label = arsse_labels.id
                WHERE owner = ?
            ) as label_data
            where articles >= ? order by name",
            ["str", "str", "int"]
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
        $this->labelValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $out = $this->db->prepareArray(
            "SELECT
                id,
                name,
                coalesce(articles - coalesce(hidden, 0), 0) as articles,
                coalesce(marked, 0) as \"read\"
            FROM arsse_labels
                left join (
                    SELECT
                        label, 
                        sum(assigned) as articles 
                    from arsse_label_members
                    join arsse_articles on arsse_articles.id = arsse_label_members.article
                    join arsse_subscriptions on arsse_articles.subscription = arsse_subscriptions.id and arsse_subscriptions.deleted = 0
                    group by label
                ) as label_stats on label_stats.label = arsse_labels.id
                left join (
                    SELECT
                        label,
                        sum(hidden) as hidden,
                        sum(case when \"read\" = 1 and hidden = 0 then 1 else 0 end) as marked
                    from arsse_articles
                        join arsse_subscriptions on arsse_subscriptions.id = arsse_articles.subscription
                        join arsse_label_members on arsse_label_members.article = arsse_articles.id
                    where arsse_subscriptions.owner = ? and arsse_subscriptions.deleted = 0
                    group by label
                ) as mark_stats on mark_stats.label = arsse_labels.id
            WHERE $field = ? and owner = ?",
            ["str", $type, "str"]
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
        $this->labelValidateId($user, $id, $byName, false);
        if (isset($data['name'])) {
            $this->labelValidateName($data['name']);
        }
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $valid = [
            'name'      => "str",
        ];
        [$setClause, $setTypes, $setValues] = $this->generateSet($data, $valid);
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
        $c = (new Context)->hidden(false);
        if ($byName) {
            $c->labelName($id);
        } else {
            $c->label($id);
        }
        try {
            $q = $this->articleQuery($user, $c);
            $q->setOrder("id");
            $out = $this->db->prepare((string) $q, $q->getTypes())->run($q->getValues())->getAll();
        } catch (Db\ExceptionInput $e) {
            if ($e->getCode() === 10235) {
                throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
            }
            throw $e;
        }
        if (!$out) {
            return $out;
        } else {
            return array_column($out, "id");
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
        // validate the tag ID, and get the numeric ID if matching by name
        $id = $this->labelValidateId($user, $id, $byName, true)['id'];
        // get the list of articles matching the context
        $articles = iterator_to_array($this->articleList($user, $context ?? new Context));
        // an empty article list is a special case
        if (!sizeof($articles)) {
            if ($mode == self::ASSOC_REPLACE) {
                // replacing with an empty set means setting everything to zero
                return $this->db->prepare("UPDATE arsse_label_members set assigned = 0, modified = CURRENT_TIMESTAMP where label = ? and assigned = 1 and article not in (select id from arsse_articles where subscription in (select id from arsse_subscriptions where deleted = 1))", "int")->run($id)->changes();
            } else {
                // adding or removing is a no-op
                return 0;
            }
        } else {
            $articles = array_column($articles, "id");
        }
        // prepare up to three queries: removing requires one, adding two, and replacing three
        [$inClause, $inTypes, $inValues] = $this->generateIn($articles, "int");
        $updateQ = "UPDATE arsse_label_members set assigned = ?, modified = CURRENT_TIMESTAMP where label = ? and assigned <> ? and article %in% ($inClause) and article not in (select id from arsse_articles where subscription in (select id from arsse_subscriptions where deleted = 1))";
        $updateT = ["bool", "int", "bool", $inTypes];
        $insertQ = "INSERT INTO arsse_label_members(label,article) SELECT ?,a.id from arsse_articles as a join arsse_subscriptions as s on a.subscription = s.id where s.owner = ? and a.id not in (select article from arsse_label_members where label = ?) and a.id in ($inClause)";
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
        foreach ($qList as [$q, $t, $v]) {
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
        if (!$byName && !V::id($id)) {
            // if we're not referring to a label by name and the ID is invalid, throw an exception
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "label", 'type' => "int > 0"]);
        } elseif ($byName && !(V::str($id) & V::VALID)) {
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
        $info = V::str($name);
        if ($info & (V::NULL | V::EMPTY)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } elseif ($info & V::WHITE) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } elseif (!($info & V::VALID)) {
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
        $integerType = $this->db->sqlToken("integer");
        return $this->db->prepareArray(
            "SELECT * FROM (
                SELECT
                    id,
                    name,
                    cast(coalesce(subscriptions,0) as $integerType) as subscriptions -- this cast is required for MySQL for unclear reasons
                from arsse_tags 
                    left join (
                        SELECT 
                            tag, 
                            sum(assigned) as subscriptions 
                        from arsse_tag_members
                            join arsse_subscriptions on arsse_subscriptions.id = arsse_tag_members.subscription and arsse_subscriptions.deleted = 0
                        group by tag
                    ) as tag_stats on tag_stats.tag = arsse_tags.id
                WHERE owner = ?
            ) as tag_data
            where subscriptions >= ? order by name",
            ["str", "int"]
        )->run($user, !$includeEmpty);
    }

    /** Lists the associations between all tags and subscription
     *
     * The following keys are included in each record:
     *
     * - "tag_id": The tag's numeric identifier
     * - "tag_name" The tag's textual name
     * - "subscription": The numeric identifier of the associated subscription
     *
     * @param string $user The user whose tags are to be listed
     */
    public function tagSummarize(string $user): Db\Result {
        return $this->db->prepareArray(
            "SELECT
                arsse_tags.id as id,
                arsse_tags.name as name,
                arsse_tag_members.subscription as subscription
            FROM arsse_tag_members
                join arsse_tags on arsse_tags.id = arsse_tag_members.tag
                join arsse_subscriptions on arsse_subscriptions.id = arsse_tag_members.subscription and arsse_subscriptions.deleted = 0
            WHERE arsse_tags.owner = ? and assigned = 1",
            ["str"]
        )->run($user);
    }

    /** Deletes a tag from the database
     *
     * Any subscriptions associated with the tag remain untouched
     *
     * @param string $user The owner of the tag to remove
     * @param integer|string $id The numeric identifier or name of the tag
     * @param boolean $byName Whether to interpret the $id parameter as the tag's name (true) or identifier (false)
     */
    public function tagRemove(string $user, $id, bool $byName = false): bool {
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
        $this->tagValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $out = $this->db->prepareArray(
            "SELECT
                id,
                name,
                coalesce(subscriptions,0) as subscriptions
            FROM arsse_tags
                left join (
                    SELECT 
                        tag, 
                        sum(assigned) as subscriptions 
                    from arsse_tag_members
                        join arsse_subscriptions on arsse_subscriptions.id = arsse_tag_members.subscription and arsse_subscriptions.deleted = 0
                    group by tag
                ) as tag_stats on tag_stats.tag = arsse_tags.id
            WHERE $field = ? and arsse_tags.owner = ?",
            [$type, "str"]
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
        $this->tagValidateId($user, $id, $byName, false);
        if (isset($data['name'])) {
            $this->tagValidateName($data['name']);
        }
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $valid = [
            'name'      => "str",
        ];
        [$setClause, $setTypes, $setValues] = $this->generateSet($data, $valid);
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
        // just do a syntactic check on the tag ID
        $this->tagValidateId($user, $id, $byName, false);
        $field = !$byName ? "t.id" : "t.name";
        $type = !$byName ? "int" : "str";
        $out = $this->db->prepare(
            "SELECT 
                subscription 
            from arsse_tag_members as m
                join arsse_tags as t on m.tag = t.id
                join arsse_subscriptions as s on m.subscription = s.id and s.deleted = 0
            where assigned = 1 and $field = ? and t.owner = ?
            order by subscription",
            $type, "str"
        )->run($id, $user)->getAll();
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
        // validate the tag ID, and get the numeric ID if matching by name
        $id = $this->tagValidateId($user, $id, $byName, true)['id'];
        // an empty subscription list is a special case
        if (!sizeof($subscriptions)) {
            if ($mode == self::ASSOC_REPLACE) {
                // replacing with an empty set means setting everything to zero
                return $this->db->prepare("UPDATE arsse_tag_members set assigned = 0, modified = CURRENT_TIMESTAMP where tag = ? and assigned = 1 and subscription not in (select id from arsse_subscriptions where deleted = 1)", "int")->run($id)->changes();
            } else {
                // adding or removing is a no-op
                return 0;
            }
        }
        // prepare up to three queries: removing requires one, adding two, and replacing three
        [$inClause, $inTypes, $inValues] = $this->generateIn($subscriptions, "int");
        $updateQ = "UPDATE arsse_tag_members set assigned = ?, modified = CURRENT_TIMESTAMP where tag = ? and assigned <> ? and subscription in (select id from arsse_subscriptions where owner = ? and id %in% ($inClause) and id not in (select id from arsse_subscriptions where deleted = 1))";
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
        foreach ($qList as [$q, $t, $v]) {
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
        if (!$byName && !V::id($id)) {
            // if we're not referring to a tag by name and the ID is invalid, throw an exception
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "tag", 'type' => "int > 0"]);
        } elseif ($byName && !(V::str($id) & V::VALID)) {
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
        $info = V::str($name);
        if ($info & (V::NULL | V::EMPTY)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } elseif ($info & V::WHITE) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } elseif (!($info & V::VALID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "name", 'type' => "string"]);
        } else {
            return true;
        }
    }
}
