<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

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
        switch ($this->u->driverFunctions("auth")) {
            case User\Driver::FUNC_EXTERNAL:
                if (Arsse::$conf->userPreAuth) {
                    $out = true;
                } else {
                    $out = $this->u->auth($user, $password);
                }
                if ($out && !Arsse::$db->userExists($user)) {
                    $this->autoProvision($user, $password);
                }
                break;
            case User\Driver::FUNC_INTERNAL:
                if (Arsse::$conf->userPreAuth) {
                    if (!Arsse::$db->userExists($user)) {
                        $this->autoProvision($user, $password);
                    }
                    $out = true;
                } else {
                    $out = $this->u->auth($user, $password);
                }
                break;
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                $out = false;
                break;
        }
        if (!$out) {
            $this->id = $prevUser;
        }
        return $out;
    }

    public function driverFunctions(string $function = null) {
        return $this->u->driverFunctions($function);
    }

    public function list(): array {
        $func = "userList";
        switch ($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if (!$this->authorize("", $func)) {
                    throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => ""]);
                }
                // no break
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userList($domain);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $domain]);
        }
    }

    public function exists(string $user): bool {
        $func = "userExists";
        switch ($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if (!$this->authorize($user, $func)) {
                    throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                }
                $out = $this->u->userExists($user);
                if ($out && !Arsse::$db->userExists($user)) {
                    $this->autoProvision($user, "");
                }
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userExists($user);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                // throwing an exception here would break all kinds of stuff; we just report that the user exists
                return true;
        }
    }

    public function add($user, $password = null): string {
        $func = "userAdd";
        switch ($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if (!$this->authorize($user, $func)) {
                    throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                }
                $newPassword = $this->u->userAdd($user, $password);
                // if there was no exception and we don't have the user in the internal database, add it
                if (!Arsse::$db->userExists($user)) {
                    $this->autoProvision($user, $newPassword);
                }
                return $newPassword;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userAdd($user, $password);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $user]);
        }
    }

    public function remove(string $user): bool {
        $func = "userRemove";
        switch ($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if (!$this->authorize($user, $func)) {
                    throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                }
                $out = $this->u->userRemove($user);
                if ($out && Arsse::$db->userExists($user)) {
                    // if the user was removed and we have it in our data, remove it there
                    if (!Arsse::$db->userExists($user)) {
                        Arsse::$db->userRemove($user);
                    }
                }
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userRemove($user);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $user]);
        }
    }

    public function passwordSet(string $user, string $newPassword = null, $oldPassword = null): string {
        $func = "userPasswordSet";
        switch ($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if (!$this->authorize($user, $func)) {
                    throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                }
                $out = $this->u->userPasswordSet($user, $newPassword, $oldPassword);
                if (Arsse::$db->userExists($user)) {
                    // if the password change was successful and the user exists, set the internal password to the same value
                    Arsse::$db->userPasswordSet($user, $out);
                } else {
                    // if the user does not exists in the internal database, create it
                    $this->autoProvision($user, $out);
                }
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userPasswordSet($user, $newPassword);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $user]);
        }
    }

    protected function autoProvision(string $user, string $password = null): string {
        $out = Arsse::$db->userAdd($user, $password);
        return $out;
    }
}
