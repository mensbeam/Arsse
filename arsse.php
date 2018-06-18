<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

namespace JKingWeb\Arsse;

const BASE = __DIR__.DIRECTORY_SEPARATOR;
const NS_BASE = __NAMESPACE__."\\";

require_once BASE."vendor".DIRECTORY_SEPARATOR."autoload.php";
ignore_user_abort(true);
ini_set("memory_limit", "-1");
ini_set("max_execution_time", "0");


if (\PHP_SAPI=="cli") {
    // initialize the CLI; this automatically handles --help and --version
    $cli = new CLI;
    // handle other CLI requests; some do not require configuration
    $cli->dispatch();
} else {
    // load configuration
    $conf = file_exists(BASE."config.php") ? new Conf(BASE."config.php") : new Conf;
    Arsse::load($conf);
    // handle Web requests
    $emitter = new \Zend\Diactoros\Response\SapiEmitter();
    $response = (new REST)->dispatch();
    $emitter->emit($response);
}
