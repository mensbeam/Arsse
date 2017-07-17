<?php
namespace JKingWeb\Arsse;
require_once __DIR__.DIRECTORY_SEPARATOR."bootstrap.php";
Arsse::load(new Conf());

if(\PHP_SAPI=="cli") {
    (new Service)->watch();
} else {
    (new REST)->dispatch();
}