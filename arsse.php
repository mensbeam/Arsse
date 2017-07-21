<?php
namespace JKingWeb\Arsse;
var_export(get_defined_constants());
exit;
require_once __DIR__.DIRECTORY_SEPARATOR."bootstrap.php";

if(\PHP_SAPI=="cli") {
    // initialize the CLI; this automatically handles --help and --version
    $cli = new CLI;
    // load configuration
    Arsse::load(new Conf());
    // handle CLI requests
    $cli->dispatch();
} else {
    // load configuration
    Arsse::load(new Conf());
    // handle Web requests
    (new REST)->dispatch()->output();
}