<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Auth;

Interface Driver {
	public function __construct($conf, $db);
	public function auth(): bool;
	public function authHTTP(): bool;
	public function isAdmin(): bool;
}