<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\User;

trait InternalFunctions {	
	protected $actor = [];
	
	function auth(string $user, string $password): bool {
		if(!$this->data->user->exists($user)) return false;
		$hash = $this->db->userPasswordGet($user);
		if(!$hash) return false;
		return password_verify($password, $hash);
	}

	function authorize(string $affectedUser, string $action, int $newRightsLevel = 0): bool {
		// if the affected user is the actor and the actor is not trying to grant themselves rights, accept the request
		if($affectedUser==$this->data->user->id && $action != "userRightsSet") return true;
		// get properties of actor if not already available
		if(!sizeof($this->actor)) $this->actor = $this->data->user->propertiesGet($this->data->user->id);
		$rights =& $this->actor["rights"];
		// if actor is a global admin, accept the request
		if($rights==self::RIGHTS_GLOBAL_ADMIN) return true;
		// if actor is a common user, deny the request
		if($rights==self::RIGHTS_NONE) return false;
		// if actor is not some other sort of admin, deny the request
		if(!in_array($rights,[self::RIGHTS_GLOBAL_MANAGER,self::RIGHTS_DOMAIN_MANAGER,self::RIGHTS_DOMAIN_ADMIN],true)) return false;
		// if actor is a domain admin/manager and domains don't match, deny the request
		if($this->data->conf->userComposeNames && $this->actor["domain"] && $rights != self::RIGHTS_GLOBAL_MANAGER) {
			$test = "@".$this->actor["domain"];
			if(substr($affectedUser,-1*strlen($test)) != $test) return false;
		}
		// certain actions shouldn't check affected user's rights
		if(in_array($action, ["userRightsGet","userExists","userList"], true)) return true;
		if($action=="userRightsSet") {
			// setting rights above your own (or equal to your own, for managers) is not allowed
			if($newRightsLevel > $rights || ($rights != self::RIGHTS_DOMAIN_ADMIN && $newRightsLevel==$rights)) return false;
		}
		$affectedRights = $this->data->user->rightsGet($affectedUser);
		// acting for users with rights greater than your own (or equal, for managers) is not allowed
		if($affectedRights > $rights || ($rights != self::RIGHTS_DOMAIN_ADMIN && $affectedRights==$rights)) return false;
		return true;
	}

	function userExists(string $user): bool {
		return $this->db->userExists($user);
	}

	function userAdd(string $user, string $password = null): bool {
		return $this->db->userAdd($user, $password);
	}

	function userRemove(string $user): bool {
		return $this->db->userRemove($user);
	}

	function userList(string $domain = null): array {
		return $this->db->userList($domain);
	}
	
	function userPasswordSet(string $user, string $newPassword, string $oldPassword): bool {
		return $this->db->userPasswordSet($user, $newPassword);
	}

	function userPropertiesGet(string $user): array {
		return $this->db->userPropertiesGet($user);
	}

	function userPropertiesSet(string $user, array $properties): array {
		return $this->db->userPropertiesSet($user, $properties);
	}

	function userRightsGet(string $user): int {
		return $this->db->userRightsGet($user);
	}
	
	function userRightsSet(string $user, int $level): bool {
		return $this->db->userRightsSet($user, $level);
	} 
}