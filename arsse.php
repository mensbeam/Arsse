<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

namespace JKingWeb\Arsse;

const BASE = __DIR__.DIRECTORY_SEPARATOR;
const NS_BASE = __NAMESPACE__."\\";

require_once BASE."vendor".DIRECTORY_SEPARATOR."autoload.php";
ignore_user_abort(true);


if (\PHP_SAPI=="cli") {
    // initialize the CLI; this automatically handles --help and --version
    $cli = new CLI;
    // handle other CLI requests; some do not require configuration
    $cli->dispatch();
} else {
    // load configuration
    Arsse::load(new Conf());
    if (file_exists(BASE."config.php")) {
        Arsse::$conf->importFile(BASE."config.php");
    }
    // handle Web requests
    (new REST)->dispatch()->output();
}
