<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class Data {
    public static $l;
    public static $conf;
    public static $db;
    public static $user;

    static function load(Conf $conf) {
        static::$l = new Lang();
        static::$conf = $conf;
        static::$l->set($conf->lang);
        static::$db = new Database();
        static::$user = new User();
    }
}