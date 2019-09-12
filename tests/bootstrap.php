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
error_reporting(\E_ALL);
require_once BASE."vendor".DIRECTORY_SEPARATOR."autoload.php";
