<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class User {
    public  $id = null;

    protected $data;
    protected $u;
    protected $authz = true;
    protected $existSupported = 0;
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
        $this->existSupported = $this->u->driverFunctions("userExists");
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
            if($this->u->auth($user, $password)) {
                $this->authPostProcess($user, $password);
                return true;
            }
            return false;
        }
    }

    public function authForm(): bool {
        $cred = $this->credentialsForm();
        if(!$cred["user"]) return $this->challengeForm();
        if(!$this->u->auth($cred["user"], $cred["password"])) return $this->challengeForm();
        $this->authPostProcess($cred["user"], $cred["password"]);
        return true;
    }

    public function authHTTP(): bool {
        $cred = $this->credentialsHTTP();
        if(!$cred["user"]) return $this->challengeHTTP();
        if(!$this->u->auth($cred["user"], $cred["password"])) return $this->challengeHTTP();
        $this->authPostProcess($cred["user"], $cred["password"]);
        return true;
    }

    public function driverFunctions(string $function = null) {
        return $this->u->driverFunctions($function);
    }
    
    public function list(string $domain = null): array {
        if($this->u->driverFunctions("userList")==User\Driver::FUNC_EXTERNAL) {
            if($domain===null) {
                if(!$this->data->user->authorize("@".$domain, "userList")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userList", "user" => $domain]);
            } else {
                if(!$this->data->user->authorize("", "userList")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userList", "user" => "all users"]);
            }
            return $this->u->userList($domain);
        } else {
            return $this->data->db->userList($domain);
        }
    }

    public function authorize(string $affectedUser, string $action, int $promoteLevel = 0): bool {
        if(!$this->authz) return true;
        if($this->id===null) $this->credentials();
        if($this->authzSupported) return $this->u->authorize($affectedUser, $action, $promoteLevel);
        // if the driver does not implement authorization, only allow operation for the current user (this means no new users can be added)
        if($affectedUser==$this->id && $action != "userRightsSet") return true;
        return false;        
    }

    public function authorizationEnabled(bool $setting = null): bool {
        if($setting===null) return $this->authz;
        $this->authz = $setting;
        return $setting;
    }
    
    public function exists(string $user): bool {
        if($this->u->driverFunctions("userExists") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userExists")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userExists", "user" => $user]);
        }
        if(!$this->existSupported) return true;
        $out = $this->u->userExists($user);
        if($out && $this->existSupported==User\Driver::FUNC_EXTERNAL && !$this->data->db->userExist($user)) {
            try {$this->data->db->userAdd($user);} catch(\Throwable $e) {}
        }
        return $out;
    }

    public function add($user, $password = null): bool {
        if($this->u->driverFunctions("userAdd") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userAdd")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userAdd", "user" => $user]);
        }
        if($this->exists($user)) return false;
        $out = $this->u->userAdd($user, $password);
        if($out && $this->u->driverFunctions("userAdd") != User\Driver::FUNC_INTERNAL) {
            try {
                if(!$this->data->db->userExists($user)) $this->data->db->userAdd($user, $password);
            } catch(\Throwable $e) {}
        }
        return $out;
    }

    public function remove(string $user): bool {
        if($this->u->driverFunctions("userRemove") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userRemove")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userRemove", "user" => $user]);
        }
        if(!$this->exists($user)) return false;
        $out = $this->u->userRemove($user);
        if($out && $this->u->driverFunctions("userRemove") != User\Driver::FUNC_INTERNAL) {
            try {
                if($this->data->db->userExists($user)) $this->data->db->userRemove($user);
            } catch(\Throwable $e) {}
        }
        return $out;
    }

    public function passwordSet(string $user, string $password): bool {
        if($this->u->driverFunctions("userPasswordSet") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userPasswordSet")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userPasswordSet", "user" => $user]);
        }
        if(!$this->exists($user)) return false;
        return $this->u->userPasswordSet($user, $password);
    }

    public function propertiesGet(string $user): array {
        if($this->u->driverFunctions("userPropertiesGet") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userPropertiesGet")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userPropertiesGet", "user" => $user]);
        }
        if(!$this->exists($user)) return false;
        $domain = null;
        if($this->data->conf->userComposeNames) $domain = substr($user,strrpos($user,"@")+1);
        $init = [
            "id"     => $user,
            "name"   => $user,
            "rights" => User\Driver::RIGHTS_NONE,
            "domain" => $domain
        ];
        if($this->u->driverFunctions("userPropertiesGet") != User\Driver::FUNC_NOT_IMPLEMENTED) {
            return array_merge($init, $this->u->userPropertiesGet($user));
        }
        return $init;
    }

    public function propertiesSet(string $user, array $properties): array {
        if($this->u->driverFunctions("userPropertiesSet") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userPropertiesSet")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userPropertiesSet", "user" => $user]);
        }
        if(!$this->exists($user)) throw new User\Exception("doesNotExist", ["user" => $user, "action" => "userPropertiesSet"]);
        return $this->u->userPropertiesSet($user, $properties);
    }

    public function rightsGet(string $user): int {
        if($this->u->driverFunctions("userRightsGet") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userRightsGet")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userRightsGet", "user" => $user]);
        }
        // we do not throw an exception here if the user does not exist, because it makes no material difference
        if(!$this->exists($user)) return User\Driver::RIGHTS_NONE;
        return $this->u->userRightsGet($user);
    }
    
    public function rightsSet(string $user, int $level): bool {
        if($this->u->driverFunctions("userRightsSet") != User\Driver::FUNC_INTERNAL) {
            if(!$this->data->user->authorize($user, "userRightsSet")) throw new User\ExceptionAuthz("notAuthorized", ["action" => "userRightsSet", "user" => $user]);
        }
        if(!$this->exists($user)) return false;
        return $this->u->userRightsSet($user, $level);
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

    protected function authPostprocess(string $user, string $password): bool {
        if($this->u->driverFunctions("auth") != User\Driver::FUNC_INTERNAL && !$this->data->db->userExists($user)) {
            if($password=="") $password = null;
            try {$this->data->db->userAdd($user, $password);} catch(\Throwable $e) {}
        }
        return true;
    }
}