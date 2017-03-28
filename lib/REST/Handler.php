<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

interface Handler {
    function __construct(\JKingWeb\Arsse\RuntimeData $data);
	function dispatch(Request $req): Response;
}