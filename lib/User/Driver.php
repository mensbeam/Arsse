<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\User;

interface Driver {
    public function __construct();

    /** Returns a human-friendly name for the driver (for display in installer, for example) */
    public static function driverName(): string;

    /** Authenticates a user against their name and password */
    public function auth(string $user, string $password): bool;

    /** Adds a new user and returns their password
     * 
     * When given no password the implementation may return null; the user
     * manager will then generate a random password and try again with that
     * password. Alternatively the implementation may generate its own 
     * password if desired
     * 
     * @param string $user The username to create
     * @param string|null $password The cleartext password to assign to the user, or null to generate a random password
     */
    public function userAdd(string $user, string $password = null): ?string;

    /** Removes a user */
    public function userRemove(string $user): bool;

    /** Lists all users */
    public function userList(): array;

    /** sets a user's password
     * 
     * When given no password the implementation may return null; the user
     * manager will then generate a random password and try again with that
     * password. Alternatively the implementation may generate its own 
     * password if desired
     * 
     * @param string $user The user for whom to change the password
     * @param string|null $password The cleartext password to assign to the user, or null to generate a random password
     * @param string|null $oldPassword The user's previous password, if known
     */
    public function userPasswordSet(string $user, ?string $newPassword, string $oldPassword = null);

    /** removes a user's password; this makes authentication fail unconditionally 
     * 
     * @param string $user The user for whom to change the password
     * @param string|null $oldPassword The user's previous password, if known
     */
    public function userPasswordUnset(string $user, string $oldPassword = null): bool;
}
