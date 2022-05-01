<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\FreshRSS;

use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class API extends \JKingWeb\Arsse\REST\AbstractHandler {
    protected const CALLS = [
        '/accounts/ClientLogin' => [
            'GET'     => [],
            'POST'    => [],
        ],
    ];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        return new Response();
    }
}