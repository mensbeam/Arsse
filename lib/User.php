<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\ValueInfo as V;
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

    public function authorize(string $affectedUser, string $action): bool {
        // at one time there was a complicated authorization system; it exists vestigially to support a later revival if desired
        return $this->u->authorize($affectedUser, $action);
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
        $func = "userList";
        if (!$this->authorize("", $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => ""]);
        }
        return $this->u->userList();
    }

    public function exists(string $user): bool {
        $func = "userExists";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        return $this->u->userExists($user);
    }

    public function add($user, $password = null): string {
        $func = "userAdd";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        return $this->u->userAdd($user, $password) ?? $this->u->userAdd($user, $this->generatePassword());
    }

    public function remove(string $user): bool {
        $func = "userRemove";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        try {
            return $this->u->userRemove($user);
        } finally { // @codeCoverageIgnore
            if (Arsse::$db->userExists($user)) {
                // if the user was removed and we (still) have it in the internal database, remove it there
                Arsse::$db->userRemove($user);
            }
        }
    }

    public function passwordSet(string $user, string $newPassword = null, $oldPassword = null): string {
        $func = "userPasswordSet";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        $out = $this->u->userPasswordSet($user, $newPassword, $oldPassword) ?? $this->u->userPasswordSet($user, $this->generatePassword(), $oldPassword);
        if (Arsse::$db->userExists($user)) {
            // if the password change was successful and the user exists, set the internal password to the same value
            Arsse::$db->userPasswordSet($user, $out);
            // also invalidate any current sessions for the user
            Arsse::$db->sessionDestroy($user);
        }
        return $out;
    }

    public function passwordUnset(string $user, $oldPassword = null): bool {
        $func = "userPasswordUnset";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
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
        // unconditionally retrieve from the database to get at least the user number, and anything else the driver does not provide
        $out = Arsse::$db->userPropertiesGet($user);
        // layer on the driver's data
        $extra = $this->u->userPropertiesGet($user);
        foreach (["lang", "tz", "admin", "sort_asc"] as $k) {
            if (array_key_exists($k, $extra)) {
                $out[$k] = $extra[$k] ?? $out[$k];
            }
        }
        return $out;
    }
    
    public function propertiesSet(string $user, array $data): bool {
        $in = [];
        if (array_key_exists("tz", $data)) {
            if (!is_string($data['tz'])) {
                throw new User\ExceptionInput("invalidTimezone");
            } elseif (!in_array($data['tz'], \DateTimeZone::listIdentifiers())) {
                throw new User\ExceptionInput("invalidTimezone", $data['tz']);
            }
            $in['tz'] = $data['tz'];
        }
        foreach (["admin", "sort_asc"] as $k) {
            if (array_key_exists($k, $data)) {
                if (($v = V::normalize($data[$k], V::T_BOOL)) === null) {
                    throw new User\ExceptionInput("invalidBoolean", $k);
                }
                $in[$k] = $v;
            }
        }
        if (array_key_exists("lang", $data)) {
            $in['lang'] = V::normalize($data['lang'], V::T_STRING | M_NULL);
        }
        $out = $this->u->userPropertiesSet($user, $in);
        // synchronize the internal database
        Arsse::$db->userPropertiesSet($user, $in);
        return $out;
    }
}
