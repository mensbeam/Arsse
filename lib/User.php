<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class User {
    public  $id = null;

    protected $data;
    protected $u;
    protected $authz = true;
    protected $authzSupported = 0;
    
    static public function listDrivers(): array {
        $sep = \DIRECTORY_SEPARATOR;
        $path = __DIR__.$sep."User".$sep;
        $classes = [];
        foreach(glob($path."Driver?*.php") as $file) {
            $drv = basename($file, ".php");
            $drv = NS_BASE."Db\\$drv";
            if(class_exists($drv)) {
                $classes[$drv] = $drv::driverName();
            }             
        }
        return $classes;
    }

    public function __construct(\JKingWeb\NewsSync\RuntimeData $data) {
        $this->data = $data;
        $driver = $data->conf->userDriver;
        $this->u = $driver::create($data);
        $this->authzSupported = $this->u->driverFunctions("authorize");
    }

    public function __toString() {
        if($this->id===null) $this->credentials();
        return (string) $this->id;
    }

    public function credentials(): array {
        if($this->data->conf->userAuthPreferHTTP) {
            return $this->credentialsHTTP();
        } else {
            return $this->credentialsForm();
        }
    }

    public function credentialsForm(): array {
        // FIXME: stub
        $this->id = "john.doe@example.com";
        return ["user" => "john.doe@example.com", "password" => "secret"];
    }

    public function credentialsHTTP(): array {
        if($_SERVER['PHP_AUTH_USER']) {
            $out = ["user" => $_SERVER['PHP_AUTH_USER'], "password" => $_SERVER['PHP_AUTH_PW']];
        } else if($_SERVER['REMOTE_USER']) {
            $out = ["user" => $_SERVER['REMOTE_USER'], "password" => ""];
        } else {
            $out = ["user" => "", "password" => ""];
        }
        if($this->data->conf->userComposeNames && $out["user"] != "") {
            $out["user"] = $this->composeName($out["user"]);
        }
        $this->id = $out["user"];
        return $out;
    }

    public function auth(string $user = null, string $password = null): bool {
        if($user===null) {
            if($this->data->conf->userAuthPreferHTTP) return $this->authHTTP();
            return $this->authForm();
        } else {
            switch($this->u->driverFunctions("auth")) {
                case User\Driver::FUNC_EXTERNAL:
                    $out = $this->u->auth($user, $password);
                    if($out && !$this->data->db->userExists($user)) $this->autoProvision($user, $password);
                    return $out;
                case User\Driver::FUNC_INTERNAL:
                    return $this->u->auth($user, $password);
                case User\Driver::FUNCT_NOT_IMPLEMENTED:
                    return false;
            }
        }
    }

    public function authForm(): bool {
        $cred = $this->credentialsForm();
        if(!$cred["user"]) return $this->challengeForm();
        if(!$this->auth($cred["user"], $cred["password"])) return $this->challengeForm();
        return true;
    }

    public function authHTTP(): bool {
        $cred = $this->credentialsHTTP();
        if(!$cred["user"]) return $this->challengeHTTP();
        if(!$this->auth($cred["user"], $cred["password"])) return $this->challengeHTTP();
        return true;
    }

    public function driverFunctions(string $function = null) {
        return $this->u->driverFunctions($function);
    }
    
    public function list(string $domain = null): array {
        $func = "userList";
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if($domain===null) {
                    if(!$this->authorize("@".$domain, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $domain]);
                } else {
                    if(!$this->authorize("", $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => "all users"]);
                }
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userList($domain);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $domain]);
        }
    }

    public function authorize(string $affectedUser, string $action, int $promoteLevel = 0): bool {
        // if authorization checks are disabled (either because we're running the installer or the background updater) just return true
        if(!$this->authz) return true;
        // if we don't have a logged-in user, fetch credentials
        if($this->id===null) $this->credentials();
        // if the driver implements authorization, return the result
        if($this->authzSupported) return $this->u->authorize($affectedUser, $action, $promoteLevel);
        // if the driver does not implement authorization, only allow operation for the logged-in user (this means no new users can be added)
        if($affectedUser==$this->id && $action != "userRightsSet") return true;
        return false;        
    }

    public function authorizationEnabled(bool $setting = null): bool {
        if($setting===null) return $this->authz;
        $this->authz = $setting;
        return $setting;
    }
    
    public function exists(string $user): bool {
        $func = "userExists";
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = $this->u->userExists($user);
                if($out && !$this->data->db->userExist($user)) $this->autoProvision($user, "");
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
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $newPassword = $this->u->userAdd($user, $password);
                // if there was no exception and we don't have the user in the internal database, add it
                if(!$this->data->db->userExists($user)) $this->autoProvision($user, $newPassword);
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
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = $this->u->userRemove($user);
                if($out && $this->data->db->userExists($user)) {
                    // if the user was removed and we have it in our data, remove it there
                    if(!$this->data->db->userExists($user)) $this->data->db->userRemove($user);
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
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = $this->u->userPasswordSet($user, $newPassword, $oldPassword);
                if($this->data->db->userExists($user)) {
                    // if the password change was successful and the user exists, set the internal password to the same value
                    $this->data->db->userPasswordSet($user, $out);
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

    public function propertiesGet(string $user): array {
        // prepare default values
        $domain = null;
        if($this->data->conf->userComposeNames) $domain = substr($user,strrpos($user,"@")+1);
        $init = [
            "id"     => $user,
            "name"   => $user,
            "rights" => User\Driver::RIGHTS_NONE,
            "domain" => $domain
        ];
        $func = "userPropertiesGet";
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = array_merge($init, $this->u->userPropertiesGet($user));
                // remove password if it is return (not exhaustive, but...)
                if(array_key_exists('password', $out)) unset($out['password']);
                // if the user does not exist in the internal database, add it
                if(!$this->data->db->userExists($user)) $this->autoProvision($user, "", $out);
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return array_merge($init, $this->u->userPropertiesGet($user));
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                // we can return generic values if the function is not implemented
                return $init;
        }
    }

    public function propertiesSet(string $user, array $properties): array {
        // remove from the array any values which should be set specially
        foreach(['password', 'rights'] as $key) {
            if(array_key_exists($key, $properties)) unset($properties[$key]);
        }
        $func = "userPropertiesSet";
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = $this->u->userPropertiesSet($user, $properties);
                if($this->data->db->userExists($user)) {
                    // if the property change was successful and the user exists, set the internal properties to the same values
                    $this->data->db->userPpropertiesSet($user, $out);
                } else {
                    // if the user does not exists in the internal database, create it
                    $this->autoProvision($user, "", $out);
                }
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userPropertiesSet($user, $properties);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $user]);
        }
    }

    public function rightsGet(string $user): int {
        $func = "userRightsGet";
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = $this->u->userRightsGet($user);
                // if the user does not exist in the internal database, add it
                if(!$this->data->db->userExists($user)) $this->autoProvision($user, "", null, $out);
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userRightsGet($user);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                // assume all users are unprivileged
                return User\Driver::RIGHTS_NONE;
        }
    }
    
    public function rightsSet(string $user, int $level): bool {
        $func = "userRightsSet";
        switch($this->u->driverFunctions($func)) {
            case User\Driver::FUNC_EXTERNAL:
                // we handle authorization checks for external drivers
                if(!$this->authorize($user, $func)) throw new User\ExceptionAuthz("notAuthorized", ["action" => $func, "user" => $user]);
                $out = $this->u->userRightsSet($user, $level);
                // if the user does not exist in the internal database, add it
                if($out && $this->data->db->userExists($user)) {
                    $authz = $this->authorizationEnabled();
                    $this->authorizationEnabled(false);
                    $this->data->db->userRightsSet($user, $level);
                    $this->authorizationEnabled($authz);
                } else if($out) {
                    $this->autoProvision($user, "", null, $level);
                }
                return $out;
            case User\Driver::FUNC_INTERNAL:
                // internal functions handle their own authorization
                return $this->u->userRightsSet($user, $level);
            case User\Driver::FUNCT_NOT_IMPLEMENTED:
                throw new User\ExceptionNotImplemented("notImplemented", ["action" => $func, "user" => $user]);
        }
    }
    
    // FIXME: stubs
    public function challenge(): bool     {throw new User\Exception("authFailed");}
    public function challengeForm(): bool {throw new User\Exception("authFailed");}
    public function challengeHTTP(): bool {throw new User\Exception("authFailed");}

    protected function composeName(string $user): string {
        if(preg_match("/.+?@[^@]+$/",$user)) {
            return $user;
        } else {
            return $user."@".$_SERVER['HTTP_HOST'];
        }
    }

    protected function autoProvision(string $user, string $password = null, array $properties = null, int $rights = 0): string {
        // temporarily disable authorization checks, to avoid potential problems
        $authz = $this->authorizationEnabled();
        $this->authorizationEnabled(false);
        // create the user
        $out = $this->data->db->userAdd($user, $password);
        // set the user rights
        $this->data->db->userRightsSet($user, $level);
        // set the user properties...
        if($properties===null) {
            // if nothing is provided but the driver uses an external function, try to get the current values from the external source
            try {
                if($this->u->driverFunctions("userPropertiesGet")==User\Driver::FUNC_EXTERNAL) $this->data->db->userPropertiesSet($user, $this->u->userPropertiesGet($user));
            } catch(\Throwable $e) {}
        } else {
            // otherwise if values are provided, use those
            $this->data->db->userPropertiesSet($user, $properties);
        }
        $this->authorizationEnabled($authz);
        return $out;
    }
}