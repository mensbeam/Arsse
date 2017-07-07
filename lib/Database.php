<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use PasswordGenerator\Generator as PassGen;
use JKingWeb\Arsse\Misc\Query;
use JKingWeb\Arsse\Misc\Context;

class Database {

    const SCHEMA_VERSION = 1;
    const FORMAT_TS      = "Y-m-d h:i:s";
    const FORMAT_DATE    = "Y-m-d";
    const FORMAT_TIME    = "h:i:s";

    public    $db;
    protected $dateFormatDefault = "sql";

    public function __construct() {
        $driver = Data::$conf->dbDriver;
        $this->db = new $driver(INSTALL);
        $ver = $this->db->schemaVersion();
        if(!INSTALL && $ver < self::SCHEMA_VERSION) {
            $this->db->schemaUpdate(self::SCHEMA_VERSION);
        }
    }

    protected function caller(): string {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];
    }

    public function dateFormatDefault(string $set = null): string {
        if(is_null($set)) return $this->dateFormatDefault;
        $set = strtolower($set);
        if(in_array($set, ["sql", "iso8601", "unix", "http"])) {
            $this->dateFormatDefault = $set;
        }
        return $this->dateFormatDefault;
    }
    
    static public function listDrivers(): array {
        $sep = \DIRECTORY_SEPARATOR;
        $path = __DIR__.$sep."Db".$sep;
        $classes = [];
        foreach(glob($path."*".$sep."Driver.php") as $file) {
            $name = basename(dirname($file));
            $class = NS_BASE."Db\\$name\\Driver";
            $classes[$class] = $class::driverName();
        }
        return $classes;
    }

    public function schemaVersion(): int {
        return $this->db->schemaVersion();
    }

    public function schemaUpdate(): bool {
        if($this->db->schemaVersion() < self::SCHEMA_VERSION) return $this->db->schemaUpdate(self::SCHEMA_VERSION);
        return false;
    }

    protected function generateSet(array $props, array $valid): array {
        $out = [
            [], // query clause
            [], // binding types
            [], // binding values
        ];
        foreach($valid as $prop => $type) {
            if(!array_key_exists($prop, $props)) continue;
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
        $out[0] = implode(",",array_fill(0,sizeof($values),"?"));
        // the binding types are just a repetition of the supplied type
        $out[1] = array_fill(0,sizeof($values),$type);
        return $out;
    }

    public function begin(): Db\Transaction {
        return $this->db->begin();
    }

    public function settingGet(string $key) {
        return $this->db->prepare("SELECT value from arsse_settings where key is ?", "str")->run($key)->getValue();
    }
    
    public function settingSet(string $key, string $value): bool {
        $out = !$this->db->prepare("UPDATE arsse_settings set value = ? where key is ?", "str", "str")->run($value, $key)->changes();
        if(!$out) {
            $out = $this->db->prepare("INSERT INTO arsse_settings(key,value)", "str", "str")->run($key, $value)->changes();
        }
        return (bool) $out;
    }

    public function settingRemove(string $key): bool {
        $this->db->prepare("DELETE from arsse_settings where key is ?", "str")->run($key);
        return true;
    }

    public function userExists(string $user): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (bool) $this->db->prepare("SELECT count(*) from arsse_users where id is ?", "str")->run($user)->getValue();
    }

    public function userAdd(string $user, string $password = null): string {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if($this->userExists($user)) throw new User\Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length(Data::$conf->userTempPasswordLength)->get();
        $hash = "";
        if(strlen($password) > 0) $hash = password_hash($password, \PASSWORD_DEFAULT);
        $this->db->prepare("INSERT INTO arsse_users(id,password) values(?,?)", "str", "str")->runArray([$user,$hash]);
        return $password;
    }

    public function userRemove(string $user): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if($this->db->prepare("DELETE from arsse_users where id is ?", "str")->run($user)->changes() < 1) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return true;
    }

    public function userList(string $domain = null): array {
        $out = [];
        if($domain !== null) {
            if(!Data::$user->authorize("@".$domain, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $domain]);
            $domain = str_replace(["\\","%","_"],["\\\\", "\\%", "\\_"], $domain);
            $domain = "%@".$domain;
            foreach($this->db->prepare("SELECT id from arsse_users where id like ?", "str")->run($domain) as $user) {
                $out[] = $user['id'];
            }
        } else {
            if(!Data::$user->authorize("", __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => "global"]);
            foreach($this->db->prepare("SELECT id from arsse_users")->run() as $user) {
                $out[] = $user['id'];
            }
        }
        return $out;
    }

    public function userPasswordGet(string $user): string {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return (string) $this->db->prepare("SELECT password from arsse_users where id is ?", "str")->run($user)->getValue();
    }

    public function userPasswordSet(string $user, string $password = null): string {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length(Data::$conf->userTempPasswordLength)->get();
        $hash = "";
        if(strlen($password) > 0) $hash = password_hash($password, \PASSWORD_DEFAULT);
        $this->db->prepare("UPDATE arsse_users set password = ? where id is ?", "str", "str")->run($hash, $user);
        return $password;
    }

    public function userPropertiesGet(string $user): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $prop = $this->db->prepare("SELECT name,rights from arsse_users where id is ?", "str")->run($user)->getRow();
        if(!$prop) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return $prop;
    }

    public function userPropertiesSet(string $user, array $properties): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $valid = [ // FIXME: add future properties
            "name" => "str",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($properties, $valid);
        $this->db->prepare("UPDATE arsse_users set $setClause where id is ?", $setTypes, "str")->run($setValues, $user);
        return $this->userPropertiesGet($user);
    }

    public function userRightsGet(string $user): int {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (int) $this->db->prepare("SELECT rights from arsse_users where id is ?", "str")->run($user)->getValue();
    }

    public function userRightsSet(string $user, int $rights): bool {
        if(!Data::$user->authorize($user, __FUNCTION__, $rights)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $this->db->prepare("UPDATE arsse_users set rights = ? where id is ?", "int", "str")->run($rights, $user);
        return true;
    }

    public function folderAdd(string $user, array $data): int {
        // If the user isn't authorized to perform this action then throw an exception.
        if(!Data::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // if the desired folder name is missing or invalid, throw an exception
        if(!array_key_exists("name", $data) || $data['name']=="") {
            throw new Db\ExceptionInput("missing", ["action" => __FUNCTION__, "field" => "name"]);
        } else if(!strlen(trim($data['name']))) {
            throw new Db\ExceptionInput("whitespace", ["action" => __FUNCTION__, "field" => "name"]);
        }
        // normalize folder's parent, if there is one
        $parent = array_key_exists("parent", $data) ? (int) $data['parent'] : 0;
        if($parent===0) {
            // if no parent is specified, do nothing
            $parent = null;
        } else {
            // if a parent is specified, make sure it exists and belongs to the user; get its root (first-level) folder if it's a nested folder
            $p = $this->db->prepare("SELECT id from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $parent)->getValue();
            if(!$p) throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "parent", 'id' => $parent]);
        }
        // check if a folder by the same name already exists, because nulls are wonky in SQL
        // FIXME: How should folder name be compared? Should a Unicode normalization be applied before comparison and insertion?
        if($this->db->prepare("SELECT count(*) from arsse_folders where owner is ? and parent is ? and name is ?", "str", "int", "str")->run($user, $parent, $data['name'])->getValue() > 0) {
            throw new Db\ExceptionInput("constraintViolation"); // FIXME: There needs to be a practical message here
        }
        // actually perform the insert (!)
        return $this->db->prepare("INSERT INTO arsse_folders(owner,parent,name) values(?,?,?)", "str", "int", "str")->run($user, $parent, $data['name'])->lastId();
    }

    public function folderList(string $user, int $parent = null, bool $recursive = true): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if(!Data::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // check to make sure the parent exists, if one is specified
        if(!is_null($parent)) {
            if(!$this->db->prepare("SELECT count(*) from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $parent)->getValue()) {
                throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "parent", 'id' => $parent]);
            }
        }
        // if we're not returning a recursive list we can use a simpler query
        if(!$recursive) {
            return $this->db->prepare("SELECT id,name,parent from arsse_folders where owner is ? and parent is ?", "str", "int")->run($user, $parent);
        } else {
            return $this->db->prepare(
                "WITH RECURSIVE folders(id) as (SELECT id from arsse_folders where owner is ? and parent is ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id) ".
                "SELECT id,name,parent from arsse_folders where id in(SELECT id from folders) order by name",
            "str", "int")->run($user, $parent);
        }
    }

    public function folderRemove(string $user, int $id): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $changes = $this->db->prepare("DELETE FROM arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->changes();
        if(!$changes) throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        return true;
    }

    public function folderPropertiesGet(string $user, int $id): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $props = $this->db->prepare("SELECT id,name,parent from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->getRow();
        if(!$props) throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        return $props;
    }

    public function folderPropertiesSet(string $user, int $id, array $data): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        // validate the folder ID and, if specified, the parent to move it to
        $parent = null;
        if(array_key_exists("parent", $data)) $parent = $data['parent'];
        $f = $this->folderValidateId($user, $id, $parent, true);
        // if a new name is specified, validate it
        if(array_key_exists("name", $data)) {
            $this->folderValidateName($data['name']);
        }
        $data = array_merge($f, $data);
        // check to make sure the target folder name/location would not create a duplicate (we must do this check because null is not distinct in SQL)
        $existing = $this->db->prepare("SELECT id from arsse_folders where owner is ? and parent is ? and name is ?", "str", "int", "str")->run($user, $data['parent'], $data['name'])->getValue();
        if(!is_null($existing) && $existing != $id) {
            throw new Db\ExceptionInput("constraintViolation"); // FIXME: There needs to be a practical message here
        }
        $valid = [
            'name' => "str",
            'parent' => "int",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($data, $valid);
        return (bool) $this->db->prepare("UPDATE arsse_folders set $setClause where owner is ? and id is ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
    }

    protected function folderValidateId(string $user, int $id = null, int $parent = null, bool $subject = false): array {
        if(is_null($id)) {
            // if no ID is specified this is a no-op, unless a parent is specified, which is always a circular dependence
            if(!is_null($parent)) {
                throw new Db\ExceptionInput("circularDependence", ["action" => $this->caller(), "field" => "parent", 'id' => $parent]);
            }
            return [name => null, parent => null];
        }
        // check whether the folder exists and is owned by the user
        $f = $this->db->prepare("SELECT name,parent from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->getRow();
        if(!$f) throw new Db\ExceptionInput($subject ? "subjectMissing" : "idMissing", ["action" => $this->caller(), "field" => "folder", 'id' => $parent]);
        // if we're moving a folder to a new parent, check that the parent is valid
        if(!is_null($parent)) {
            // make sure both that the parent exists, and that the parent is not either the folder itself or one of its children (a circular dependence)
            $p = $this->db->prepare(
                "WITH RECURSIVE folders(id) as (SELECT id from arsse_folders where owner is ? and id is ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id) ".
                "SELECT id,(id not in (select id from folders)) as valid from arsse_folders where owner is ? and id is ?",
                "str", "int", "str", "int"
            )->run($user, $id, $user, $parent)->getRow();
            if(!$p) {
                // if the parent doesn't exist or doesn't below to the user, throw an exception
                throw new Db\ExceptionInput("idMissing", ["action" => $this->caller(), "field" => "parent", 'id' => $parent]);
            } else {
                // if using the desired parent would create a circular dependence, throw a different exception
                if(!$p['valid']) throw new Db\ExceptionInput("circularDependence", ["action" => $this->caller(), "field" => "parent", 'id' => $parent]);
            }
        }
        return $f;
    }

    protected function folderValidateName($name): bool {
        $name = (string) $name;
        if(!strlen($name)) {
            throw new Db\ExceptionInput("missing", ["action" => $this->caller(), "field" => "name"]);
        } else if(!strlen(trim($name))) {
            throw new Db\ExceptionInput("whitespace", ["action" => $this->caller(), "field" => "name"]);
        } else {
            return true;
        }
    }

    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = ""): int {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        // check to see if the feed exists
        $feedID = $this->db->prepare("SELECT id from arsse_feeds where url is ? and username is ? and password is ?", "str", "str", "str")->run($url, $fetchUser, $fetchPassword)->getValue();
        if(is_null($feedID)) {
            // if the feed doesn't exist add it to the database; we do this unconditionally so as to lock SQLite databases for as little time as possible
            $feedID = $this->db->prepare('INSERT INTO arsse_feeds(url,username,password) values(?,?,?)', 'str', 'str', 'str')->run($url, $fetchUser, $fetchPassword)->lastId();
            try {
                // perform an initial update on the newly added feed
                $this->feedUpdate($feedID, true);
            } catch(\Throwable $e) {
                // if the update fails, delete the feed we just added
                $this->db->prepare('DELETE from arsse_feeds where id is ?', 'int')->run($feedID);
                throw $e;
            }
        }
        // Add the feed to the user's subscriptions and return the new subscription's ID.
        return $this->db->prepare('INSERT INTO arsse_subscriptions(owner,feed) values(?,?)', 'str', 'int')->run($user, $feedID)->lastId();
    }

    public function subscriptionList(string $user, int $folder = null, int $id = null): Db\Result {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        // create a complex query
        $q = new Query(
            "SELECT 
                arsse_subscriptions.id,
                url,favicon,source,folder,pinned,err_count,err_msg,order_type,
                DATEFORMAT(?, added) as added,
                topmost.top as top_folder,
                coalesce(arsse_subscriptions.title, arsse_feeds.title) as title,
                (SELECT count(*) from arsse_articles where feed is arsse_subscriptions.feed) - (SELECT count(*) from arsse_marks join user on user is owner join arsse_articles on article = arsse_articles.id where feed is arsse_feeds.id and read is 1) as unread
             from arsse_subscriptions 
                join user on user is owner 
                join arsse_feeds on feed = arsse_feeds.id 
                left join topmost on folder=f_id",
             "", // where terms
             "pinned desc, title" // order by terms
        );
        // define common table expressions
        $q->setCTE("user(user) as (SELECT ?)", "str", $user);  // the subject user; this way we only have to pass it to prepare() once
        // topmost folders belonging to the user
        $q->setCTE("topmost(f_id,top) as (select id,id from arsse_folders join user on owner is user where parent is null union select id,top from arsse_folders join topmost on parent=f_id)");
        if(!is_null($id)) {
            // this condition facilitates the implementation of subscriptionPropertiesGet, which would otherwise have to duplicate the complex query; it takes precedence over a specified folder
            // if an ID is specified, add a suitable WHERE condition and bindings
            $q->setWhere("arsse_subscriptions.id is ?", "int", $id);
        } else if(!is_null($folder)) {
            // if a folder is specified, make sure it exists
            $this->folderValidateId($user, $folder);
            // if it does exist, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder) as (SELECT ? union select id from arsse_folders join folders on parent is folder)", "int", $folder);
            // add a suitable WHERE condition
            $q->setWhere("folder in (select folder from folders)");
        }
        return $this->db->prepare($q, "str")->run($this->dateFormatDefault);
    }

    public function subscriptionRemove(string $user, int $id): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $changes = $this->db->prepare("DELETE from arsse_subscriptions where owner is ? and id is ?", "str", "int")->run($user, $id)->changes();
        if(!$changes) throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        return true;
    }

    public function subscriptionPropertiesGet(string $user, int $id): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        // disable authorization checks for the list call
        Data::$user->authorizationEnabled(false);
        $sub = $this->subscriptionList($user, null, $id)->getRow();
        Data::$user->authorizationEnabled(true);
        if(!$sub) throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        return $sub;
    }

    public function subscriptionPropertiesSet(string $user, int $id, array $data): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $tr = $this->db->begin();
        if(!$this->db->prepare("SELECT count(*) from arsse_subscriptions where owner is ? and id is ?", "str", "int")->run($user, $id)->getValue()) {
            // if the ID doesn't exist or doesn't belong to the user, throw an exception
            throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $id]);
        }
        if(array_key_exists("folder", $data)) {
            // ensure the target folder exists and belong to the user
            $this->folderValidateId($user, $data['folder']);
        }
        if(array_key_exists("title", $data)) {
            // if the title is null, this signals intended use of the default title; otherwise make sure it's not effectively an empty string
            if(!is_null($data['title'])) {
                $title = (string) $data['title'];
                if(!strlen($title)) throw new Db\ExceptionInput("missing", ["action" => __FUNCTION__, "field" => "title"]);
                if(!strlen(trim($title))) throw new Db\ExceptionInput("whitespace", ["action" => __FUNCTION__, "field" => "title"]);
                $data['title'] = $title;
            }
        }
        $valid = [
            'title'      => "str",
            'folder'     => "int",
            'order_type' => "strict int",
            'pinned'     => "strict bool",
        ];
        list($setClause, $setTypes, $setValues) = $this->generateSet($data, $valid);
        $out = (bool) $this->db->prepare("UPDATE arsse_subscriptions set $setClause where owner is ? and id is ?", $setTypes, "str", "int")->run($setValues, $user, $id)->changes();
        $tr->commit();
        return $out;
    }

    protected function subscriptionValidateId(string $user, int $id): array {
        $out = $this->db->prepare("SELECT feed from arsse_subscriptions where id is ? and owner is ?", "int", "str")->run($id, $user)->getRow();
        if(!$out) throw new Db\ExceptionInput("idMissing", ["action" => $this->caller(), "field" => "subscription", 'id' => $id]);
        return $out;
    }

    public function feedListStale(): array {
        $feeds = $this->db->prepare("SELECT id from arsse_feeds where next_fetch <= CURRENT_TIMESTAMP")->run()->getAll();
        return array_column($feeds,'id');
    }
    
    public function feedUpdate(int $feedID, bool $throwError = false): bool {
        $tr = $this->db->begin();
        // check to make sure the feed exists
        $f = $this->db->prepare("SELECT url, username, password, DATEFORMAT('http', modified) AS lastmodified, etag, err_count FROM arsse_feeds where id is ?", "int")->run($feedID)->getRow();
        if(!$f) throw new Db\ExceptionInput("subjectMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $feedID]);
        // the Feed object throws an exception when there are problems, but that isn't ideal
        // here. When an exception is thrown it should update the database with the
        // error instead of failing; if other exceptions are thrown, we should simply roll back
        try {
            $feed = new Feed($feedID, $f['url'], (string)$f['lastmodified'], $f['etag'], $f['username'], $f['password']);
            if(!$feed->modified) {
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
            )->run(Feed::nextFetchOnError($f['err_count']), $e->getMessage(),$feedID);
            $tr->commit();
            if($throwError) throw $e;
            return false;
        }
        //prepare the necessary statements to perform the update
        if(sizeof($feed->newItems) || sizeof($feed->changedItems)) {
            $qInsertEnclosure = $this->db->prepare("INSERT INTO arsse_enclosures(article,url,type) values(?,?,?)", 'int', 'str', 'str');
            $qInsertCategory = $this->db->prepare("INSERT INTO arsse_categories(article,name) values(?,?)", 'int', 'str');
            $qInsertEdition = $this->db->prepare("INSERT INTO arsse_editions(article) values(?)", 'int');
        }
        if(sizeof($feed->newItems)) {
            $qInsertArticle = $this->db->prepare(
                "INSERT INTO arsse_articles(url,title,author,published,edited,guid,content,url_title_hash,url_content_hash,title_content_hash,feed) values(?,?,?,?,?,?,?,?,?,?,?)",
                'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str', 'int'
            );
        }
        if(sizeof($feed->changedItems)) {
            $qDeleteEnclosures = $this->db->prepare("DELETE FROM arsse_enclosures WHERE article is ?", 'int');
            $qDeleteCategories = $this->db->prepare("DELETE FROM arsse_categories WHERE article is ?", 'int');
            $qClearReadMarks = $this->db->prepare("UPDATE arsse_marks SET read = 0, modified = CURRENT_TIMESTAMP WHERE article is ? and read is 1", 'int');
            $qUpdateArticle = $this->db->prepare(
                "UPDATE arsse_articles SET url = ?, title = ?, author = ?, published = ?, edited = ?, modified = CURRENT_TIMESTAMP, guid = ?, content = ?, url_title_hash = ?, url_content_hash = ?, title_content_hash = ? WHERE id is ?", 
                'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str', 'int'
            );
        }
        // actually perform updates
        foreach($feed->newItems as $article) {
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
            if($article->enclosureUrl) {
                $qInsertEnclosure->run($articleID,$article->enclosureUrl,$article->enclosureType);
            }
            foreach($article->categories as $c) {
                $qInsertCategory->run($articleID, $c);
            }
            $qInsertEdition->run($articleID);
        }
        foreach($feed->changedItems as $articleID => $article) {
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
            if($article->enclosureUrl) {
                $qInsertEnclosure->run($articleID,$article->enclosureUrl,$article->enclosureType);
            }
            foreach($article->categories as $c) {
                $qInsertCategory->run($articleID, $c);
            }
            $qInsertEdition->run($articleID);
            $qClearReadMarks->run($articleID);
        }
        // lastly update the feed database itself with updated information.
        $this->db->prepare(
            "UPDATE arsse_feeds SET url = ?, title = ?, favicon = ?, source = ?, updated = CURRENT_TIMESTAMP, modified = ?, etag = ?, err_count = 0, err_msg = '', next_fetch = ? WHERE id is ?", 
            'str', 'str', 'str', 'str', 'datetime', 'str', 'datetime', 'int'
        )->run(
            $feed->data->feedUrl,
            $feed->data->title,
            $feed->favicon,
            $feed->data->siteUrl,
            $feed->lastModified,
            $feed->resource->getEtag(),
            $feed->nextFetch,
            $feedID
        );
        $tr->commit();
        return true;
    }

    public function feedMatchLatest(int $feedID, int $count): Db\Result {
        return $this->db->prepare(
            "SELECT id, DATEFORMAT('unix', edited) AS edited_date, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed is ? ORDER BY modified desc, id desc limit ?", 
            'int', 'int'
        )->run($feedID, $count);
    }

    public function feedMatchIds(int $feedID, array $ids = [], array $hashesUT = [], array $hashesUC = [], array $hashesTC = []): Db\Result {
        // compile SQL IN() clauses and necessary type bindings for the four identifier lists
        list($cId,     $tId)     = $this->generateIn($ids,      "str");
        list($cHashUT, $tHashUT) = $this->generateIn($hashesUT, "str");
        list($cHashUC, $tHashUC) = $this->generateIn($hashesUC, "str");
        list($cHashTC, $tHashTC) = $this->generateIn($hashesTC, "str");
        // perform the query
        return $articles = $this->db->prepare(
            "SELECT id, DATEFORMAT('unix', edited) AS edited_date, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed is ? and (guid in($cId) or url_title_hash in($cHashUT) or url_content_hash in($cHashUC) or title_content_hash in($cHashTC))", 
            'int', $tId, $tHashUT, $tHashUC, $tHashTC
        )->run($feedID, $ids, $hashesUT, $hashesUC, $hashesTC);
    }

    public function articleStarredCount(string $user, array $context = []): int {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return $this->db->prepare(
            "WITH RECURSIVE
                user(user) as (SELECT ?),
                subscribed_feeds(id,sub) as (SELECT feed,id from arsse_subscriptions join user on user is owner) ".
            "SELECT count(*) from arsse_marks 
                join user on user is owner 
                join arsse_articles on arsse_marks.article is arsse_articles.id
                join subscribed_feeds on arsse_articles.feed is subscribed_feeds.id
            where starred is 1", 
            "str"
        )->run($user)->getValue();
    }

    public function editionLatest(string $user, Context $context = null): int {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$context) $context = new Context;
        $q = new Query("SELECT max(arsse_editions.id) from arsse_editions left join arsse_articles on article is arsse_articles.id left join arsse_feeds on arsse_articles.feed is arsse_feeds.id");
        if($context->subscription()) {
            // if a subscription is specified, make sure it exists
            $id = $this->subscriptionValidateId($user, $context->subscription)['feed'];
            // a simple WHERE clause is required here
            $q->setWhere("arsse_feeds.id is ?", "int", $id);
        } else {
            $q->setCTE("user(user) as (SELECT ?)", "str", $user);
            $q->setCTE(
                "feeds(feed) as (SELECT feed from arsse_subscriptions join user on user is owner)", 
                [], // binding types 
                [], // binding values
                "join feeds on arsse_articles.feed is feeds.feed" // join expression
            );
        }
        return (int) $this->db->prepare($q)->run()->getValue();
    }

    public function articleList(string $user, Context $context = null): Db\Result {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$context) $context = new Context;
        $q = new Query(
            "SELECT 
                arsse_articles.id as id,
                arsse_articles.url as url,
                title,author,content,guid,
                DATEFORMAT(?, published) as published_date,
                DATEFORMAT(?, edited) as edited_date,
                DATEFORMAT(?, max(
                    modified, 
                    coalesce((select modified from arsse_marks join user on user is owner where article is arsse_articles.id),'')
                )) as modified_date,
                NOT (select count(*) from arsse_marks join user on user is owner where article is arsse_articles.id and read is 1) as unread,
                (select count(*) from arsse_marks join user on user is owner where article is arsse_articles.id and starred is 1) as starred,
                (select max(id) from arsse_editions where article is arsse_articles.id) as edition,
                subscribed_feeds.sub as subscription,
                url_title_hash||':'||url_content_hash||':'||title_content_hash as fingerprint,
                arsse_enclosures.url as media_url,
                arsse_enclosures.type as media_type
            FROM arsse_articles 
                join subscribed_feeds on arsse_articles.feed is subscribed_feeds.id
                left join arsse_enclosures on arsse_enclosures.article is arsse_articles.id
            ",
            "", // WHERE clause
            "edition".($context->reverse ? " desc" : ""), // ORDER BY clause
            $context->limit,
            $context->offset
        );
        $q->setCTE("user(user) as (SELECT ?)", "str", $user);
        if($context->subscription()) {
            // if a subscription is specified, make sure it exists
            $id = $this->subscriptionValidateId($user, $context->subscription)['feed'];
            // add a basic CTE that will join in only the requested subscription
            $q->setCTE("subscribed_feeds(id,sub) as (SELECT ?,?)", ["int","int"], [$id,$context->subscription]);
        } else if($context->folder()) {
            // if a folder is specified, make sure it exists
            $this->folderValidateId($user, $context->folder);
            // if it does exist, add a common table expression to list it and its children so that we select from the entire subtree
            $q->setCTE("folders(folder) as (SELECT ? union select id from arsse_folders join folders on parent is folder)", "int", $context->folder);
            // add another CTE for the subscriptions within the folder
            $q->setCTE("subscribed_feeds(id,sub) as (SELECT feed,id from arsse_subscriptions join user on user is owner join folders on arsse_subscriptions.folder is folders.folder)");
        } else {
            // otherwise add a CTE for all the user's subscriptions
            $q->setCTE("subscribed_feeds(id,sub) as (SELECT feed,id from arsse_subscriptions join user on user is owner)");
        }
        // filter based on edition offset
        if($context->oldestEdition()) $q->setWhere("edition >= ?", "int", $context->oldestEdition);
        if($context->latestEdition()) $q->setWhere("edition <= ?", "int", $context->latestEdition);
        // filter based on lastmod time
        if($context->modifiedSince()) $q->setWhere("modified_date >= ?", "datetime", $context->modifiedSince);
        if($context->notModifiedSince()) $q->setWhere("modified_date <= ?", "datetime", $context->notModifiedSince);
        // filter for un/read and un/starred status if specified
        if($context->unread()) $q->setWhere("unread is ?", "bool", $context->unread);
        if($context->starred()) $q->setWhere("starred is ?", "bool", $context->starred);
        // perform the query and return results
        return $this->db->prepare($q, "str", "str", "str")->run($this->dateFormatDefault, $this->dateFormatDefault, $this->dateFormatDefault);
    }

    public function articleMark(string $user, array $data, Context $context = null): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$context) $context = new Context;
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
                    owner is (select user from user) 
                    and article in (select id from target_articles where to_insert is 0 and (honour_read is 1 or honour_star is 1))",
            "INSERT INTO arsse_marks(owner,article,read,starred)
                select 
                    (select user from user),
                    id,
                    coalesce((select read from target_values) * honour_read,0),
                    coalesce((select starred from target_values),0)
                from target_articles where to_insert is 1 and (honour_read is 1 or honour_star is 1)"
        ];
        $out = 0;
        // wrap this UPDATE and INSERT together into a transaction
        $tr = $this->begin();
        // if an edition context is specified, make sure it's valid
        if($context->edition()) {
            // make sure the edition exists
            $edition = $this->articleValidateEdition($user, $context->edition);
            // if the edition is not the latest, do not mark the read flag
            if(!$edition['current']) $values[0] = null;
        } else if($context->article()) {
            // otherwise if an article context is specified, make sure it's valid
            $this->articleValidateId($user, $context->article);
        }
        // execute each query in sequence
        foreach($queries as $query) {
            // first build the query which will select the target articles; we will later turn this into a CTE for the actual query that manipulates the articles
            $q = new Query(
                "SELECT
                    arsse_articles.id as id,
                    (select max(id) from arsse_editions where article is arsse_articles.id) as edition,
                    max(arsse_articles.modified,
                        coalesce((select modified from arsse_marks join user on user is owner where article is arsse_articles.id),'')
                    ) as modified_date,
                    (not exists(select id from arsse_marks join user on user is owner where article is arsse_articles.id)) as to_insert,
                    ((select read from target_values) is not null and (select read from target_values) is not (coalesce((select read from arsse_marks join user on user is owner where article is arsse_articles.id),0)) and (not exists(select * from requested_articles) or (select max(id) from arsse_editions where article is arsse_articles.id) in (select edition from requested_articles))) as honour_read,
                    ((select starred from target_values) is not null and (select starred from target_values) is not (coalesce((select starred from arsse_marks join user on user is owner where article is arsse_articles.id),0))) as honour_star
                FROM arsse_articles"
            );
            // common table expression for the affected user
            $q->setCTE("user(user) as (SELECT ?)", "str", $user);
            // common table expression with the values to set
            $q->setCTE("target_values(read,starred) as (select ?,?)", ["bool","bool"], $values);
            if($context->edition()) {
                // if an edition is specified, filter for its previously identified article
                $q->setWhere("arsse_articles.id is ?", "int", $edition['article']);
            } else if($context->article()) {
                // if an article is specified, filter for it (it has already been validated above)
                $q->setWhere("arsse_articles.id is ?", "int", $context->article);
            } else if($context->subscription()) {
                // if a subscription is specified, make sure it exists
                $id = $this->subscriptionValidateId($user, $context->subscription)['feed'];
                // add a basic CTE that will join in only the requested subscription
                $q->setCTE("subscribed_feeds(id,sub) as (SELECT ?,?)", ["int","int"], [$id,$context->subscription], "join subscribed_feeds on feed is subscribed_feeds.id");
            } else if($context->folder()) {
                // if a folder is specified, make sure it exists
                $this->folderValidateId($user, $context->folder);
                // if it does exist, add a common table expression to list it and its children so that we select from the entire subtree
                $q->setCTE("folders(folder) as (SELECT ? union select id from arsse_folders join folders on parent is folder)", "int", $context->folder);
                // add another CTE for the subscriptions within the folder
                $q->setCTE("subscribed_feeds(id,sub) as (SELECT feed,id from arsse_subscriptions join user on user is owner join folders on arsse_subscriptions.folder is folders.folder)", [], [], "join subscribed_feeds on feed is subscribed_feeds.id");
            } else {
                // otherwise add a CTE for all the user's subscriptions
                $q->setCTE("subscribed_feeds(id,sub) as (SELECT feed,id from arsse_subscriptions join user on user is owner)", [], [], "join subscribed_feeds on feed is subscribed_feeds.id");
            }
            if($context->editions()) {
                // if multiple specific editions have been requested, prepare a CTE to list them and their articles
                if(!$context->editions) throw new Db\ExceptionInput("tooShort", ['field' => "editions", 'action' => __FUNCTION__, 'min' => 1]); // must have at least one array element
                if(sizeof($context->editions) > 50) throw new Db\ExceptionInput("tooLong", ['field' => "editions", 'action' => __FUNCTION__, 'max' => 50]); // must not have more than 50 array elements
                list($inParams, $inTypes) = $this->generateIn($context->editions, "int");
                $q->setCTE(
                    "requested_articles(id,edition) as (select article,id as edition from arsse_editions where edition in ($inParams))",
                    $inTypes,
                    $context->editions
                );
                $q->setWhere("arsse_articles.id in (select id from requested_articles)");
            } else if($context->articles()) {
                // if multiple specific articles have been requested, prepare a CTE to list them and their articles
                if(!$context->articles) throw new Db\ExceptionInput("tooShort", ['field' => "articles", 'action' => __FUNCTION__, 'min' => 1]); // must have at least one array element
                if(sizeof($context->articles) > 50) throw new Db\ExceptionInput("tooLong", ['field' => "articles", 'action' => __FUNCTION__, 'max' => 50]); // must not have more than 50 array elements
                list($inParams, $inTypes) = $this->generateIn($context->articles, "int");
                $q->setCTE(
                    "requested_articles(id,edition) as (select id,(select max(id) from arsse_editions where article is arsse_articles.id) as edition from arsse_articles where arsse_articles.id in ($inParams))",
                    $inTypes,
                    $context->articles
                );
                $q->setWhere("arsse_articles.id in (select id from requested_articles)");
            } else {
                // if neither list is specified, mock an empty table
                $q->setCTE("requested_articles(id,edition) as (select 'empty','table' where 1 is 0)");
            }
            // filter based on edition offset
            if($context->oldestEdition()) $q->setWhere("edition >= ?", "int", $context->oldestEdition);
            if($context->latestEdition()) $q->setWhere("edition <= ?", "int", $context->latestEdition);
            // filter based on lastmod time
            if($context->modifiedSince()) $q->setWhere("modified_date >= ?", "datetime", $context->modifiedSince);
            if($context->notModifiedSince()) $q->setWhere("modified_date <= ?", "datetime", $context->notModifiedSince);
            // push the current query onto the CTE stack and execute the query we're actually interested in
            $q->pushCTE(
                "target_articles(id,edition,modified_date,to_insert,honour_read,honour_star)", // CTE table specification
                [], // CTE types
                [], // CTE values
                $query // new query body
            );
            $out += $this->db->prepare($q)->run()->changes();
        }
        // commit the transaction
        $tr->commit();
        return (bool) $out;
    }

    public function articleValidateId(string $user, int $id): array {
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
        if(!$out) throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "article", 'id' => $id]);
        return $out;
    }

    protected function articleValidateEdition(string $user, int $id): array {
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
        if(!$out) throw new Db\ExceptionInput("subjectMissing", ["action" => $this->caller(), "field" => "edition", 'id' => $id]);
        return $out;
    }
}