<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\User;

interface Driver {
    const FUNC_NOT_IMPLEMENTED = 0;
    const FUNC_INTERNAL = 1;
    const FUNC_EXTERNAL = 2;

    const RIGHTS_NONE           = 0;    // normal user
    const RIGHTS_DOMAIN_MANAGER = 25;   // able to act for any normal users on same domain; cannot elevate other users
    const RIGHTS_DOMAIN_ADMIN   = 50;   // able to act for any users on same domain not above themselves; may elevate users on same domain to domain manager or domain admin
    const RIGHTS_GLOBAL_MANAGER = 75;   // able to act for any normal users on any domain; cannot elevate other users
    const RIGHTS_GLOBAL_ADMIN   = 100;  // is completely unrestricted

    // returns an instance of a class implementing this interface.
    public function __construct();
    // returns a human-friendly name for the driver (for display in installer, for example)
    public static function driverName(): string;
    // returns an array (or single queried member of same) of methods defined by this interface and whether the class implements the internal function or a custom version
    public function driverFunctions(string $function = null);
    // authenticates a user against their name and password
    public function auth(string $user, string $password): bool;
    // checks whether a user exists
    public function userExists(string $user): bool;
    // adds a user
    public function userAdd(string $user, string $password = null): string;
    // removes a user
    public function userRemove(string $user): bool;
    // lists all users
    public function userList(string $domain = null): array;
    // sets a user's password; if the driver does not require the old password, it may be ignored
    public function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null): string;
    // gets user metadata (currently not useful)
    public function userPropertiesGet(string $user): array;
    // sets user metadata (currently not useful)
    public function userPropertiesSet(string $user, array $properties): array;
    // returns a user's access level according to RIGHTS_* constants (or some custom semantics, if using custom implementation of authorize())
    public function userRightsGet(string $user): int;
    // sets a user's access level
    public function userRightsSet(string $user, int $level): bool;
}
