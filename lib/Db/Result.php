<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

interface Result extends \Iterator {
    function current();
    function key();
    function next();
    function rewind();
    function valid();

    function getRow();
    function getAll(): array;
    function getValue();
    
    function changes();
    function lastId();
}