<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\Miniflux;

use JKingWeb\Arsse\Arsse;

class ErrorResponse extends \Laminas\Diactoros\Response\JsonResponse {
    public function __construct($data, int $status = 400, array $headers = [], int $encodingOptions = self::DEFAULT_JSON_FLAGS) {
        assert(isset(Arsse::$lang) && Arsse::$lang instanceof \JKingWeb\Arsse\Lang, new \Exception("Language database must be initialized before use"));
        $data = (array) $data;
        $msg = array_shift($data);
        $data = ["error_message" => Arsse::$lang->msg("API.Miniflux.Error.".$msg, $data)];
        parent::__construct($data, $status, $headers, $encodingOptions);
    }
}
