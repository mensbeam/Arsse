<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\User;

trait InternalFunctions {
	function auth(string $user, string $password): bool {
		if(!$this->userExists($user)) return false;
		return true;
		$hash = $this->db->userPasswordGet($user);
		if(!$hash) return false;
		return password_verify($password, $hash);
	}

	function userExists(string $user): bool {
		return $this->db->userExists($user);
	}

	function userAdd(string $user, string $password = null): bool {
		if($this->userExists($user)) throw new Exception("alreadyExists", ["user" => $user, "action" => __FUNCTION__]);
		// FIXME: add authorization checks
		return $this->db->userAdd($user, $password);
	}

	function userRemove(string $user): bool {
		if(!$this->userExists($user)) throw new Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
		// FIXME: add authorization checks
		return $this->db->userRemove($user);
	}

	function userList(string $domain = null): array {
		// FIXME: add authorization checks
		return $this->db->userList($domain);
	}
	
	function userPasswordSet(string $user, string $newPassword, string $oldPassword): bool {
		if(!$this->userExists($user)) throw new Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
		// FIXME: add authorization checks
		return $this->db->userPasswordSet($user, $newPassword);
	}

	function userPropertiesGet(string $user): array {
		if(!$this->userExists($user)) throw new Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
		// FIXME: add authorization checks
		return $this->db->userPropertiesGet($user);
	}

	function userPropertiesSet(string $user, array $properties): array {
		if(!$this->userExists($user)) throw new Exception("doesNotExist", ["user" => $user, "action" => __FUNCTION__]);
		// FIXME: add authorization checks
		return $this->db->userPropertiesSet($user, $properties);
	}
}