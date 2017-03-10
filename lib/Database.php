<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;
use PasswordGenerator\Generator as PassGen;
use PicoFeed\Reader\Reader;
use PicoFeed\PicoFeedException;

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

    public function __construct(RuntimeData $data) {
        $this->data = $data;
        $this->driver = $driver = $data->conf->dbDriver;
        $this->db = new $driver($data, INSTALL);
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

    public function settingGet(string $key) {
        $row = $this->db->prepare("SELECT value, type from newssync_settings where key = ?", "str")->run($key)->getRow();
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
        return (bool) $this->db->prepare("REPLACE INTO newssync_settings(key,value,type) values(?,?,?)", "str", "str", "str")->run($key, $value, $type)->changes();
    }

    public function settingRemove(string $key): bool {
        $this->db->prepare("DELETE from newssync_settings where key is ?", "str")->run($key);
        return true;
    }

    public function userExists(string $user): bool {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (bool) $this->db->prepare("SELECT count(*) from newssync_users where id is ?", "str")->run($user)->getValue();
    }

    public function userAdd(string $user, string $password = null): string {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if($this->userExists($user)) throw new User\Exception("alreadyExists", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        $hash = "";
        if(strlen($password) > 0) $hash = password_hash($password, \PASSWORD_DEFAULT);
        $this->db->prepare("INSERT INTO newssync_users(id,password) values(?,?)", "str", "str")->runArray([$user,$hash]);
        return $password;
    }

    public function userRemove(string $user): bool {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if($this->db->prepare("DELETE from newssync_users where id is ?", "str")->run($user)->changes() < 1) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return true;
    }

    public function userList(string $domain = null): array {
        if($domain !== null) {
            if(!$this->data->user->authorize("@".$domain, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $domain]);
            $domain = str_replace(["\\","%","_"],["\\\\", "\\%", "\\_"], $domain);
            $domain = "%@".$domain;
            return $this->db->prepare("SELECT id from newssync_users where id like ?", "str")->run($domain)->getAll();
        } else {
            if(!$this->data->user->authorize("", __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => "global"]);
            return $this->db->prepare("SELECT id from newssync_users")->run()->getAll();
        }
    }

    public function userPasswordGet(string $user): string {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        return (string) $this->db->prepare("SELECT password from newssync_users where id is ?", "str")->run($user)->getValue();
    }

    public function userPasswordSet(string $user, string $password = null): string {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) throw new User\Exception("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        if($password===null) $password = (new PassGen)->length($this->data->conf->userTempPasswordLength)->get();
        $hash = "";
        if(strlen($password > 0)) $hash = password_hash($password, \PASSWORD_DEFAULT);
        $this->db->prepare("UPDATE newssync_users set password = ? where id is ?", "str", "str")->run($hash, $user);
        return $password;
    }

    public function userPropertiesGet(string $user): array {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $prop = $this->db->prepare("SELECT name,rights from newssync_users where id is ?", "str")->run($user)->getRow();
        if(!$prop) return [];
        return $prop;
    }

    public function userPropertiesSet(string $user, array &$properties): array {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        $valid = [ // FIXME: add future properties
            "name" => "str",
        ];
        if(!$this->userExists($user)) return [];
        $this->db->begin();
        foreach($valid as $prop => $type) {
            if(!array_key_exists($prop, $properties)) continue;
            $this->db->prepare("UPDATE newssync_users set $prop = ? where id is ?", $type, "str")->run($properties[$prop], $user);
        }
        $this->db->commit();
        return $this->userPropertiesGet($user);
    }

    public function userRightsGet(string $user): int {
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (int) $this->db->prepare("SELECT rights from newssync_users where id is ?", "str")->run($user)->getValue();
    }

    public function userRightsSet(string $user, int $rights): bool {
        if(!$this->data->user->authorize($user, __FUNCTION__, $rights)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        if(!$this->userExists($user)) return false;
        $this->db->prepare("UPDATE newssync_users set rights = ? where id is ?", "int", "str")->run($rights, $user);
        return true;
    }

    public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = ""): int {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!$this->data->user->authorize($user, __FUNCTION__)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        }
        // If the user doesn't exist throw an exception.
        if (!$this->userExists($user)) {
            throw new User\Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
        }

        $this->db->begin();

        // If the feed doesn't already exist in the database then add it to the database after determining its validity with PicoFeed.
        $qFeed = $this->db->prepare("SELECT id from newssync_feeds where url is ? and username is ? and password is ?", "str", "str", "str");
        $feed = $qFeed->run($url, $fetchUser, $fetchPassword)->getValue();
        if ($feed === null) {
            try {
                $reader = new Reader;
                $resource = $reader->download($url);

                $parser = $reader->getParser(
                    $resource->getUrl(),
                    $resource->getContent(),
                    $resource->getEncoding()
                );

                $feed = $parser->execute();
            } catch (PicoFeedException $e) {
                // If there's any error while trying to download or parse the feed then return an exception.
                throw new Feed\Exception($url, $e);
            }

            $feedID = $this->db->prepare(
                "INSERT INTO newssync_feeds(url,title,favicon,source,updated,modified,etag,username,password) values(?,?,?,?,?,?,?,?,?)", 
                "str", "str", "str", "str", "datetime", "datetime", "str", "str", "str"
            )->run(
                $url,
                $feed->title,
                // Grab the favicon for the Goodfeed; returns an empty string if it cannot find one.
                (new \PicoFeed\Reader\Favicon)->find($url),
                $feed->siteUrl,
                $feed->date,
                $resource->getLastModified(),
                $resource->getEtag(),
                $fetchUser,
                $fetchPassword
            )->lastId();

            // TODO: Populate newssync_articles with contents of what was obtained from PicoFeed.
        }

        // Add the feed to the user's subscriptions.
        $sub = $this->db->prepare("INSERT INTO newssync_subscriptions(owner,feed) values(?,?)", "str", "int")->run($user, $feedID)->lastId();
        $this->db->commit();
        return $sub;
    }

    public function subscriptionRemove(int $id): bool {
        $this->db->begin();
        $user = $this->db->prepare("SELECT owner from newssync_subscriptions where id is ?", "int")->run($id)->getValue();
        if($user===null) return false;
        if(!$this->data->user->authorize($user, __FUNCTION__)) throw new User\ExceptionAuthz("notAuthorized", ["action" => __FUNCTION__, "user" => $user]);
        return (bool) $this->db->prepare("DELETE from newssync_subscriptions where id is ?", "int")->run($id)->changes();
    }

    public function folderAdd(string $user, array $data): int {
        // If the user isn't authorized to perform this action then throw an exception.
        if (!$this->data->user->authorize($user, __FUNCTION__)) {
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
        } else if(iconv_strlen($data['name']) > 100) {
            throw new Db\ExceptionInput("tooLong", ["action" => __FUNCTION__, "field" => "name", 'max' => 100]);
        }
        // normalize folder's parent, if there is one
        $parent = array_key_exists("parent", $data) ? (int) $data['parent'] : 0;
        if($parent===0) {
            // if no parent is specified, do nothing
            $parent = null;
            $root = null;
        } else {
            // if a parent is specified, make sure it exists and belongs to the user; get its root (first-level) folder if it's a nested folder
            $p = $this->db->prepare("SELECT id,root from newssync_folders where owner is ? and id is ?", "str", "int")->run($user, $parent)->getRow();
            if(!$p) {
                throw new Db\ExceptionInput("idMissing", ["action" => __FUNCTION__, "field" => "parent", 'id' => $parent]);
            } else {
                // if the parent does not have a root specified (because it is a first-level folder) use the parent ID as the root ID
                $root = $p['root']===null ? $parent : $p['root'];
            }
        }
        // check if a folder by the same name already exists, because nulls are wonky in SQL
        // FIXME: How should folder name be compared? Should a Unicode normalization be applied before comparison and insertion?
        if($this->db->prepare("SELECT count(*) from newssync_folders where owner is ? and parent is ? and name is ?", "str", "int", "str")->run($user, $parent, $data['name'])->getValue() > 0) {
            throw new Db\ExceptionInput("constraintViolation"); // FIXME: There needs to be a practical message here
        }
        // actually perform the insert (!)
        return $this->db->prepare("INSERT INTO newssync_folders(owner,parent,root,name) values(?,?,?,?)", "str", "int", "int", "str")->run($user, $parent, $root, $data['name'])->lastId();
    }
}