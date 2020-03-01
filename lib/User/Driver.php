<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\User;

interface Driver {
    public const FUNC_NOT_IMPLEMENTED = 0;
    public const FUNC_INTERNAL = 1;
    public const FUNC_EXTERNAL = 2;

    // returns an instance of a class implementing this interface.
    public function __construct();
    // returns a human-friendly name for the driver (for display in installer, for example)
    public static function driverName(): string;
    // authenticates a user against their name and password
    public function auth(string $user, string $password): bool;
    // check whether a user is authorized to perform a certain action; not currently used and subject to change
    public function authorize(string $affectedUser, string $action): bool;
    // checks whether a user exists
    public function userExists(string $user): bool;
    // adds a user
    public function userAdd(string $user, string $password = null);
    // removes a user
    public function userRemove(string $user): bool;
    // lists all users
    public function userList(): array;
    // sets a user's password; if the driver does not require the old password, it may be ignored
    public function userPasswordSet(string $user, string $newPassword = null, string $oldPassword = null);
    // removes a user's password; this makes authentication fail unconditionally
    public function userPasswordUnset(string $user, string $oldPassword = null): bool;
}
