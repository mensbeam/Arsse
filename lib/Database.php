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

    protected function cleanName(string $name): string {
        return (string) preg_filter("[^0-9a-zA-Z_\.]", "", $name);
    }

    public function __construct(Db\Driver $db = null) {
        // if we're fed a pre-prepared driver, use it'
        if($db) {
            $this->db = $db;
        } else {
            $this->driver = $driver = Data::$conf->dbDriver;
            $this->db = new $driver(INSTALL);
            $ver = $this->db->schemaVersion();
            if(!INSTALL && $ver < self::SCHEMA_VERSION) {
                $this->db->schemaUpdate(self::SCHEMA_VERSION);
            }
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
        $valid = [ // FIXME: add future properties
            "name" => "str",
        ];
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        $this->db->begin();
        foreach($valid as $prop => $type) {
            if(!array_key_exists($prop, $properties)) continue;
            $this->db->prepare("UPDATE arsse_users set $prop = ? where id is ?", $type, "str")->run($properties[$prop], $user);
        }
        $this->db->commit();
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

    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = ""): int {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Data::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // If the user doesn't exist throw an exception.
        if (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        }

        $this->db->begin();

        // If the feed doesn't already exist in the database then add it to the database
        // after determining its validity with PicoFeed.
        $qFeed = $this->db->prepare("SELECT id from arsse_feeds where url is ? and username is ? and password is ?", "str", "str", "str");
        $feed = $qFeed->run($url, $fetchUser, $fetchPassword)->getValue();
        if ($feed === null) {
            $feed = new Feed($url);
            $feed->parse();

            // Add the feed to the database and return its Id which will be used when adding
            // its articles to the database.
            $feedID = $this->db->prepare(
                'INSERT INTO arsse_feeds(url,title,favicon,source,updated,modified,etag,username,password)
                values(?,?,?,?,?,?,?,?,?)',
                'str', 'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str')->run(
                    $url,
                    $feed->data->title,
                    // Grab the favicon for the feed; returns an empty string if it cannot find one.
                    $feed->favicon,
                    $feed->data->siteUrl,
                    $feed->data->date,
                    $feed->resource->getLastModified(),
                    $feed->resource->getEtag(),
                    $fetchUser,
                    $fetchPassword
                )->lastId();

            // Add each of the articles to the database.
            foreach ($feed->data->items as $i) {
                $this->articleAdd($i);
            }
        }

        // Add the feed to the user's subscriptions.
        $sub = $this->db->prepare('INSERT INTO arsse_subscriptions(owner,feed) values(?,?)', 'str', 'int')->run($user, $feedID)->lastId();
        $this->db->commit();
        return $sub;
    }

    public function subscriptionRemove(string $user, int $id): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (bool) $this->db->prepare("DELETE from arsse_subscriptions where id is ?", "int")->run($id)->changes();
    }

    public function folderAdd(string $user, array $data): int {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!Data::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // If the user doesn't exist throw an exception.
        if (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        }
        // if the desired folder name is missing or invalid, throw an exception
        if(!array_key_exists("name", $data)) {
            throw new Db\ExceptionInput("missing", ["action" => __FUNCTION__, "field" => "name"]);
        } else if(!strlen(trim($data['name']))) {
            throw new Db\ExceptionInput("whitespace", ["action" => __FUNCTION__, "field" => "name"]);
        }
        // normalize folder's parent, if there is one
        $parent = array_key_exists("parent", $data) ? (int) $data['parent'] : 0;
        if($parent===0) {
            // if no parent is specified, do nothing
            $parent = null;
            $root = null;
        } else {
            // if a parent is specified, make sure it exists and belongs to the user; get its root (first-level) folder if it's a nested folder
            $p = $this->db->prepare("SELECT id,root from arsse_folders where owner is ? and id is ?", "str", "int")->run($user, $parent)->getRow();
            if(!$p) {
                throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "parent", 'id' => $parent]);
            } else {
                // if the parent does not have a root specified (because it is a first-level folder) use the parent ID as the root ID
                $root = $p['root']===null ? $parent : $p['root'];
            }
        }
        // check if a folder by the same name already exists, because nulls are wonky in SQL
        // FIXME: How should folder name be compared? Should a Unicode normalization be applied before comparison and insertion?
        if($this->db->prepare("SELECT count(*) from arsse_folders where owner is ? and parent is ? and name is ?", "str", "int", "str")->run($user, $parent, $data['name'])->getValue() > 0) {
            throw new Db\ExceptionInput("constraintViolation"); // FIXME: There needs to be a practical message here
        }
        // actually perform the insert (!)
        return $this->db->prepare("INSERT INTO arsse_folders(owner,parent,root,name) values(?,?,?,?)", "str", "int", "int", "str")->run($user, $parent, $root, $data['name'])->lastId();
    }

    public function folderList(string $user, int $parent = null, bool $recursive = true): Db\Result {
        // if the user isn't authorized to perform this action then throw an exception.
        if (!Data::$user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // if the user doesn't exist throw an exception.
        if (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        }
        // if we're not returning a recursive list we can use a simpler query
        if(!$recursive) {
            return $this->db->preparre("SELECT id,name,parent from arsse_folders where owner is ? and parent is ?", "str", "int")->run($user, $parent);
        } else {
            return $this->db->prepare(
                "WITH RECURSIVE folders(id) as (SELECT id from arsse_folders where owner is ? and parent is ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id) ".
                "SELECT id,name,parent from arsse_folders where id in(SELECT id from folders) order by name",
            "str", "int")->run($user, $parent);
        }
    }

    public function articleAdd(PicoFeed\Parser\Item $article): int {
        $this->db->begin();

        $articleId = $this->db->prepare('INSERT INTO arsse_articles(feed,url,title,author,published,edited,guid,content,url_title_hash,url_content_hash,title_content_hash)
        values(?,?,?,?,?,?,?,?,?,?,?)',
        'int', 'str', 'str', 'str', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str')->run(
            $feedID,
            $article->url,
            $article->title,
            $article->author,
            $article->publishedDate,
            $article->updatedDate,
            $article->id,
            $article->content,
            $article->urlTitleHash,
            $article->urlContentHash,
            $article->titleContentHash
        )->lastId();

        // If the article has categories add them into the categories database.
        $categories = $article->getTag('category');
        if (count($categories) > 0) {
            foreach ($categories as $c) {
                $this->db->prepare('INSERT INTO arsse_tags(article,name) values(?,?)', 'int', 'str')->run($articleId, $c);
            }
        }

        $this->db->commit();
        return 1;
    }

    public function updateFeeds(): int {
        $feeds = $this->db->query('SELECT id, url, username, password, DATEFORMAT("http", modified) AS lastmodified, etag FROM arsse_feeds')->getAll();
        foreach ($feeds as $f) {
            $feed = new Feed($f['url'], $f['lastmodified'], $f['etag'], $f['username'], $f['password']);
            // FIXME: What to do if fails? It currently throws an exception which isn't ideal here.

            // If the feed has been updated then
            if ($feed->resource->isModified()) {
                $feed->parse();

                $this->db->begin();
                $articles = $this->db->prepare('SELECT id, url, title, author, DATEFORMAT("http", edited) AS edited_date, guid, content, url_title_hash, url_content_hash, title_content_hash FROM arsse_articles WHERE feed is ? ORDER BY id', 'int')->run($f['id'])->getAll();

                foreach ($feed->data->items as $i) {
                    // Iterate through the articles in the database to determine a match for the one
                    // in the just-parsed feed.
                    $match = null;
                    foreach ($articles as $a) {
                        // If the id exists and is equal to one in the database then this is the post.
                        if ($i->id) {
                            if ($i->id === $a['guid']) {
                                $match = $a;
                            }
                        }

                        // Otherwise if the id doesn't exist and any of the hashes match then this is
                        // the post.
                        elseif ($i->urlTitleHash === $a['url_title_hash'] || $i->urlContentHash === $a['url_content_hash'] || $i->titleContentHash === $a['title_content_hash']) {
                            $match = $a;
                        }
                    }

                    // If there is no match then this is a new post and must be added to the
                    // database.
                    if (!$match) {
                        $this->articleAdd($i);
                        continue;
                    }

                    // With that out of the way determine if the post has been updated.
                    // If there is an updated date, and it doesn't match the database's then update
                    // the post.
                    $update = false;
                    if ($i->updatedDate) {
                        if ($i->updatedDate !== $match['edited_date']) {
                            $update = true;
                        }
                    }
                    // Otherwise if there isn't an updated date and any of the hashes don't match
                    // then update the post.
                    elseif ($i->urlTitleHash !== $match['url_title_hash'] || $i->urlContentHash !== $match['url_content_hash'] || $i->titleContentHash !== $match['title_content_hash']) {
                        $update = true;
                    }

                    if ($update) {
                        $this->db->prepare('UPDATE arsse_articles SET url = ?, title = ?, author = ?, published = ?, edited = ?, modified = ?, guid = ?, content = ?, url_title_hash = ?, url_content_hash = ?, title_content_hash = ? WHERE id is ?', 'str', 'str', 'str', 'datetime', 'datetime', 'datetime', 'str', 'str', 'str', 'str', 'str', 'int')->run(
                            $i->url,
                            $i->title,
                            $i->author,
                            $i->publishedDate,
                            $i->updatedDate,
                            time(),
                            $i->id,
                            $i->content,
                            $i->urlTitleHash,
                            $i->urlContentHash,
                            $i->titleContentHash,
                            $match['id']
                        );

                        // TODO: Update categories
                    }
                }

                // Lastly update the feed database itself with updated information.
                $this->db->prepare('UPDATE arsse_feeds SET url = ?, title = ?, favicon = ?, source = ?, updated = ?, modified = ?, etag = ? WHERE id is ?', 'str', 'str', 'str', 'str', 'datetime', 'datetime', 'str', 'int')->run(
                    $feed->feedUrl,
                    $feed->title,
                    $feed->favicon,
                    $feed->siteUrl,
                    $feed->date,
                    $feed->resource->getLastModified(),
                    $feed->resource->getEtag(),
                    $f['id']
                );
            }
        }

        $this->db->commit();
        return 1;
    }

    public function folderRemove(string $user, int $id): bool {
        if(!Data::$user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        // common table expression to list all descendant folders of the target folder
        $cte = "RECURSIVE folders(id) as (SELECT id from arsse_folders where owner is ? and id is ? union select arsse_folders.id from arsse_folders join folders on arsse_folders.parent=folders.id) ";
        $changes = 0;
        $this->db->begin();
        // first delete any feed subscriptions contained within the folder tree (this may not be necesary because of foreign keys)
        $changes += $this->db->prepare("WITH $cte"."DELETE FROM arsse_subscriptions where folder in(select id from folders)", "str", "int")->run($user, $id)->changes();
        // next delete the folders themselves
        $changes += $this->db->prepare("WITH $cte"."DELETE FROM arsse_folders where id in(select id from folders)", "str", "int")->run($user, $id)->changes();
        $this->db->commit();
        return (bool) $changes;
    }
}