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
    protected const CURL_ERROR_MAP = [1 => "invalidUrl",3 => "invalidUrl",5 => "transmissionError","connectionFailed","connectionFailed","transmissionError","forbidden","unauthorized","transmissionError","transmissionError","transmissionError","transmissionError","connectionFailed","connectionFailed","transmissionError","transmissionError","transmissionError","transmissionError","transmissionError","invalidUrl","transmissionError","transmissionError","transmissionError","transmissionError",28 => "timeout","transmissionError","transmissionError","transmissionError","transmissionError","transmissionError",35 => "invalidCertificate","transmissionError","transmissionError","transmissionError","transmissionError",45 => "transmissionError","unauthorized","maxRedirect",52 => "transmissionError","invalidCertificate","invalidCertificate","transmissionError","transmissionError",58 => "invalidCertificate","invalidCertificate","invalidCertificate","transmissionError","invalidUrl","transmissionError","invalidCertificate","transmissionError","invalidCertificate","forbidden","invalidUrl","forbidden","transmissionError",73 => "transmissionError","transmissionError",77 => "invalidCertificate","invalidUrl",90 => "invalidCertificate","invalidCertificate","transmissionError",94 => "unauthorized","transmissionError","connectionFailed"];
    protected const HTTP_ERROR_MAP = [401 => "unauthorized",403 => "forbidden",404 => "invalidUrl",408 => "timeout",410 => "invalidUrl",414 => "invalidUrl",451 => "invalidUrl"];

    public function __construct(string $msgID = "", $vars = null, ?\Throwable $e = null) {
        if ($msgID === "") {
            assert($e !== null, new \Exception("Expecting Picofeed or Guzzle exception when no message specified."));
            if ($e instanceof BadResponseException) {
                $msgID = self::HTTP_ERROR_MAP[$e->getCode()] ?? "transmissionError";
            } elseif ($e instanceof TooManyRedirectsException) {
                $msgID = "maxRedirect";
            } elseif ($e instanceof GuzzleException) {
                $msg = $e->getMessage();
                if (preg_match("/^Error creating resource:/", $msg)) {
                    // PHP stream error; the class of error is ambiguous
                    $msgID = "transmissionError";
                } elseif (preg_match("/^cURL error (\d+):/", $msg, $match)) {
                    $msgID = self::CURL_ERROR_MAP[(int) $match[1]] ?? "internalError";
                } else {
                    // Fallback  for future Guzzle exceptions we may not know about
                    $msgID = "internalError"; // @codeCoverageIgnore
                }
            } elseif ($e instanceof PicoFeedException) {
                $className = get_class($e);
                // Convert the exception thrown by PicoFeed to the one to be thrown here.
                $msgID = preg_replace('/^PicoFeed\\\(?:Client|Parser|Reader)\\\([A-Za-z]+)Exception$/', '$1', $className);
                // If the message ID doesn't change then it's unknown.
                $msgID = ($msgID !== $className) ? lcfirst($msgID) : "internalError";
            } else {
                $msgID = "internalError";
            }
        }
        parent::__construct($msgID, $vars, $e);
    }
}
