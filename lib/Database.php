<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use PasswordGenerator\Generator as PassGen;

class Database {

    const SCHEMA_VERSION = 1;
    const FORMAT_TS      = "Y-m-d h:i:s";
    const FORMAT_DATE    = "Y-m-d";
    const FORMAT_TIME    = "h:i:s";

    protected $data;
    public    $db;
    private   $driver;

    public function __construct() {
        $this->driver = $driver = Data::$conf->dbDriver;
        $this->db = new $driver(INSTALL);
        $ver = $this->db->schemaVersion();
        if(!INSTALL && $ver < self::SCHEMA_VERSION) {
            $this->db->schemaUpdate(self::SCHEMA_VERSION);
        }
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

    public function settingGet(string $key) {
        $row = $this->db->prepare("SELECT value, type from arsse_settings where key = ?", "str")->run($key)->getRow();
        if(!$row) return null;
        switch($row['type']) {
            case "int":       return (int) $row['value'];
            case "numeric":   return (float) $row['value'];
            case "text":      return $row['value'];
            case "json":      return json_decode($row['value']);
            case "timestamp": return date_create_from_format("!".self::FORMAT_TS, $row['value'], new DateTimeZone("UTC"));
            case "date":      return date_create_from_format("!".self::FORMAT_DATE, $row['value'], new DateTimeZone("UTC"));
            case "time":      return date_create_from_format("!".self::FORMAT_TIME, $row['value'], new DateTimeZone("UTC"));
            case "bool":      return (bool) $row['value'];
            case "null":      return null;
            default:          return $row['value'];
        }
    }

    public function settingSet(string $key, $in, string $type = null): bool {
        if(!$type) {
            switch(gettype($in)) {
                case "boolean":         $type = "bool"; break;
                case "integer":         $type = "int"; break;
                case "double":          $type = "numeric"; break;
                case "string":
                case "array":           $type = "json"; break;
                case "resource":
                case "unknown type":
                case "NULL":            $type = "null"; break;
                case "object":
                    if($in instanceof DateTimeInterface) {
                        $type = "timestamp";
                    } else {
                        $type = "text";
                    }
                    break;
                default:                $type = 'null'; break;
            }
        }
        $type = strtolower($type);
        switch($type) {
            case "integer":
                $type = "int";
            case "int":
                $value = $in;
                break;
            case "float":
            case "double":
            case "real":
                $type = "numeric";
            case "numeric":
                $value = $in;
                break;
            case "str":
            case "string":
                $type = "text";
            case "text":
                $value = $in;
                break;
            case "json":
                if(is_array($in) || is_object($in)) {
                    $value = json_encode($in);
                } else {
                    $value = $in;
                }
                break;
            case "datetime":
                $type = "timestamp";
            case "timestamp":
                if($in instanceof DateTimeInterface) {
                    $value = gmdate(self::FORMAT_TS, $in->format("U"));
                } else if(is_numeric($in)) {
                    $value = gmdate(self::FORMAT_TS, $in);
                } else {
                    $value = gmdate(self::FORMAT_TS, gmstrftime($in));
                }
                break;
            case "date":
                if($in instanceof DateTimeInterface) {
                    $value = gmdate(self::FORMAT_DATE, $in->format("U"));
                } else if(is_numeric($in)) {
                    $value = gmdate(self::FORMAT_DATE, $in);
                } else {
                    $value = gmdate(self::FORMAT_DATE, gmstrftime($in));
                }
                break;
            case "time":
                if($in instanceof DateTimeInterface) {
                    $value = gmdate(self::FORMAT_TIME, $in->format("U"));
                } else if(is_numeric($in)) {
                    $value = gmdate(self::FORMAT_TIME, $in);
                } else {
                    $value = gmdate(self::FORMAT_TIME, gmstrftime($in));
                }
                break;
            case "boolean":
            case "bit":
                $type = "bool";
            case "bool":
                $value = (int) $in;
                break;
            case "null":
                $value = null;
                break;
            default:
                $type = "text";
                $value = $in;
                break;
        }
        return (bool) $this->db->prepare("REPLACE INTO arsse_settings(key,value,type) values(?,?,?)", "str", "str", "str")->run($key, $value, $type)->changes();
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
        // If the user doesn't exist throw an exception.
        if(!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
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
        // if the user doesn't exist throw an exception.
        if(!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
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
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        $changes = $this->db->prepare("DELETE FROM arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->changes();
        if(!$changes) throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        return true;
    }

    public function folderPropertiesGet(string $user, int $id): array {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        $props = $this->db->prepare("SELECT id,name,parent from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $id)->getRow();
        if(!$props) throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "folder", 'id' => $id]);
        return $props;
    }

    public function folderPropertiesSet(string $user, int $id, array $data): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        // layer the existing folder properties onto the new desired one
        $data = array_merge($this->folderPropertiesGet($user, $id), $data);
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
            // if a parent is specified, make sure it exists and belongs to the user
            $p = $this->db->prepare(
                "WITH RECURSIVE folders(id) as (SELECT id from arsse_folders where owner is ? and id is ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id) ".
                "SELECT id,(id not in (select id from folders)) as valid from arsse_folders where owner is ? and id is ?",
            "str", "int", "str", "int")->run($user, $id, $user, $parent)->getRow();
            if(!$p) {
                throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "parent", 'id' => $parent]);
            } else {
                // if using the desired parent would create a circular dependence, throw an exception
                if(!$p['valid']) throw new Db\ExceptionInput("circularDependence", ["action" => __FUNCTION__, "field" => "parent", 'id' => $parent]);
            }
        }
        $data['parent'] = $parent;
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
        $this->db->prepare("UPDATE arsse_folders set $setClause where owner is ? and id is ?", $setTypes, "str", "int")->run($setValues, $user, $id);
        return true;
    }

    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = ""): int {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        // If the feed doesn't already exist in the database then add it to the database
        // after determining its validity with PicoFeed.
        $feedID = $this->db->prepare("SELECT id from arsse_feeds where url is ? and username is ? and password is ?", "str", "str", "str")->run($url, $fetchUser, $fetchPassword)->getValue();
        if($feedID === null) {
            $feedID = $this->feedAdd($url, $fetchUser, $fetchPassword);
        }
        // Add the feed to the user's subscriptions.
        return $this->db->prepare('INSERT INTO arsse_subscriptions(owner,feed) values(?,?)', 'str', 'int')->run($user, $feedID)->lastId();
    }

    public function subscriptionRemove(string $user, int $id): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (bool) $this->db->prepare("DELETE from arsse_subscriptions where owner is ? and id is ?", "str", "int")->run($user, $id)->changes();
    }

    public function feedAdd(string $url, string $fetchUser = "", string $fetchPassword = ""): int {
        $feedID = $this->db->prepare('INSERT INTO arsse_feeds(url,username,password) values(?,?,?)', 'str', 'str', 'str')->run($url, $fetchUser, $fetchPassword)->lastId();
        // Add the feed to the database and return its Id which will be used when adding
        // its articles to the database.
        try {
            $this->feedUpdate($feedID, true);
        } catch(\Throwable $e) {
            $this->db->prepare('DELETE from arsse_feeds where id is ?', 'int')->run($feedID);
            throw $e;
        }
        return $feedID;
    }

    public function feedUpdate(int $feedID, bool $throwError = false): bool {
        $this->db->begin();
        try {
            // check to make sure the feed exists
            $f = $this->db->prepare('SELECT url, username, password, DATEFORMAT("http", modified) AS lastmodified, etag, err_count FROM arsse_feeds where id is ?', "int")->run($feedID)->getRow();
            if(!$f) throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "feed", 'id' => $feedID]);
            // the Feed object throws an exception when there are problems, but that isn't ideal
            // here. When an exception is thrown it should update the database with the
            // error instead of failing; if other exceptions are thrown, we should simply roll back
            try {
                $feed = new Feed($feedID, $f['url'], (string)$f['lastmodified'], $f['etag'], $f['username'], $f['password']);
                if(!$feed->modified) {
                    // if the feed hasn't changed, just compute the next fetch time and record it
                    $this->db->prepare('UPDATE arsse_feeds SET updated = CURRENT_TIMESTAMP, next_fetch = ? WHERE id is ?', 'datetime', 'int')->run($feed->nextFetch, $feedID);
                    $this->db->commit();
                    return false;
                }
            } catch (Feed\Exception $e) {
                // update the database with the resultant error and the next fetch time, incrementing the error count
                $this->db->prepare(
                    'UPDATE arsse_feeds SET updated = CURRENT_TIMESTAMP, next_fetch = ?, err_count = err_count + 1, err_msg = ? WHERE id is ?', 
                    'datetime', 'str', 'int'
                )->run(Feed::nextFetchOnError($f['err_count']), $e->getMessage(),$feedID);
                $this->db->commit();
                if($throwError) throw $e;
                return false;
            } catch(\Throwable $e) {
                $this->db->rollback();
                throw $e;
            }
            //prepare the necessary statements to perform the update
            if(sizeof($feed->newItems) || sizeof($feed->changedItems)) {
                $qInsertCategory = $this->db->prepare('INSERT INTO arsse_categories(article,name) values(?,?)', 'int', 'str');
                $qInsertEdition = $this->db->prepare('INSERT INTO arse_editions(article) values(?)', 'int');
            }
            if(sizeof($feed->newItems)) {
                $qInsertArticle = $this->db->prepare(
                    'INSERT INTO arsse_articles(url,title,author,published,edited,guid,content,url_title_hash,url_content_hash,title_content_hash,feed) values(?,?,?,?,?,?,?,?,?,?,?)',
                    'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str', 'int'
                );
            }
            if(sizeof($feed->changedItems)) {
                $qDeleteCategories = $this->db->prepare('DELETE FROM arsse_categories WHERE article is ?', 'int');
                $qClearReadMarks = $this->db->prepare('UPDATE arsse_subscription_articles SET read = 0, modified = CURRENT_TIMESTAMP WHERE article is ?', 'int');
                $qUpdateArticle = $this->db->prepare(
                    'UPDATE arsse_articles SET url = ?, title = ?, author = ?, published = ?, edited = ?, modified = CURRENT_TIMESTAMP, guid = ?, content = ?, url_title_hash = ?, url_content_hash = ?, title_content_hash = ? WHERE id is ?', 
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
                foreach($article->getTag('category') as $c) {
                    $qInsertCategories->run($articleID, $c);
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
                $qDeleteCategories->run($articleID);
                foreach($article->getTag('category') as $c) {
                    $qInsertCategories->run($articleID, $c);
                }
                $qInsertEdition->run($articleID);
                $qClearReadMarks->run($articleID);
            }
            // lastly update the feed database itself with updated information.
            $this->db->prepare(
                'UPDATE arsse_feeds SET url = ?, title = ?, favicon = ?, source = ?, updated = CURRENT_TIMESTAMP, modified = ?, etag = ?, err_count = 0, err_msg = "", next_fetch = ? WHERE id is ?', 
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
        } catch(\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
        $this->db->commit();
        return true;
    }

    public function articleMatchLatest(int $feedID, int $count): Db\Result {
        return $this->db->prepare(
            'SELECT id, DATEFORMAT("unix", edited) AS edited_date, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed is ? ORDER BY edited desc limit ?', 
            'int', 'int'
        )->run($feedID, $count);
    }

    public function articleMatchIds(int $feedID, array $ids = [], array $hashesUT = [], array $hashesUC = [], array $hashesTC = []): Db\Result {
        // compile SQL IN() clauses and necessary type bindings for the four identifier lists
        list($cId,     $tId)     = $this->generateIn($ids, "str");
        list($cHashUT, $tHashUT) = $this->generateIn($hashesUT, "str");
        list($cHashUC, $tHashUC) = $this->generateIn($hashesUC, "str");
        list($cHashTC, $tHashTC) = $this->generateIn($hashesTC, "str");
        // perform the query
        return $articles = $this->db->prepare(
            'SELECT id, DATEFORMAT("unix", edited) AS edited_date, guid, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles '.
            'WHERE feed is ? and (guid in($cId) or url_title_hash in($cHashUT) or url_content_hash in($cHashUC) or title_content_hash in($cHashTC)', 
            'int', $tId, $tHashUT, $tHashUC, $tHashTC
        )->run($feedID, $ids, $hashesUT, $hashesUC, $hashesTC);
    }
}