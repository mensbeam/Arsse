<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Database {
	const SCHEMA_VERSION = 1;
	const FORMAT_TS      = "Y-m-d h:i:s";
	const FORMAT_DATE    = "Y-m-d";
	const FORMAT_TIME    = "h:i:s";
	
	protected $data;
	public    $db;

	protected function cleanName(string $name): string {
		return (string) preg_filter("[^0-9a-zA-Z_\.]", "", $name);
	}

	public function __construct(RuntimeData $data) {
		$this->data = $data;
		$driver = $data->conf->dbDriver;
		$this->db = $driver::create($data, INSTALL);
		$ver = $this->db->schemaVersion();
		if(!INSTALL && $ver < self::SCHEMA_VERSION) {
			$this->db->update(self::SCHEMA_VERSION);
		}
	}

	static public function listDrivers(): array {
		$sep = \DIRECTORY_SEPARATOR;
		$path = __DIR__.$sep."Db".$sep;
		$classes = [];
		foreach(glob($path."Driver?*.php") as $file) {
			$name = basename($file, ".php");
			if(substr($name,-3) != "PDO") {
				$name = NS_BASE."Db\\$name";
				if(class_exists($name)) {
					$classes[$name] = $name::driverName();
				}
			}			 
		}
		return $classes;
	}

	public function schemaVersion(): int {
		return $this->db->schemaVersion();
	}

	public function dbUpdate(): bool {
		if($this->db->schemaVersion() < self::SCHEMA_VERSION) return $this->db->update(self::SCHEMA_VERSION);
		return false;
	}

	public function settingGet(string $key) {
		$row = $this->db->prepare("SELECT value, type from newssync_settings where key = ?", "str")->run($key)->get();
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
				case "boolean": 		$type = "bool"; break;
				case "integer": 		$type = "int"; break;
				case "double": 			$type = "numeric"; break;
				case "string":
				case "array": 			$type = "json"; break;
				case "resource":
				case "unknown type":
				case "NULL": 			$type = "null"; break;
				case "object":
					if($in instanceof DateTimeInterface) {
						$type = "timestamp";
					} else {
						$type = "text";
					}
					break;
				default: 				$type = 'null'; break;
			}
		}
		$type = strtolower($type);
		switch($type) {
			case "integer":
				$type = "int";
			case "int":
				$value =& $in;
				break;
			case "float":
			case "double":
			case "real":
				$type = "numeric";
			case "numeric":
				$value =& $in;
				break;
			case "str":
			case "string":
				$type = "text";
			case "text":
				$value =& $in;
				break;
			case "json":
				if(is_array($in) || is_object($in)) {
					$value = json_encode($in);
				} else {
					$value =& $in;
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
				$value =& $in;
				break;
		}
		$this->db->prepare("REPLACE INTO newssync_settings(key,value,type) values(?,?,?)", "str", (($type=="null") ? "null" : "str"), "str")->run($key, $value, "text");
	}

	public function settingRemove(string $key): bool {
		$this->db->prepare("DELETE from newssync_settings where key = ?", "str")->run($key);
		return true;
	}

	public function userExists(string $username): bool {
		return (bool) $this->db->prepare("SELECT count(*) from newssync_users where id is ?", "str")->run($username)->getSingle();
	}

	public function userAdd(string $username, string $password = null): bool {
		if(strlen($password) > 0) $password = password_hash($password, \PASSWORD_DEFAULT);
		if($this->db->prepare("SELECT count(*) from newssync_users")->run()->getSingle() < 1) { //if there are no users, the first user should be made a global admin
			$admin = "global";
		} else {
			$admin = null;
		}
		$this->db->prepare("INSERT INTO newssync_users(id,password,admin) values(?,?,?)", "str", "str", "str")->run($username,$password,$admin);
		return true;
	}

	public function userRemove(string $username): bool {
		$this->db->prepare("DELETE from newssync_users where id is ?", "str")->run($username);
		return true;
	}

	public function userList(string $domain = null): array {
		if($domain !== null) {
			$domain = str_replace(["\\","%","_"],["\\\\", "\\%", "\\_"], $domain);
			$domain = "%@".$domain;
			$set = $this->db->prepare("SELECT id from newssync_users where id like ?", "str")->run($domain);
		} else {
			$set = $this->db->query("SELECT id from newssync_users");
		}
		$out = [];
		foreach($set as $row) {
			$out[] = $row["id"];
		}
		return $out;
	}
	
	public function userPasswordSet($username, $password): bool {
		if(!$this->userExists($username)) return false;
		if(strlen($password > 0)) $password = password_hash($password);
		$this->db->prepare("UPDATE newssync_users set password = ? where id is ?", "str", "str")->run($password, $username);
		return true;
	}

	public function userPropertiesGet(string $username): array {
		$prop = $this->db->prepare("SELECT name,admin from newssync_users where id is ?", "str")->run($username)->get();
		if(!$prop) return [];
		return $prop;
	}

	public function userPropertiesSet(string $username, array &$properties): array {
		$valid = [ // FIXME: add future properties
			"name" => "str", 
			"admin" => "str",
		];
		$this->db->begin();
		foreach($valid as $prop => $type) {
			if(!array_key_exists($prop, $properties)) continue;
			$this->db->prepare("UPDATE newssync_users set $prop = ? where id is ?", $type, "str")->run($properties[$prop], $username); 
		}
		$this->db->commit();
		return $this->userPropertiesGet($username);
	}

	public function subscriptionAdd(string $user, string $url, string $fetchUser = "", string $fetchPassword = ""): int {
		$this->db->begin();
		$qFeed = $this->db->prepare("SELECT id from newssync_feeds where url is ? and username is ? and password is ?", "str", "str", "str");
		$id = $qFeed->run($url, $fetchUser, $fetchPassword)->getSingle();
		if($id===null) {
			$this->db->prepare("INSERT INTO newssync_feeds(url,username,password) values(?,?,?)", "str", "str", "str")->run($url, $fetchUser, $fetchPassword);
			$id = $qFeed->run($url, $fetchUser, $fetchPassword)->getSingle();
			var_export($id);
		}
		$this->db->prepare("INSERT INTO newssync_subscriptions(owner,feed) values(?,?)", "str", "int")->run($user,$id);
		$this->db->commit();
		return 0;
	}

}