<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Database {
	const SCHEMA_VERSION = 1;
	
	protected $db;

	protected function clean_name(string $name): string {
		return (string) preg_filter("[^0-9a-zA-Z_\.]", "", $name);
	}

	public function __construct(Conf $conf) {
		$driver = $conf->dbClass;
		$this->db = $driver::create($conf, INSTALL);
		$ver = $this->db->schemaVersion();
		if($ver < self::SCHEMA_VERSION) {
			if($conf->dbSQLite3AutoUpd) {
				$this->db->update(self::SCHEMA_VERSION);
			} else {
				throw new Db\Exception("updateManual", ['from_version' => $ver, 'to_version' => self::SCHEMA_VERSION, 'driver_name' => $this->db->driverName()]);
			}
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