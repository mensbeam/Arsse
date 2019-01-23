<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

class Arsse {
    const VERSION = "0.6.1";

    /** @var Lang */
    public static $lang;
    /** @var Conf  */
    public static $conf;
    /** @var Database */
    public static $db;
    /** @var User */
    public static $user;

    public static function load(Conf $conf) {
        static::$lang = static::$lang ?? new Lang;
        static::$conf = $conf;
        static::$lang->set($conf->lang);
        static::$db = static::$db ?? new Database;
        static::$user = static::$user ?? new User;
    }
}
