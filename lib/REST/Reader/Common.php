<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\ResponseInterface;

trait Common {
    protected function challenge(ResponseInterface $res): ResponseInterface {
        return $res->withAddedHeader("WWW-Authenticate", "GoogleLogin");
    }

    public static function respError($message, int $status = 400, array $headers = []): ResponseInterface {
        if ($message instanceof \Exception) {
            $message = $message->getMessage();
        } else {
            $message = (array) $message;
            assert(isset(Arsse::$lang) && Arsse::$lang instanceof \JKingWeb\Arsse\Lang, new \Exception("Language database must be initialized before use"));
            $message = Arsse::$lang->msg("API.Reader.Error.".array_shift($message), $message);
        }
        return HTTP::respText($message, $status, $headers);
    }
}