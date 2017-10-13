<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

use PasswordGenerator\Generator as PassGen;
use JKingWeb\DrUUID\UUID;
use JKingWeb\Arsse\Misc\Query;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

class Database {
    const SCHEMA_VERSION = 2;
    
    /** @var Db\Driver */
    public $db;

    public function __construct($initialize = true) {
        $driver = Arsse::$conf->dbDriver;
        $this->db = new $driver();
        $ver = $this->db->schemaVersion();
        if ($initialize && $ver < self::SCHEMA_VERSION) {
            $this->db->schemaUpdate(self::SCHEMA_VERSION);
        }
    }

    protected function caller(): string {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];
    }
    
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

    public function driverSchemaVersion(): int {
        return $this->db->schemaVersion();
    }

    public function driverSchemaUpdate(): bool {
        if ($this->db->schemaVersion() < self::SCHEMA_VERSION) {
            return $this->db->schemaUpdate(self::SCHEMA_VERSION);
        }
        return false;
    }

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
            $out[0][] = "$prop = ?";
            $out[1][] = $type;
            $out[2][] = $props[$prop];
        }
        $out[0] = implode(", ", $out[0]);
        return $out;
    }

    protected function generateIn(array $values, string $type) {
        $out = [
            [], // query clause
            [], // binding types
        ];
        // the query clause is just a series of question marks separated by commas
        $out[0] = implode(",", array_fill(0, sizeof($values), "?"));
        // the binding types are just a repetition of the supplied type
        $out[1] = array_fill(0, sizeof($values), $type);
        return $out;
    }

    public function begin(): Db\Transaction {
        return $this->db->begin();
    }

    public function metaGet(string $key) {
        return $this->db->prepare("SELECT value from arsse_meta where key is ?", "str")->run($key)->getValue();
    }
    
    public function metaSet(string $key, $value, string $type = "str"): bool {
        $out = $this->db->prepare("UPDATE arsse_meta set value = ? where key is ?", $type, "str")->run($value, $key)->changes();
        if (!$out) {
            $out = $this->db->prepare("INSERT INTO arsse_meta(key,value) values(?,?)", "str", $type)->run($key, $value)->changes();
        }
        return (bool) $out;
    }

    public function metaRemove(string $key): bool {
        return (bool) $this->db->prepare("DELETE from arsse_meta where key is ?", "str")->run($key)->changes();
    }

    public function userExists(string $user): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return (bool) $this->db->prepare("SELECT count(*) from arsse_users where id is ?", "str")->run($user)->getValue();
    }

    public function userAdd(string $user, string $password = null): string {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif ($this->userExists($user)) {
            throw new User\Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        }
        if ($password===null) {
            $password = (new PassGen)->length(Arsse::$conf->userTempPasswordLength)->get();
        }
        $hash = "";
        if (strlen($password) > 0) {
            $hash = password_hash($password, \PASSWORD_DEFAULT);
        }
        $this->db->prepare("INSERT INTO arsse_users(id,password) values(?,?)", "str", "str")->runArray([$user,$hash]);
        return $password;
    }

    public function userRemove(string $user): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if ($this->db->prepare("DELETE from arsse_users where id is ?", "str")->run($user)->changes() < 1) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return true;
    }

    public function userList(string $domain = null): array {
        $out = [];
        if ($domain !== null) {
            if (!Arsse::$user->authorize("@".$domain, __FUNCTION__)) {
                throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $domain]);
            }
            $domain = str_replace(["\\","%","_"], ["\\\\", "\\%", "\\_"], $domain);
            $domain = "%@".$domain;
            foreach ($this->db->prepare("SELECT id from arsse_users where id like ?", "str")->run($domain) as $user) {
                $out[] = $user['id'];
            }
        } else {
            if (!Arsse::$user->authorize("", __FUNCTION__)) {
                throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => "global"]);
            }
            foreach ($this->db->query("SELECT id from arsse_users") as $user) {
                $out[] = $user['id'];
            }
        }
        return $out;
    }

    public function userPasswordGet(string $user): string {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return (string) $this->db->prepare("SELECT password from arsse_users where id is ?", "str")->run($user)->getValue();
    }

    public function userPasswordSet(string $user, string $password = null): string {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        if ($password===null) {
            $password = (new PassGen)->length(Arsse::$conf->userTempPasswordLength)->get();
        }
        $hash = "";
        if (strlen($password) > 0) {
            $hash = password_hash($password, \PASSWORD_DEFAULT);
        }
        $this->db->prepare("UPDATE arsse_users set password = ? where id is ?", "str", "str")->run($hash, $user);
        return $password;
    }

    public function userPropertiesGet(string $user): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $prop = $this->db->prepare("SELECT name,rights from arsse_users where id is ?", "str")->run($user)->getRow();
        if (!$prop) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $prop;
    }

    public function userPropertiesSet(string $user, array $properties): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $valid = [ // FIXME: add future properties
            "name" => "str",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($properties, $valid);
        if (!$setClause) {
            // if no changes would actually be applied, just return
            return $this->userPropertiesGet($user);
        }
        $this->db->prepare("UPDATE arsse_users set $setClause where id is ?", $setTypes, "str")->run($setValues, $user);
        return $this->userPropertiesGet($user);
    }

    public function userRightsGet(string $user): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return (int) $this->db->prepare("SELECT rights from arsse_users where id is ?", "str")->run($user)->getValue();
    }

    public function userRightsSet(string $user, int $rights): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__, $rights)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        } elseif (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->db->prepare("UPDATE arsse_users set rights = ? where id is ?", "int", "str")->run($rights, $user);
        return true;
    }

    public function sessionCreate(string $user): string {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // generate a new session ID and expiry date
        $id = UUID::mint()->hex;
        $expires = Date::add(Arsse::$conf->userSessionTimeout);
        // save the session to the database
        $this->db->prepare("INSERT INTO arsse_sessions(id,expires,user) values(?,?,?)", "str", "datetime", "str")->run($id, $expires, $user);
        // return the ID
        return $id;
    }

    public function sessionDestroy(string $user, string $id): bool {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // delete the session and report success.
        return (bool) $this->db->prepare("DELETE FROM arsse_sessions where id is ? and user is ?", "str", "str")->run($id, $user)->changes();
    }

    public function sessionResume(string $id): array {
        $maxAge = Date::sub(Arsse::$conf->userSessionLifetime);
        $out = $this->db->prepare("SELECT id,created,expires,user from arsse_sessions where id is ? and expires > CURRENT_TIMESTAMP and created > ?", "str", "datetime")->run($id, $maxAge)->getRow();
        // if the session does not exist or is expired, throw an exception
        if (!$out) {
            throw new User\ExceptionSession("invalid", $id);
        }
        // if we're more than half-way from the session expiring, renew it
        if ($this->sessionExpiringSoon(Date::normalize($out['expires'], "sql"))) {
            $expires = Date::add(Arsse::$conf->userSessionTimeout);
            $this->db->prepare("UPDATE arsse_sessions set expires = ? where id is ?", "datetime", "str")->run($expires, $id);
        }
        return $out;
    }

    public function sessionCleanup(): int {
        $maxAge = Date::sub(Arsse::$conf->userSessionLifetime);
        return $this->db->prepare("DELETE FROM arsse_sessions where expires < CURRENT_TIMESTAMP or created < ?", "datetime")->run($maxAge)->changes();
    }

    protected function sessionExpiringSoon(\DateTimeInterface $expiry): bool {
        // calculate half the session timeout as a number of seconds
        $now = time();
        $max = Date::add(Arsse::$conf->userSessionTimeout, $now)->getTimestamp();
        $diff = intdiv($max - $now, 2);
        // determine if the expiry time is less than half the session timeout into the future
        return (($now + $diff) >= $expiry->getTimestamp());
    }

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
                (select count(*) from arsse_folders as parents where parents.parent is arsse_folders.id) as children,
                (select count(*) from arsse_subscriptions where folder is arsse_folders.id) as feeds
            FROM arsse_folders"
        );
        if (!$recursive) {
            $q->setWhere("owner is ?", "str", $user);
            $q->setWhere("parent is ?", "int", $parent);
        } else {
            $q->setCTE("folders", "SELECT id from arsse_folders where owner is ? and parent is ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id", ["str", "int"], [$user, $parent]);
            $q->setWhere("id in (SELECT id from folders)");
        }
        $q->setOrder("name");
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    public function folderRemove(string $user, $id): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "folder", 'type' => "int > 0"]);
        }
        $changes = $this->db->prepare("DELETE FROM arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        }
        return true;
    }

    public function folderPropertiesGet(string $user, $id): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "folder", 'type' => "int > 0"]);
        }
        $props = $this->db->prepare("SELECT id,name,parent from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->getRow();
        if (!$props) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        }
        return $props;
    }

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
        return (bool) $this->db->prepare("UPDATE arsse_folders set $setClause, modified = CURRENT_TIMESTAMP where owner is ? and id is ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
    }

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
        $f = $this->db->prepare("SELECT id,name,parent from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->getRow();
        if (!$f) {
            throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "folder", 'id' => $id]);
        }
        return $f;
    }

    protected function folderValidateMove(string $user, int $id = null, $parent = null, string $name = null) {
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
        if ($id==$parent) {
            throw new Db\ExceptionInput("circularDependence", $errData);
        }
        // make sure both that the prospective parent exists, and that the it is not one of its children (a circular dependence);
        // also make sure that a folder with the same prospective name and parent does not already exist: if the parent is null,
        // SQL will happily accept duplicates (null is not unique), so we must do this check ourselves
        $p = $this->db->prepare(
            "WITH RECURSIVE
                target as (select ? as user, ? as source, ? as dest, ? as rename),
                folders as (SELECT id from arsse_folders join target on owner is user and parent is source  union select arsse_folders.id as id from arsse_folders join folders on arsse_folders.parent=folders.id)
            ".
            "SELECT 
                ((select dest from target) is null or exists(select id from arsse_folders join target on owner is user and id is dest)) as extant,
                not exists(select id from folders where id is (select dest from target)) as valid,
                not exists(select id from arsse_folders join target on parent is dest and name is coalesce((select rename from target),(select name from arsse_folders join target on id is source))) as available
            ", "str", "int", "int", "str"
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

    protected function folderValidateName($name, bool $checkDuplicates = false, int $parent = null): bool {
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
            if ($this->db->prepare("SELECT exists(select id from arsse_folders where parent is ? and name is ?)", "int", "str")->run($parent, $name)->getValue()) {
                throw new Db\ExceptionInput("constraintViolation", ["action" => $this->caller(), "field" => "name"]);
            }
            return true;
        } else {
            return true;
        }
    }

    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = "", bool $discover = true): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // check to see if the feed exists
        $check = $this->db->prepare("SELECT id from arsse_feeds where url is ? and username is ? and password is ?", "str", "str", "str");
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
                $this->db->prepare('DELETE from arsse_feeds where id is ?', 'int')->run($feedID);
                throw $e;
            }
        }
        // Add the feed to the user's subscriptions and return the new subscription's ID.
        return $this->db->prepare('INSERT INTO arsse_subscriptions(owner,feed) values(?,?)', 'str', 'int')->run($user, $feedID)->lastId();
    }

    public function subscriptionList(string $user, $folder = null, int $id = null): Db\Result {
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
                (SELECT count(*) from arsse_articles where feed is arsse_subscriptions.feed) - (SELECT count(*) from arsse_marks where subscription is arsse_subscriptions.id and read is 1) as unread
             from arsse_subscriptions 
                join user on user is owner 
                join arsse_feeds on feed = arsse_feeds.id 
                left join topmost on folder=f_id"
        );
        $q->setOrder("pinned desc, title");
        // define common table expressions
        $q->setCTE("user(user)", "SELECT ?", "str", $user);  // the subject user; this way we only have to pass it to prepare() once
        // topmost folders belonging to the user
        $q->setCTE("topmost(f_id,top)", "SELECT id,id from arsse_folders join user on owner is user where parent is null union select id,top from arsse_folders join topmost on parent=f_id");
        if ($id) {
            // this condition facilitates the implementation of subscriptionPropertiesGet, which would otherwise have to duplicate the complex query; it takes precedence over a specified folder
            // if an ID is specified, add a suitable WHERE condition and bindings
            $q->setWhere("arsse_subscriptions.id is ?", "int", $id);
        } elseif ($folder) {
            // if it does exist, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on parent is folder", "int", $folder);
            // add a suitable WHERE condition
            $q->setWhere("folder in (select folder from folders)");
        }
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    public function subscriptionCount(string $user, $folder = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // validate inputs
        $folder = $this->folderValidateId($user, $folder)['id'];
        // create a complex query
        $q = new Query("SELECT count(*) from arsse_subscriptions");
        $q->setWhere("owner is ?", "str", $user);
        if ($folder) {
            // if it does exist, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on parent is folder", "int", $folder);
            // add a suitable WHERE condition
            $q->setWhere("folder in (select folder from folders)");
        }
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

    public function subscriptionRemove(string $user, $id): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'type' => "int > 0"]);
        }
        $changes = $this->db->prepare("DELETE from arsse_subscriptions where owner is ? and id is ?", "str", "int")->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return true;
    }

    public function subscriptionPropertiesGet(string $user, $id): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'type' => "int > 0"]);
        }
        // disable authorization checks for the list call
        Arsse::$user->authorizationEnabled(false);
        $sub = $this->subscriptionList($user, null, (int) $id)->getRow();
        Arsse::$user->authorizationEnabled(true);
        if (!$sub) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        return $sub;
    }

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
        $out = (bool) $this->db->prepare("UPDATE arsse_subscriptions set $setClause, modified = CURRENT_TIMESTAMP where owner is ? and id is ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
        $tr->commit();
        return $out;
    }

    protected function subscriptionValidateId(string $user, $id, bool $subject = false): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "feed", 'type' => "int > 0"]);
        }
        $out = $this->db->prepare("SELECT id,feed from arsse_subscriptions where id is ? and owner is ?", "int", "str")->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "subscription", 'id' => $id]);
        }
        return $out;
    }

    public function feedListStale(): array {
        $feeds = $this->db->query("SELECT id from arsse_feeds where next_fetch <= CURRENT_TIMESTAMP")->getAll();
        return array_column($feeds, 'id');
    }
    
    public function feedUpdate($feedID, bool $throwError = false): bool {
        $tr = $this->db->begin();
        // check to make sure the feed exists
        if (!ValueInfo::id($feedID)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => __FUNCTION__, "field" => "feed", 'id' => $feedID, 'type' => "int > 0"]);
        }
        $f = $this->db->prepare("SELECT url, username, password, modified, etag, err_count, scrape FROM arsse_feeds where id is ?", "int")->run($feedID)->getRow();
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
                $this->db->prepare("UPDATE arsse_feeds SET updated = CURRENT_TIMESTAMP, next_fetch = ? WHERE id is ?", 'datetime', 'int')->run($feed->nextFetch, $feedID);
                $tr->commit();
                return false;
            }
        } catch (Feed\Exception $e) {
            // update the database with the resultant error and the next fetch time, incrementing the error count
            $this->db->prepare(
                "UPDATE arsse_feeds SET updated = CURRENT_TIMESTAMP, next_fetch = ?, err_count = err_count + 1, err_msg = ? WHERE id is ?",
                'datetime', 'str', 'int'
            )->run(Feed::nextFetchOnError($f['err_count']), $e->getMessage(), $feedID);
            $tr->commit();
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
                'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str', 'int'
            );
        }
        if (sizeof($feed->changedItems)) {
            $qDeleteEnclosures = $this->db->prepare("DELETE FROM arsse_enclosures WHERE article is ?", 'int');
            $qDeleteCategories = $this->db->prepare("DELETE FROM arsse_categories WHERE article is ?", 'int');
            $qClearReadMarks = $this->db->prepare("UPDATE arsse_marks SET read = 0, modified = CURRENT_TIMESTAMP WHERE article is ? and read is 1", 'int');
            $qUpdateArticle = $this->db->prepare(
                "UPDATE arsse_articles SET url = ?, title = ?, author = ?, published = ?, edited = ?, modified = CURRENT_TIMESTAMP, guid = ?, content = ?, url_title_hash = ?, url_content_hash = ?, title_content_hash = ? WHERE id is ?",
                'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str', 'int'
            );
        }
        // actually perform updates
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
            "UPDATE arsse_feeds SET url = ?, title = ?, favicon = ?, source = ?, updated = CURRENT_TIMESTAMP, modified = ?, etag = ?, err_count = 0, err_msg = '', next_fetch = ?, size = ? WHERE id is ?",
            'str', 'str', 'str', 'str', 'datetime', 'str', 'datetime', 'int', 'int'
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

    public function feedCleanup(): bool {
        $tr = $this->begin();
        // first unmark any feeds which are no longer orphaned
        $this->db->query("UPDATE arsse_feeds set orphaned = null where exists(SELECT id from arsse_subscriptions where feed is arsse_feeds.id)");
        // next mark any newly orphaned feeds with the current date and time
        $this->db->query("UPDATE arsse_feeds set orphaned = CURRENT_TIMESTAMP where orphaned is null and not exists(SELECT id from arsse_subscriptions where feed is arsse_feeds.id)");
        // finally delete feeds that have been orphaned longer than the retention period
        $limit = Date::normalize("now");
        if (Arsse::$conf->purgeFeeds) {
            // if there is a retention period specified, compute it; otherwise feed are deleted immediatelty
            $limit->sub(new \DateInterval(Arsse::$conf->purgeFeeds));
        }
        $out = (bool) $this->db->prepare("DELETE from arsse_feeds where orphaned <= ?", "datetime")->run($limit);
        // commit changes and return
        $tr->commit();
        return $out;
    }

    public function feedMatchLatest(int $feedID, int $count): Db\Result {
        return $this->db->prepare(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed is ? ORDER BY modified desc, id desc limit ?",
            'int', 'int'
        )->run($feedID, $count);
    }

    public function feedMatchIds(int $feedID, array $ids = [], array $hashesUT = [], array $hashesUC = [], array $hashesTC = []): Db\Result {
        // compile SQL IN() clauses and necessary type bindings for the four identifier lists
        list($cId, $tId)     = $this->generateIn($ids, "str");
        list($cHashUT, $tHashUT) = $this->generateIn($hashesUT, "str");
        list($cHashUC, $tHashUC) = $this->generateIn($hashesUC, "str");
        list($cHashTC, $tHashTC) = $this->generateIn($hashesTC, "str");
        // perform the query
        return $articles = $this->db->prepare(
            "SELECT id, edited, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed is ? and (guid in($cId) or url_title_hash in($cHashUT) or url_content_hash in($cHashUC) or title_content_hash in($cHashTC))",
            'int', $tId, $tHashUT, $tHashUC, $tHashTC
        )->run($feedID, $ids, $hashesUT, $hashesUC, $hashesTC);
    }

    protected function articleQuery(string $user, Context $context, array $extraColumns = []): Query {
        $extraColumns = implode(",", $extraColumns);
        if (strlen($extraColumns)) {
            $extraColumns .= ",";
        }
        $q = new Query(
            "SELECT 
                $extraColumns
                arsse_articles.id as id,
                arsse_articles.feed as feed,
                max(
                    arsse_articles.modified, 
                    coalesce((select modified from arsse_marks where article is arsse_articles.id and subscription in (select sub from subscribed_feeds)),'')
                ) as modified_date,
                NOT (select count(*) from arsse_marks where article is arsse_articles.id and read is 1 and subscription in (select sub from subscribed_feeds)) as unread,
                (select count(*) from arsse_marks where article is arsse_articles.id and starred is 1 and subscription in (select sub from subscribed_feeds)) as starred,
                (select max(id) from arsse_editions where article is arsse_articles.id) as edition,
                subscribed_feeds.sub as subscription
            FROM arsse_articles"
        );
        $q->setOrder("edition".($context->reverse ? " desc" : ""));
        $q->setLimit($context->limit, $context->offset);
        $q->setCTE("user(user)", "SELECT ?", "str", $user);
        if ($context->subscription()) {
            // if a subscription is specified, make sure it exists
            $id = $this->subscriptionValidateId($user, $context->subscription)['feed'];
            // add a basic CTE that will join in only the requested subscription
            $q->setCTE("subscribed_feeds(id,sub)", "SELECT ?,?", ["int","int"], [$id,$context->subscription], "join subscribed_feeds on feed is subscribed_feeds.id");
        } elseif ($context->folder()) {
            // if a folder is specified, make sure it exists
            $this->folderValidateId($user, $context->folder);
            // if it does exist, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder)", "SELECT ? union select id from arsse_folders join folders on parent is folder", "int", $context->folder);
            // add another CTE for the subscriptions within the folder
            $q->setCTE("subscribed_feeds(id,sub)", "SELECT feed,id from arsse_subscriptions join user on user is owner join folders on arsse_subscriptions.folder is folders.folder", [], [], "join subscribed_feeds on feed is subscribed_feeds.id");
        } else {
            // otherwise add a CTE for all the user's subscriptions
            $q->setCTE("subscribed_feeds(id,sub)", "SELECT feed,id from arsse_subscriptions join user on user is owner", [], [], "join subscribed_feeds on feed is subscribed_feeds.id");
        }
        if ($context->edition()) {
            // if an edition is specified, filter for its previously identified article
            $q->setWhere("arsse_articles.id is (select article from arsse_editions where id is ?)", "int", $context->edition);
        } elseif ($context->article()) {
            // if an article is specified, filter for it (it has already been validated above)
            $q->setWhere("arsse_articles.id is ?", "int", $context->article);
        }
        if ($context->editions()) {
            // if multiple specific editions have been requested, prepare a CTE to list them and their articles
            if (!$context->editions) {
                throw new Db\ExceptionInput("tooShort", ['field' => "editions", 'action' => __FUNCTION__, 'min' => 1]); // must have at least one array element
            } elseif (sizeof($context->editions) > 50) {
                throw new Db\ExceptionInput("tooLong", ['field' => "editions", 'action' => __FUNCTION__, 'max' => 50]); // must not have more than 50 array elements
            }
            list($inParams, $inTypes) = $this->generateIn($context->editions, "int");
            $q->setCTE("requested_articles(id,edition)",
                "SELECT article,id as edition from arsse_editions where edition in ($inParams)",
                $inTypes,
                $context->editions
            );
            $q->setWhere("arsse_articles.id in (select id from requested_articles)");
        } elseif ($context->articles()) {
            // if multiple specific articles have been requested, prepare a CTE to list them and their articles
            if (!$context->articles) {
                throw new Db\ExceptionInput("tooShort", ['field' => "articles", 'action' => __FUNCTION__, 'min' => 1]); // must have at least one array element
            } elseif (sizeof($context->articles) > 50) {
                throw new Db\ExceptionInput("tooLong", ['field' => "articles", 'action' => __FUNCTION__, 'max' => 50]); // must not have more than 50 array elements
            }
            list($inParams, $inTypes) = $this->generateIn($context->articles, "int");
            $q->setCTE("requested_articles(id,edition)",
                "SELECT id,(select max(id) from arsse_editions where article is arsse_articles.id) as edition from arsse_articles where arsse_articles.id in ($inParams)",
                $inTypes,
                $context->articles
            );
            $q->setWhere("arsse_articles.id in (select id from requested_articles)");
        } else {
            // if neither list is specified, mock an empty table
            $q->setCTE("requested_articles(id,edition)", "SELECT 'empty','table' where 1 is 0");
        }
        // filter based on label by ID or name
        if ($context->label() || $context->labelName()) {
            if ($context->label()) {
                $id = $this->labelValidateId($user, $context->label, false)['id'];
            } else {
                $id = $this->labelValidateId($user, $context->labelName, true)['id'];
            }
            $q->setWhere("exists(select article from arsse_label_members where assigned is 1 and article is arsse_articles.id and label is ?)", "int", $id);
        }
        // filter based on edition offset
        if ($context->oldestEdition()) {
            $q->setWhere("edition >= ?", "int", $context->oldestEdition);
        }
        if ($context->latestEdition()) {
            $q->setWhere("edition <= ?", "int", $context->latestEdition);
        }
        // filter based on lastmod time
        if ($context->modifiedSince()) {
            $q->setWhere("modified_date >= ?", "datetime", $context->modifiedSince);
        }
        if ($context->notModifiedSince()) {
            $q->setWhere("modified_date <= ?", "datetime", $context->notModifiedSince);
        }
        // filter for un/read and un/starred status if specified
        if ($context->unread()) {
            $q->setWhere("unread is ?", "bool", $context->unread);
        }
        if ($context->starred()) {
            $q->setWhere("starred is ?", "bool", $context->starred);
        }
        // return the query
        return $q;
    }

    public function articleList(string $user, Context $context = null): Db\Result {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        $columns = [
            "arsse_articles.url as url",
            "title",
            "author",
            "content",
            "guid",
            "published as published_date",
            "edited as edited_date",
            "url_title_hash||':'||url_content_hash||':'||title_content_hash as fingerprint",
            "arsse_enclosures.url as media_url",
            "arsse_enclosures.type as media_type",
        ];
        $q = $this->articleQuery($user, $context, $columns);
        $q->setJoin("left join arsse_enclosures on arsse_enclosures.article is arsse_articles.id");
        // perform the query and return results
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues());
    }

    public function articleCount(string $user, Context $context = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        $q = $this->articleQuery($user, $context);
        $q->pushCTE("selected_articles");
        $q->setBody("SELECT count(*) from selected_articles");
        return $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

    public function articleMark(string $user, array $data, Context $context = null): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        // sanitize input
        $values = [
            isset($data['read']) ? $data['read'] : null,
            isset($data['starred']) ? $data['starred'] : null,
        ];
        // the two queries we want to execute to make the requested changes
        $queries = [
            "UPDATE arsse_marks 
                set 
                    read = case when (select honour_read from target_articles where target_articles.id is article) is 1 then (select read from target_values) else read end,
                    starred = coalesce((select starred from target_values),starred),
                    modified = CURRENT_TIMESTAMP  
                WHERE 
                    subscription in (select sub from subscribed_feeds)
                    and article in (select id from target_articles where to_insert is 0 and (honour_read is 1 or honour_star is 1))",
            "INSERT INTO arsse_marks(subscription,article,read,starred)
                select 
                    (select id from arsse_subscriptions join user on user is owner where arsse_subscriptions.feed is target_articles.feed),
                    id,
                    coalesce((select read from target_values) * honour_read,0),
                    coalesce((select starred from target_values),0)
                from target_articles where to_insert is 1 and (honour_read is 1 or honour_star is 1)"
        ];
        $out = 0;
        // wrap this UPDATE and INSERT together into a transaction
        $tr = $this->begin();
        // if an edition context is specified, make sure it's valid
        if ($context->edition()) {
            // make sure the edition exists
            $edition = $this->articleValidateEdition($user, $context->edition);
            // if the edition is not the latest, do not mark the read flag
            if (!$edition['current']) {
                $values[0] = null;
            }
        } elseif ($context->article()) {
            // otherwise if an article context is specified, make sure it's valid
            $this->articleValidateId($user, $context->article);
        }
        // execute each query in sequence
        foreach ($queries as $query) {
            // first build the query which will select the target articles; we will later turn this into a CTE for the actual query that manipulates the articles
            $q = $this->articleQuery($user, $context, [
                "(not exists(select article from arsse_marks where article is arsse_articles.id and subscription in (select sub from subscribed_feeds))) as to_insert",
                "((select read from target_values) is not null and (select read from target_values) is not (coalesce((select read from arsse_marks where article is arsse_articles.id and subscription in (select sub from subscribed_feeds)),0)) and (not exists(select * from requested_articles) or (select max(id) from arsse_editions where article is arsse_articles.id) in (select edition from requested_articles))) as honour_read",
                "((select starred from target_values) is not null and (select starred from target_values) is not (coalesce((select starred from arsse_marks where article is arsse_articles.id and subscription in (select sub from subscribed_feeds)),0))) as honour_star",
            ]);
            // common table expression with the values to set
            $q->setCTE("target_values(read,starred)", "SELECT ?,?", ["bool","bool"], $values);
            // push the current query onto the CTE stack and execute the query we're actually interested in
            $q->pushCTE("target_articles");
            $q->setBody($query);
            $out += $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        }
        // commit the transaction
        $tr->commit();
        return (bool) $out;
    }

    public function articleStarred(string $user): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare(
            "SELECT
                count(*) as total,
                coalesce(sum(not read),0) as unread,
                coalesce(sum(read),0) as read
            FROM (
                select read from arsse_marks where starred is 1 and subscription in (select id from arsse_subscriptions where owner is ?)
            )", "str"
        )->run($user)->getRow();
    }

    public function articleLabelsGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $id = $this->articleValidateId($user, $id)['article'];
        $out = $this->db->prepare("SELECT id,name from arsse_labels where owner is ? and exists(select id from arsse_label_members where article is ? and label is arsse_labels.id and assigned is 1)", "str", "int")->run($user, $id)->getAll();
        if (!$out) {
            return $out;
        } else {
            // flatten the result to return just the label ID or name
            return array_column($out, !$byName ? "id" : "name");
        }
    }

    public function articleCleanup(): bool {
        $query = $this->db->prepare(
            "WITH target_feed(id,subs) as (".
                "SELECT 
                    id, (select count(*) from arsse_subscriptions where feed is arsse_feeds.id) as subs
                from arsse_feeds where id is ?".
            "), excepted_articles(id,edition) as (".
                "SELECT 
                    arsse_articles.id, (select max(id) from arsse_editions where article is arsse_articles.id) as edition 
                from arsse_articles
                    join target_feed on arsse_articles.feed is target_feed.id 
                order by edition desc limit ?".
            ") ".
            "DELETE from arsse_articles where 
                feed is (select max(id) from target_feed) 
                and id not in (select id from excepted_articles)
                and (select count(*) from arsse_marks where article is arsse_articles.id and starred is 1) is 0
                and (
                    coalesce((select max(modified) from arsse_marks where article is arsse_articles.id),modified) <= ?
                    or ((select max(subs) from target_feed) is (select count(*) from arsse_marks where article is arsse_articles.id and read is 1) and coalesce((select max(modified) from arsse_marks where article is arsse_articles.id),modified) <= ?)
                )
            ", "int", "int", "datetime", "datetime"
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

    protected function articleValidateId(string $user, $id): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "article", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepare(
            "SELECT 
                arsse_articles.id as article, 
                (select max(id) from arsse_editions where article is arsse_articles.id) as edition
            FROM arsse_articles
                join arsse_feeds on arsse_feeds.id is arsse_articles.feed
                join arsse_subscriptions on arsse_subscriptions.feed is arsse_feeds.id
            WHERE 
                arsse_articles.id is ? and arsse_subscriptions.owner is ?",
            "int", "str"
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "article", 'id' => $id]);
        }
        return $out;
    }

    protected function articleValidateEdition(string $user, int $id): array {
        if (!ValueInfo::id($id)) {
            throw new Db\ExceptionInput("typeViolation", ["action" => $this->caller(), "field" => "edition", 'type' => "int > 0"]); // @codeCoverageIgnore
        }
        $out = $this->db->prepare(
            "SELECT 
                arsse_editions.id as edition, 
                arsse_editions.article as article,
                (arsse_editions.id is (select max(id) from arsse_editions where article is arsse_editions.article)) as current
            FROM arsse_editions
                join arsse_articles on arsse_editions.article is arsse_articles.id
                join arsse_feeds on arsse_feeds.id is arsse_articles.feed
                join arsse_subscriptions on arsse_subscriptions.feed is arsse_feeds.id
            WHERE 
                edition is ? and arsse_subscriptions.owner is ?",
            "int", "str"
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "edition", 'id' => $id]);
        }
        return $out;
    }

    public function editionLatest(string $user, Context $context = null): int {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $context = $context ?? new Context;
        $q = new Query("SELECT max(arsse_editions.id) from arsse_editions left join arsse_articles on article is arsse_articles.id left join arsse_feeds on arsse_articles.feed is arsse_feeds.id");
        if ($context->subscription()) {
            // if a subscription is specified, make sure it exists
            $id = $this->subscriptionValidateId($user, $context->subscription)['feed'];
            // a simple WHERE clause is required here
            $q->setWhere("arsse_feeds.id is ?", "int", $id);
        } else {
            $q->setCTE("user(user)", "SELECT ?", "str", $user);
            $q->setCTE("feeds(feed)", "SELECT feed from arsse_subscriptions join user on user is owner", [], [], "join feeds on arsse_articles.feed is feeds.feed");
        }
        return (int) $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->getValue();
    }

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

    public function labelList(string $user, bool $includeEmpty = true): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        return $this->db->prepare(
            "SELECT 
                id,name,
                (select count(*) from arsse_label_members where label is id and assigned is 1) as articles,
                (select count(*) from arsse_label_members 
                    join arsse_marks on arsse_label_members.article is arsse_marks.article and arsse_label_members.subscription is arsse_marks.subscription
                 where label is id and assigned is 1 and read is 1
                ) as read
            FROM arsse_labels where owner is ? and articles >= ?
            ", "str", "int"
        )->run($user, !$includeEmpty);
    }

    public function labelRemove(string $user, $id, bool $byName = false): bool {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        $this->labelValidateId($user, $id, $byName, false);
        $field = $byName ? "name" : "id";
        $type = $byName ? "str" : "int";
        $changes = $this->db->prepare("DELETE FROM arsse_labels where owner is ? and $field is ?", "str", $type)->run($user, $id)->changes();
        if (!$changes) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
        }
        return true;
    }

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
                (select count(*) from arsse_label_members where label is id and assigned is 1) as articles,
                (select count(*) from arsse_label_members 
                    join arsse_marks on arsse_label_members.article is arsse_marks.article and arsse_label_members.subscription is arsse_marks.subscription
                 where label is id and assigned is 1 and read is 1
                ) as read
            FROM arsse_labels where $field is ? and owner is ?
            ", $type, "str"
        )->run($id, $user)->getRow();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
        }
        return $out;
    }

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
        $out = (bool) $this->db->prepare("UPDATE arsse_labels set $setClause, modified = CURRENT_TIMESTAMP where owner is ? and $field is ?", $setTypes, "str", $type)->run($setValues, $user, $id)->changes();
        if (!$out) {
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "label", 'id' => $id]);
        }
        return $out;
    }

    public function labelArticlesGet(string $user, $id, bool $byName = false): array {
        if (!Arsse::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // just do a syntactic check on the label ID
        $this->labelValidateId($user, $id, $byName, false);
        $field = !$byName ? "id" : "name";
        $type = !$byName ? "int" : "str";
        $out = $this->db->prepare("SELECT article from arsse_label_members join arsse_labels on label is id where assigned is 1 and $field is ? and owner is ?", $type, "str")->run($id, $user)->getAll();
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

    public function labelArticlesSet(string $user, $id, Context $context = null, bool $remove = false, bool $byName = false): bool {
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
        $q->setWhere("exists(select article from arsse_label_members where label is ? and article is arsse_articles.id)", "int", $id);
        $q->pushCTE("target_articles");
        $q->setBody(
            "UPDATE arsse_label_members set assigned = ?, modified = CURRENT_TIMESTAMP where label is ? and assigned is not ? and article in (select id from target_articles)", 
            ["bool","int","bool"], 
            [!$remove, $id, !$remove]
        );
        $out += $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        // next, if we're not removing, add any new entries that need to be added
        if (!$remove) {
            $q = $this->articleQuery($user, $context);
            $q->setWhere("not exists(select article from arsse_label_members where label is ? and article is arsse_articles.id)", "int", $id);
            $q->pushCTE("target_articles");
            $q->setBody(
                "INSERT INTO 
                    arsse_label_members(label,article,subscription) 
                SELECT 
                    ?,id,
                    (select id from arsse_subscriptions join user on user is owner where arsse_subscriptions.feed is target_articles.feed) 
                FROM target_articles",
                "int", $id
            );
            $out += $this->db->prepare($q->getQuery(), $q->getTypes())->run($q->getValues())->changes();
        }
        // commit the transaction
        $tr->commit();
        return (bool) $out;
    }

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
            $l = $this->db->prepare("SELECT id,name from arsse_labels where $field is ? and owner is ?", $type, "str")->run($id, $user)->getRow();
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
