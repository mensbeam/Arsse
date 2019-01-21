<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use PasswordGenerator\Generator as PassGen;

class User {
    const DRIVER_NAMES = [
        'internal' => \JKingWeb\Arsse\User\Internal\Driver::class,
    ];

    public $id = null;

    /**
    * @var User\Driver
    */
    protected $u;

    public static function driverList(): array {
        $sep = \DIRECTORY_SEPARATOR;
        $path = __DIR__.$sep."User".$sep;
        $classes = [];
        foreach (glob($path."*".$sep."Driver.php") as $file) {
            $name = basename(dirname($file));
            $class = NS_BASE."User\\$name\\Driver";
            $classes[$class] = $class::driverName();
        }
        return $classes;
    }

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
        }
        return $out;
    }

    protected function generatePassword(): string {
        return (new PassGen)->length(Arsse::$conf->userTempPasswordLength)->get();
    }
}
