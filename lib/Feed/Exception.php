<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Feed;

use GuzzleHttp\Exception\GuzzleException;

class Exception extends \JKingWeb\Arsse\AbstractException {
    public function __construct($url, \Throwable $e) {
        if ($e instanceof GuzzleException) {
            switch ($e->getCode()) {
                case 401:
                    $msgID = "unauthorized";
                    break;
                case 403:
                    $msgID = "forbidden";
                    break;
                case 404:
                case 410:
                    $msgID = "invalidUrl";
                    break;
                case 508:
                    $msgID = "tooManyRedirects";
                    break;
                default:
                    $c = $e->getCode();
                    if ($c >= 400 && $c < 600) {
                        $msgID = "transmissionError";
                    }
            }
        }
        if (!($msgID ?? "")) {
            $className = get_class($e);
            // Convert the exception thrown by PicoFeed to the one to be thrown here.
            $msgID = preg_replace('/^(?:PicoFeed\\\(?:Client|Parser|Reader)|GuzzleHttp\\\Exception)\\\([A-Za-z]+)Exception$/', '$1', $className);
            // If the message ID doesn't change then it's unknown.
            $msgID = ($msgID !== $className) ? lcfirst($msgID) : '';
        }
        parent::__construct($msgID, ['url' => $url], $e);
    }
}
