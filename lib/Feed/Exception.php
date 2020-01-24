<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Feed;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use PicoFeed\PicoFeedException;

class Exception extends \JKingWeb\Arsse\AbstractException {
    public function __construct($url, \Throwable $e) {
        if ($e instanceof BadResponseException) {
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
                    $msgID = "transmissionError";
            }
        } elseif ($e instanceof TooManyRedirectsException) {
            $msgID = "maxRedirect";
        } elseif ($e instanceof GuzzleException) {
            $m = $e->getMessage();
            if (preg_match("/^Error creating resource:/", $m)) {
                // PHP stream error; the class of error is ambiguous
                $msgID = "transmissionError"; // @codeCoverageIgnore
            } elseif (preg_match("/^cURL error 35:/", $m)) {
                $msgID = "invalidCertificate";
            } elseif (preg_match("/^cURL error 28:/", $m)) {
                $msgID = "timeout";
            } else {
                var_export($m);
                exit;
            }
        } elseif ($e instanceof PicoFeedException) {
            $className = get_class($e);
            // Convert the exception thrown by PicoFeed to the one to be thrown here.
            $msgID = preg_replace('/^PicoFeed\\\(?:Client|Parser|Reader)\\\([A-Za-z]+)Exception$/', '$1', $className);
            // If the message ID doesn't change then it's unknown.
            $msgID = ($msgID !== $className) ? lcfirst($msgID) : '';
        } else {
            $msgID = get_class($e);
        }
        parent::__construct($msgID, ['url' => $url], $e);
    }
}
