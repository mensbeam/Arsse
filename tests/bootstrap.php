<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

const NS_BASE = __NAMESPACE__."\\";
define(NS_BASE."BASE", dirname(__DIR__).DIRECTORY_SEPARATOR);
const DOCROOT = BASE."tests".DIRECTORY_SEPARATOR."docroot".DIRECTORY_SEPARATOR;
ini_set("memory_limit", "-1");
ini_set("zend.assertions", "1");
ini_set("assert.exception", "true");
// FIXME: Workaround for a bug in PCRE2 10.37
ini_set("pcre.jit", "0");
// FIXME: This is required by a dependency of Picofeed
error_reporting(\E_ALL & ~\E_DEPRECATED);
require_once BASE."vendor".DIRECTORY_SEPARATOR."autoload.php";

if (function_exists("xdebug_set_filter")) {
    if (defined("XDEBUG_PATH_INCLUDE")) {
        xdebug_set_filter(\XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [BASE."lib/"]);
    } else {
        xdebug_set_filter(\XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_WHITELIST, [BASE."lib/"]);
    }
}
