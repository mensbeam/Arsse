<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

interface Result extends \Iterator {
    function current();
    function key();
    function next();
    function rewind();
    function valid();

    function get();
    function getAll();
    function getValue();
    
    function changes();
    function lastId();
}