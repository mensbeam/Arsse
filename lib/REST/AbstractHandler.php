<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

abstract class AbstractHandler implements Handler {
	abstract function __construct();
	abstract function dispatch(Request $req): Response;
}