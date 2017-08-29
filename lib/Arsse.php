<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class Arsse {
    /** @var Lang */
    public static $lang;
    /** @var Conf  */
    public static $conf;
    /** @var Database */
    public static $db;
    /** @var User */
    public static $user;

    public static function load(Conf $conf) {
        static::$lang = new Lang();
        static::$conf = $conf;
        static::$lang->set($conf->lang);
        static::$db = new Database();
        static::$user = new User();
    }
}
