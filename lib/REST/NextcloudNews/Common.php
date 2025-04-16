<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\HTTP;
use Psr\Http\Message\ResponseInterface;

trait Common {
    protected static function error(int $code, $message, array $headers = []): ResponseInterface {
        if ($message instanceof \Exception) {
            $message = $message->getMessage();
        } else {
            $message = (array) $message;
            $message = Arsse::$lang->msg("API.NCNv1.Error.".array_shift($message), $message);
        }
        return HTTP::respJson(['message' => $message], $code, $headers);
    }
}