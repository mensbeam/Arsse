<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

interface Handler {
    public function __construct();
    public function dispatch(Request $req): Response;
}
