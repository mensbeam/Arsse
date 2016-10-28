<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class User {
	public  $id = null;

	protected $data;
	protected $u;
	protected $logged = [];
	
	static public function listDrivers(): array {
		$sep = \DIRECTORY_SEPARATOR;
		$path = __DIR__.$sep."User".$sep;
		$classes = [];
		foreach(glob($path."Driver?*.php") as $file) {
			$name = basename($file, ".php");
			$name = NS_BASE."Db\\$name";
			if(class_exists($name)) {
				$classes[$name] = $name::driverName();
			}			 
		}
		return $classes;
	}

	public function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
		$this->data = $data;
		$driver = $data->conf->userDriver;
		$this->u = $driver::create($data);
	}

	public function __toString() {
		if($this->id===null) $this->credentials();
		return (string) $this->id;
	}

	public function credentials(): array {
		if($this->data->conf->userAuthPreferHTTP) {
			return $this->credentialsHTTP();
		} else {
			return $this->credentialsForm();
		}
	}

	public function credentialsForm(): array {
		// FIXME: stub
		$this->id = "john.doe@example.com";
		return ["user" => "john.doe@example.com", "password" => "secret"];
	}

	public function credentialsHTTP(): array {
		if($_SERVER['PHP_AUTH_USER']) {
			$out = ["user" => $_SERVER['PHP_AUTH_USER'], "password" => $_SERVER['PHP_AUTH_PW']];
		} else if($_SERVER['REMOTE_USER']) {
			$out = ["user" => $_SERVER['REMOTE_USER'], "password" => null];
		} else {
			$out = ["user" => null, "password" => null];
		}
		if($this->data->conf->userComposeNames && $out["user"] !== null) {
			$out["user"] = $this->composeName($out["user"]);
		}
		$this->id = $out["user"];
		return $out;
	}

	public function auth(string $user = null, string $password = null): bool {
		if($user===null) {
			if($this->data->conf->userAuthPreferHTTP) {
				return $this->authHTTP();
			} else {
				return $this->authForm();
			}
		} else {
			if($this->u->auth($user, $password)) {
				$this->authPostprocess($user);
				return true;
			} else {
				return false;
			}
		}
	}

	public function authForm(): bool {
		$cred = $this->credentialsForm();
		if(!$cred["user"]) return $this->challengeForm();
		if(!$this->u->auth($cred["user"], $cred["password"])) return $this->challengeForm();
		$this->authPostprocess($cred["user"]);
		return true;
	}

	public function authHTTP(): bool {
		$cred = $this->credentialsHTTP();
		if(!$cred["user"]) return $this->challengeHTTP();
		if(!$this->u->auth($cred["user"], $cred["password"])) return $this->challengeHTTP();
		$this->authPostprocess($cred["user"]);
		return true;
	}

	public function driverFunctions(string $function = null) {
		return $this->u->driverFunctions($function);
	}
	
	public function list(string $domain = null): array {
		if($this->u->driveFunctions("userList") != Driver::FUNC_NOT_IMPLEMENTED) {
			return $this->u->userList($domain);
		} else {
			// N.B. this does not do any authorization checks
			return $this->data->db->userList($domain);
		}
	}

	public function exists(string $user): bool {
		return $this->u->userExists($user);
	}

	public function add($user, $password = null): bool {
		$out = $this->u->userAdd($user, $password);
		if($out && $this->u->driverFunctions("userAdd") != User\Driver::FUNC_INTERNAL) {
			try {
				if(!$this->data->db->userExists($user)) $this->data->db->userAdd($user, $password);
			} catch(\Throwable $e) {}
		}
		return $out;
	}

	public function remove(string $user): bool {
		$out = $this->u->userRemove($user);
		if($out && $this->u->driverFunctions("userRemove") != User\Driver::FUNC_INTERNAL) {
			try {
				if($this->data->db->userExists($user)) $this->data->db->userRemove($user);
			} catch(\Throwable $e) {}
		}
		return $out;
	}

	public function passwordSet(string $user, string $password): bool {
		return $this->u->userPasswordSet($user, $password);
	}

	public function propertiesGet(string $user): array {
		return $this->u->userPropertiesGet($user);
	}

	public function propertiesSet(string $user, array $properties): array {
		return $this->u->userPropertiesSet($user, $properties);
	}

	// FIXME: stubs
	public function challenge(): bool     {throw new User\Exception("authFailed");}
	public function challengeForm(): bool {throw new User\Exception("authFailed");}
	public function challengeHTTP(): bool {throw new User\Exception("authFailed");}

	protected function composeName(string $user): string {
		if(preg_match("/.+?@[^@]+$/",$user)) {
			return $user;
		} else {
			return $user."@".$_SERVER['HTTP_HOST'];
		}
	}

	protected function authPostprocess(string $user): bool {
		return true;
	}
}