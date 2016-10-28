<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\User;

Interface Driver {
	const FUNC_NOT_IMPLEMENTED = 0;
	const FUNC_INTERNAL = 1;
	const FUNC_EXTERNAL = 2;
	
	static function create(\JKingWeb\NewsSync\RuntimeData $data): Driver;
	static function driverName(): string;
	function driverFunctions(string $function = null);
	function auth(string $user, string $password): bool;
	function userExists(string $user): bool;
	function userAdd(string $user, string $password = null): bool;
	function userRemove(string $user): bool;
	function userList(string $domain = null): array;
	function userPasswordSet(string $user, string $newPassword, string $oldPassword): bool;
	function userPropertiesGet(string $user): array;
	function userPropertiesSet(string $user, array $properties): array;
}