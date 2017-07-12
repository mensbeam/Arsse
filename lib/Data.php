<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class Data {
    public static $lang;
    public static $conf;
    public static $db;
    public static $user;

    static function load(Conf $conf) {
        static::$lang = new Lang();
        static::$conf = $conf;
        static::$lang->set($conf->lang);
        static::$db = new Database();
        static::$user = new User();
    }
}