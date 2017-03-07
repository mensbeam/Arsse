<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\User\Internal;
use JKingWeb\NewsSync\Lang;
use JKingWeb\NewsSync\User\Driver as Iface;

final class Driver implements Iface {
    use InternalFunctions;

    protected $data;
    protected $db;
    protected $functions = [
        "auth"                    => Iface::FUNC_INTERNAL,
        "userList"                => Iface::FUNC_INTERNAL,
        "userExists"              => Iface::FUNC_INTERNAL,
        "userAdd"                 => Iface::FUNC_INTERNAL,
        "userRemove"              => Iface::FUNC_INTERNAL,
        "userPasswordSet"         => Iface::FUNC_INTERNAL,
        "userPropertiesGet"       => Iface::FUNC_INTERNAL,
        "userPropertiesSet"       => Iface::FUNC_INTERNAL,
        "userRightsGet"           => Iface::FUNC_INTERNAL,
        "userRightsSet"           => Iface::FUNC_INTERNAL,
    ];

    static public function create(\JKingWeb\NewsSync\RuntimeData $data): Driver {
        return new static($data);
    }

    static public function driverName(): string {
        return Lang::msg("Driver.User.Internal.Name");
    }

    public function driverFunctions(string $function = null) {
        if($function===null) return $this->functions;
        if(array_key_exists($function, $this->functions)) {
            return $this->functions[$function];
        } else {
            return Iface::FUNC_NOT_IMPLEMENTED;
        }
    }

    // see InternalFunctions.php for bulk of methods
}