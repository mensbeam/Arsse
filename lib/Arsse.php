<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse;

class Arsse {
    public const VERSION = "0.9.1";

    /** @var Factory */
    public static $obj;
    /** @var Lang */
    public static $lang;
    /** @var Conf  */
    public static $conf;
    /** @var Database */
    public static $db;
    /** @var User */
    public static $user;

    public static function load(Conf $conf): void {
        static::$obj = static::$obj ?? new Factory;
        static::$lang = static::$lang ?? new Lang;
        static::$conf = $conf;
        static::$lang->set($conf->lang);
        static::$db = static::$db ?? new Database;
        static::$user = static::$user ?? new User;
    }
}
