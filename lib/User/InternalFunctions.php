<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\User;

trait InternalFunctions {    
    protected $actor = [];

    public function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
        $this->data = $data;
        $this->db = $this->data->db;
    }

    function auth(string $user, string $password): bool {
        if(!$this->data->user->exists($user)) return false;
        $hash = $this->db->userPasswordGet($user);
        if(!$hash) return false;
        return password_verify($password, $hash);
    }

    function userExists(string $user): bool {
        return $this->db->userExists($user);
    }

    function userAdd(string $user, string $password = null): string {
        return $this->db->userAdd($user, $password);
    }

    function userRemove(string $user): bool {
        return $this->db->userRemove($user);
    }

    function userList(string $domain = null): array {
        return $this->db->userList($domain);
    }
    
    function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): bool {
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