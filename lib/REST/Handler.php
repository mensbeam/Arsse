<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\REST;

interface Handler {
    function __construct(\JKingWeb\NewsSync\RuntimeData $data);
	function dispatch(Request $req): Response;
}