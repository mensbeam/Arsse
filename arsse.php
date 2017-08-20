<?php
namespace JKingWeb\Arsse;
require_once __DIR__.DIRECTORY_SEPARATOR."bootstrap.php";

if(\PHP_SAPI=="cli") {
    // initialize the CLI; this automatically handles --help and --version
    $cli = new CLI;
    // handle other CLI requests; some do not require configuration
    $cli->dispatch();
} else {
    // load configuration
    Arsse::load(new Conf());
    if(file_exists(BASE."config.php")) {
        Arsse::$conf->importFile(BASE."config.php");
    }
    // handle Web requests
    (new REST)->dispatch()->output();
}