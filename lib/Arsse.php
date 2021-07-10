<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

class Arsse {
    public const VERSION = "0.10.0";
    public const REQUIRED_EXTENSIONS = [
        "intl",      // as this extension is required to prepare formatted messages, its absence will throw a distinct English-only exception
        "dom",
        "filter",
        "json",      // part of the PHP core since version 8.0
        "hash",      // part of the PHP core since version 7.4
        "simplexml", // required by PicoFeed only
        "iconv",     // required by PicoFeed only
    ];

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

    /** @codeCoverageIgnore */
    public static function bootstrap(): void {
        $conf = file_exists(BASE."config.php") ? new Conf(BASE."config.php") : new Conf;
        static::load($conf);
    }

    public static function load(Conf $conf): void {
        static::$obj = static::$obj ?? new Factory;
        static::$lang = static::$lang ?? new Lang;
        static::$conf = $conf;
        static::$lang->set($conf->lang);
        static::$db = static::$db ?? new Database;
        static::$user = static::$user ?? new User;
    }

    /** Checks whether the specified extensions are loaded and throws an exception if any are not */
    public static function checkExtensions(string ...$ext): void {
        $missing = [];
        foreach ($ext as $e) {
            if (!extension_loaded($e)) {
                $missing[] = $e;
            }
        }
        if ($missing) {
            $total = sizeof($missing);
            $first = $missing[0];
            throw new Exception("extMissing", ['first' => $first, 'total' => $total]);
        }
    }
}
