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
    public const PROPERTIES = [
        'admin'        => V::T_BOOL,
        'lang'         => V::T_STRING,
        'tz'           => V::T_STRING,
        'sort_asc'     => V::T_BOOL,
        'theme'        => V::T_STRING,
        'page_size'    => V::T_INT, // greater than zero
        'shortcuts'    => V::T_BOOL,
        'gestures'     => V::T_BOOL,
        'stylesheet'   => V::T_STRING,
        'reading_time' => V::T_BOOL,
    ];
    public const PROPERTIES_LARGE = ["stylesheet"];

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

    public function propertiesGet(string $user, bool $includeLarge = true): array {
        $extra = $this->u->userPropertiesGet($user, $includeLarge);
        // synchronize the internal database
        if (!Arsse::$db->userExists($user)) {
            Arsse::$db->userAdd($user, null);
            Arsse::$db->userPropertiesSet($user, $extra);
        }
        // retrieve from the database to get at least the user number, and anything else the driver does not provide
        $meta = Arsse::$db->userPropertiesGet($user, $includeLarge);
        // combine all the data
        $out = ['num' => $meta['num']];
        foreach (self::PROPERTIES as $k => $t) {
            if (array_key_exists($k, $extra)) {
                $v = $extra[$k];
            } elseif (array_key_exists($k, $meta)) {
                $v = $meta[$k];
            } else {
                $v = null;
            }
            $out[$k] = V::normalize($v, $t | V::M_NULL);
        }
        return $out;
    }

    public function propertiesSet(string $user, array $data): array {
        $in = [];
        foreach (self::PROPERTIES as $k => $t) {
            if (array_key_exists($k, $data)) {
                try {
                    $in[$k] = V::normalize($data[$k], $t | V::M_NULL | V::M_STRICT);
                } catch (\JKingWeb\Arsse\ExceptionType $e) {
                    throw new User\ExceptionInput("invalidValue", ['field' => $k, 'type' => $t], $e);
                }
            }
        }
        if (isset($in['tz']) && !@timezone_open($in['tz'])) {
            throw new User\ExceptionInput("invalidTimezone", ['field' => "tz", 'value' => $in['tz']]);
        } elseif (isset($in['page_size']) && $in['page_size'] < 1) {
            throw new User\ExceptionInput("invalidNonZeroInteger", ['field' => "page_size"]);
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
