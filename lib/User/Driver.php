<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\User;

interface Driver {
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
    public function userAdd(string $user, ?string $password = null): ?string;

    /** Renames a user
     *
     * The implementation must retain all user metadata as well as the
     * user's password
     */
    public function userRename(string $user, string $newName): bool;

    /** Removes a user */
    public function userRemove(string $user): bool;

    /** Lists all users */
    public function userList(): array;

    /** Sets a user's password
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
    public function userPasswordSet(string $user, ?string $newPassword, ?string $oldPassword = null): ?string;

    /** Removes a user's password; this makes authentication fail unconditionally
     *
     * @param string $user The user for whom to change the password
     * @param string|null $oldPassword The user's previous password, if known
     */
    public function userPasswordUnset(string $user, ?string $oldPassword = null): bool;

    /** Retrieves metadata about a user
     *
     * Any expected keys not returned by the driver are taken from the internal
     * database instead; the expected keys at this time are:
     *
     * - admin: A boolean denoting whether the user has administrator privileges
     * - lang: A BCP 47 language tag e.g. "en", "hy-Latn-IT-arevela"
     * - tz: A zoneinfo timezone e.g. "Asia/Jakarta", "America/Argentina/La_Rioja"
     * - sort_asc: A boolean denoting whether the user prefers articles to be sorted oldest-first
     *
     * Any other keys will be ignored.
     */
    public function userPropertiesGet(string $user, bool $includeLarge = true): array;

    /** Sets metadata about a user
     *
     * Output should be the same as the input, unless input is changed prior to storage
     * (if it is, for instance, normalized in some way), which which case the changes
     * should be reflected in the output.
     *
     * @param string $user The user for which to set metadata
     * @param array $data The input data; see userPropertiesGet for keys
     */
    public function userPropertiesSet(string $user, array $data): array;
}
