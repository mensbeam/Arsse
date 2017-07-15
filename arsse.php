<?php
namespace JKingWeb\Arsse;
require_once __DIR__.DIRECTORY_SEPARATOR."bootstrap.php";
Data::load(new Conf());

if(\PHP_SAPI=="cli") {
    (new Service)->watch();
} else {
    (new REST)->dispatch();
}