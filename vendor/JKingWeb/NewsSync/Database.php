<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Database {
	const SCHEMA_VERSION = 1;
	const FORMAT_TS      = "Y-m-d h:i:s";
	const FORMAT_DATE    = "Y-m-d";
	const FORMAT_TIME    = "h:i:s";
	
	protected $data;
	protected $db;

	protected function cleanName(string $name): string {
		return (string) preg_filter("[^0-9a-zA-Z_\.]", "", $name);
	}

	public function __construct(RuntimeData $data) {
		$this->data = $data;
		$driver = $data->conf->dbClass;
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

	public function getSetting(string $key) {
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

	public function setSetting(string $key, $in, string $type = null): bool {
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

	public function clearSetting(string $key): bool {
		$this->db->prepare("DELETE from newssync_settings where key = ?", "str")->run($key);
		return true;
	}

	public function userAdd(string $username, string $password = null, bool $admin = false): string {
		$this->db->prepare("INSERT INTO newssync_users(id,password,admin) values(?,?,?)", "str", "str", "bool")->run($username,$password,$admin);
		return $username;
	}
	
	public function subscriptionAdd(string $user, string $url, string $fetchUser = null, string $fetchPassword = null): int {
		$this->db->begin();
		$qFeed = $this->db->prepare("SELECT id from newssync_feeds where url = ? and username = ? and password = ?", "str", "str", "str");
		if(is_null($id = $qFeed->run($url, $fetchUser, $fetchPassword)->getSingle())) {
			$this->db->prepare("INSERT INTO newssync_feeds(url,username,password) values(?,?,?)", "str", "str", "str")->run($url, $fetchUser, $fetchPassword);
			$id = $qFeed->run($url, $fetchUser, $fetchPassword)->getSingle();
		}
		$this->db->prepare("INSERT INTO newssync_subscriptions(owner,feed) values(?,?)", "str", "int")->run($user,$id);
		$this->db->commit();
		return 0;
	}

}