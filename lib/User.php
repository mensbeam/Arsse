<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\ValueInfo as V;
use JKingWeb\Arsse\User\ExceptionConflict as Conflict;
use PasswordGenerator\Generator as PassGen;

class User {
    public const DRIVER_NAMES = [
        'internal' => \JKingWeb\Arsse\User\Internal\Driver::class,
    ];

    public $id = null;

    /** @var User\Driver */
    protected $u;

    public function __construct(\JKingWeb\Arsse\User\Driver $driver = null) {
        $this->u = $driver ?? new Arsse::$conf->userDriver;
    }

    public function __toString() {
        return (string) $this->id;
    }

    public function auth(string $user, string $password): bool {
        $prevUser = $this->id;
        $this->id = $user;
        if (Arsse::$conf->userPreAuth) {
            $out = true;
        } else {
            $out = $this->u->auth($user, $password);
        }
        // if authentication was successful and we don't have the user in the internal database, add it
        // users must be in the internal database to preserve referential integrity
        if ($out && !Arsse::$db->userExists($user)) {
            Arsse::$db->userAdd($user, $password);
        }
        $this->id = $prevUser;
        return $out;
    }

    public function list(): array {
        return $this->u->userList();
    }

    public function add(string $user, ?string $password = null): string {
        // ensure the user name does not contain any U+003A COLON characters, as
        // this is incompatible with HTTP Basic authentication
        if (strpos($user, ":") !== false) {
            throw new User\ExceptionInput("invalidUsername", "U+003A COLON");
        }
        try {
            $out = $this->u->userAdd($user, $password) ?? $this->u->userAdd($user, $this->generatePassword());
        } catch (Conflict $e) {
            if (!Arsse::$db->userExists($user)) {
                Arsse::$db->userAdd($user, null);
            }
            throw $e;
        }
        // synchronize the internal database
        if (!Arsse::$db->userExists($user)) {
            Arsse::$db->userAdd($user, $out);
        }
        return $out;
    }
    

    public function remove(string $user): bool {
        try {
            $out = $this->u->userRemove($user);
        } catch (Conflict $e) {
            if (Arsse::$db->userExists($user)) {
                Arsse::$db->userRemove($user);
            }
            throw $e;
        }
        if (Arsse::$db->userExists($user)) {
            // if the user was removed and we (still) have it in the internal database, remove it there
            Arsse::$db->userRemove($user);
        }
        return $out;
    }

    public function passwordSet(string $user, ?string $newPassword, $oldPassword = null): string {
        $out = $this->u->userPasswordSet($user, $newPassword, $oldPassword) ?? $this->u->userPasswordSet($user, $this->generatePassword(), $oldPassword);
        if (Arsse::$db->userExists($user)) {
            // if the password change was successful and the user exists, set the internal password to the same value
            Arsse::$db->userPasswordSet($user, $out);
            // also invalidate any current sessions for the user
            Arsse::$db->sessionDestroy($user);
        } else {
            // if the user does not exist, add it with the new password
            Arsse::$db->userAdd($user, $out);
        }
        return $out;
    }

    public function passwordUnset(string $user, $oldPassword = null): bool {
        $out = $this->u->userPasswordUnset($user, $oldPassword);
        if (Arsse::$db->userExists($user)) {
            // if the password change was successful and the user exists, set the internal password to the same value
            Arsse::$db->userPasswordSet($user, null);
            // also invalidate any current sessions for the user
            Arsse::$db->sessionDestroy($user);
        }
        return $out;
    }

    public function generatePassword(): string {
        return (new PassGen)->length(Arsse::$conf->userTempPasswordLength)->get();
    }

    public function propertiesGet(string $user): array {
        $extra = $this->u->userPropertiesGet($user);
        // synchronize the internal database
        if (!Arsse::$db->userExists($user)) {
            Arsse::$db->userAdd($user, null);
            Arsse::$db->userPropertiesSet($user, $extra);
        }
        // retrieve from the database to get at least the user number, and anything else the driver does not provide
        $out = Arsse::$db->userPropertiesGet($user);
        // layer on the driver's data
        foreach (["tz", "admin", "sort_asc"] as $k) {
            if (array_key_exists($k, $extra)) {
                $out[$k] = $extra[$k] ?? $out[$k];
            }
        }
        // treat language specially since it may legitimately be null
        if (array_key_exists("lang", $extra)) {
            $out['lang'] = $extra['lang'];
        }
        return $out;
    }

    public function propertiesSet(string $user, array $data): array {
        $in = [];
        if (array_key_exists("tz", $data)) {
            if (!is_string($data['tz'])) {
                throw new User\ExceptionInput("invalidTimezone", ['field' => "tz", 'value' => ""]);
            } elseif(!@timezone_open($data['tz'])) {
                throw new User\ExceptionInput("invalidTimezone", ['field' => "tz", 'value' => $data['tz']]);
            }
            $in['tz'] = $data['tz'];
        }
        foreach (["admin", "sort_asc"] as $k) {
            if (array_key_exists($k, $data)) {
                if (($v = V::normalize($data[$k], V::T_BOOL | V::M_DROP)) === null) {
                    throw new User\ExceptionInput("invalidBoolean", $k);
                }
                $in[$k] = $v;
            }
        }
        if (array_key_exists("lang", $data)) {
            $in['lang'] = V::normalize($data['lang'], V::T_STRING | V::M_NULL);
        }
        $out = $this->u->userPropertiesSet($user, $in);
        // synchronize the internal database
        if (!Arsse::$db->userExists($user)) {
            Arsse::$db->userAdd($user, null);
        }
        Arsse::$db->userPropertiesSet($user, $out);
        return $out;
    }
}
