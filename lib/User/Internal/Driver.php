<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\User\Internal;

final class Driver implements \JKingWeb\Arsse\User\Driver {
    use InternalFunctions;

    protected $db;
    protected $functions = [
        "auth"                    => self::FUNC_INTERNAL,
        "userList"                => self::FUNC_INTERNAL,
        "userExists"              => self::FUNC_INTERNAL,
        "userAdd"                 => self::FUNC_INTERNAL,
        "userRemove"              => self::FUNC_INTERNAL,
        "userPasswordSet"         => self::FUNC_INTERNAL,
    ];

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.User.Internal.Name");
    }

    public function driverFunctions(string $function = null) {
        if ($function===null) {
            return $this->functions;
        }
        if (array_key_exists($function, $this->functions)) {
            return $this->functions[$function];
        } else {
            return self::FUNC_NOT_IMPLEMENTED;
        }
    }

    // see InternalFunctions.php for bulk of methods
}
