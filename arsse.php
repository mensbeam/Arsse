<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

const BASE = __DIR__.DIRECTORY_SEPARATOR;
const NS_BASE = __NAMESPACE__."\\";

require_once BASE."vendor".DIRECTORY_SEPARATOR."autoload.php";
ignore_user_abort(true);
ini_set("memory_limit", "-1");
ini_set("max_execution_time", "0");
// NOTE: While running in the wild we don't want to spew deprecation warnings if users are ahead of us in PHP versions
error_reporting(\E_ALL & ~\E_DEPRECATED);

if (\PHP_SAPI === "cli") {
    // initialize the CLI; this automatically handles --help and --version else
    Arsse::$obj = new Factory;
    $cli = new CLI;
    // handle other CLI requests; some do not require configuration
    $exitStatus = $cli->dispatch();
    exit($exitStatus);
} else {
    // load configuration
    Arsse::bootstrap();
    // handle Web requests
    $emitter = new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
    $response = (new REST)->dispatch();
    $emitter->emit($response);
}
