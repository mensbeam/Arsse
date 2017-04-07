<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

interface Handler {
    function __construct();
    function dispatch(Request $req): Response;
}