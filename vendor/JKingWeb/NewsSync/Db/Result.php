<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

interface Result {
	function __invoke(); // alias of get()
	function get();
	function getSingle();
}