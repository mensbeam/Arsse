<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\User\Internal\Driver as InternalDriver;

class User {

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

    public function __construct() {
        $driver = Arsse::$conf->userDriver;
        $this->u = new $driver();
    }

    public function __toString() {
        return (string) $this->id;
    }

    // at one time there was a complicated authorization system; it exists vestigially to support a later revival if desired
    public function authorize(string $affectedUser, string $action): bool {
        return true;
    }

    public function auth(string $user, string $password): bool {
        $prevUser = $this->id ?? null;
        $this->id = $user;
        if (Arsse::$conf->userPreAuth) {
            $out = true;
        } else {
            $out = $this->u->auth($user, $password);
        }
        if ($out && !Arsse::$db->userExists($user)) {
            $this->autoProvision($user, $password);
        }
        if (!$out) {
            $this->id = $prevUser;
        }
        return $out;
    }

    public function list(): array {
        $func = "userList";
        if (!$this->authorize("", $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => ""]);
        }
        return $this->u->userList($domain);
    }

    public function exists(string $user): bool {
        $func = "userExists";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        $out = $this->u->userExists($user);
        if (!$this->u instanceof InternalDriver) {
            // if an alternative driver doesn't match the internal database, add or remove the user as appropriate
            if (!$out && Arsse::$db->userExists($user)) {
                Arsse::$db->userRemove($user);
            } elseif ($out && !Arsse::$db->userExists($user)) {
                $this->autoProvision($user, "");
            }
        }
        return $out;
    }

    public function add($user, $password = null): string {
        $func = "userAdd";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        $newPassword = $this->u->userAdd($user, $password);
        // if there was no exception and we don't have the user in the internal database, add it
        if (!$this->u instanceof InternalDriver && !Arsse::$db->userExists($user)) {
            $this->autoProvision($user, $newPassword);
        }
        return $newPassword;
    }

    public function remove(string $user): bool {
        $func = "userRemove";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        $out = $this->u->userRemove($user);
        if ($out && !$this->u instanceof InternalDriver && Arsse::$db->userExists($user)) {
            // if the user was removed and we have it in our data, remove it there
            Arsse::$db->userRemove($user);
        }
        return $out;
    }

    public function passwordSet(string $user, string $newPassword = null, $oldPassword = null): string {
        $func = "userPasswordSet";
        if (!$this->authorize($user, $func)) {
            throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
        }
        $out = $this->u->userPasswordSet($user, $newPassword, $oldPassword);
        if (!$this->u instanceof InternalDriver && Arsse::$db->userExists($user)) {
            // if the password change was successful and the user exists, set the internal password to the same value
            Arsse::$db->userPasswordSet($user, $out);
        } elseif (!$this->u instanceof InternalDriver){
            // if the user does not exists in the internal database, create it
            $this->autoProvision($user, $out);
        }
        return $out;
    }

    protected function autoProvision(string $user, string $password = null): string {
        $out = Arsse::$db->userAdd($user, $password);
        return $out;
    }
}
