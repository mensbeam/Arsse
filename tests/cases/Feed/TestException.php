<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Feed;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Exception\TransferException;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use PicoFeed\PicoFeedException;

/**
 * @covers \JKingWeb\Arsse\Feed\Exception
 * @group slow */
class TestException extends \JKingWeb\Arsse\Test\AbstractTest {
    /** @dataProvider provideCurlErrors */
    public function testHandleCurlErrors(int $code, string $message): void {
        $e = $this->mockGuzzleException(TransferException::class, "cURL error $code: Some message", 0);
        $this->assertException($message, "Feed");
        throw new FeedException("", ['url' => "https://example.com/"], $e);
    }

    public function provideCurlErrors() {
        return [
            'CURLE_UNSUPPORTED_PROTOCOL'        => [1,  "invalidUrl"],
            'CURLE_FAILED_INIT'                 => [2,  "internalError"],
            'CURLE_URL_MALFORMAT'               => [3,  "invalidUrl"],
            'CURLE_URL_MALFORMAT_USER'          => [4,  "internalError"],
            'CURLE_COULDNT_RESOLVE_PROXY'       => [5,  "transmissionError"],
            'CURLE_COULDNT_RESOLVE_HOST'        => [6,  "connectionFailed"],
            'CURLE_COULDNT_CONNECT'             => [7,  "connectionFailed"],
            'CURLE_WEIRD_SERVER_REPLY'          => [8,  "transmissionError"],
            'CURLE_FTP_ACCESS_DENIED'           => [9,  "forbidden"],
            'CURLE_FTP_USER_PASSWORD_INCORRECT' => [10, "unauthorized"],
            'CURLE_FTP_WEIRD_PASS_REPLY'        => [11, "transmissionError"],
            'CURLE_FTP_WEIRD_USER_REPLY'        => [12, "transmissionError"],
            'CURLE_FTP_WEIRD_PASV_REPLY'        => [13, "transmissionError"],
            'CURLE_FTP_WEIRD_227_FORMAT'        => [14, "transmissionError"],
            'CURLE_FTP_CANT_GET_HOST'           => [15, "connectionFailed"],
            'CURLE_FTP_CANT_RECONNECT'          => [16, "connectionFailed"],
            'CURLE_FTP_COULDNT_SET_BINARY'      => [17, "transmissionError"],
            'CURLE_PARTIAL_FILE'                => [18, "transmissionError"],
            'CURLE_FTP_COULDNT_RETR_FILE'       => [19, "transmissionError"],
            'CURLE_FTP_WRITE_ERROR'             => [20, "transmissionError"],
            'CURLE_FTP_QUOTE_ERROR'             => [21, "transmissionError"],
            'CURLE_HTTP_NOT_FOUND'              => [22, "invalidUrl"],
            'CURLE_WRITE_ERROR'                 => [23, "transmissionError"],
            'CURLE_MALFORMAT_USER'              => [24, "transmissionError"],
            'CURLE_FTP_COULDNT_STOR_FILE'       => [25, "transmissionError"],
            'CURLE_READ_ERROR'                  => [26, "transmissionError"],
            'CURLE_OUT_OF_MEMORY'               => [27, "internalError"],
            'CURLE_OPERATION_TIMEDOUT'          => [28, "timeout"],
            'CURLE_FTP_COULDNT_SET_ASCII'       => [29, "transmissionError"],
            'CURLE_FTP_PORT_FAILED'             => [30, "transmissionError"],
            'CURLE_FTP_COULDNT_USE_REST'        => [31, "transmissionError"],
            'CURLE_FTP_COULDNT_GET_SIZE'        => [32, "transmissionError"],
            'CURLE_HTTP_RANGE_ERROR'            => [33, "transmissionError"],
            'CURLE_HTTP_POST_ERROR'             => [34, "internalError"],
            'CURLE_SSL_CONNECT_ERROR'           => [35, "invalidCertificate"],
            'CURLE_BAD_DOWNLOAD_RESUME'         => [36, "transmissionError"],
            'CURLE_FILE_COULDNT_READ_FILE'      => [37, "transmissionError"],
            'CURLE_LDAP_CANNOT_BIND'            => [38, "transmissionError"],
            'CURLE_LDAP_SEARCH_FAILED'          => [39, "transmissionError"],
            'CURLE_LIBRARY_NOT_FOUND'           => [40, "internalError"],
            'CURLE_FUNCTION_NOT_FOUND'          => [41, "internalError"],
            'CURLE_ABORTED_BY_CALLBACK'         => [42, "internalError"],
            'CURLE_BAD_FUNCTION_ARGUMENT'       => [43, "internalError"],
            'CURLE_BAD_CALLING_ORDER'           => [44, "internalError"],
            'CURLE_HTTP_PORT_FAILED'            => [45, "transmissionError"],
            'CURLE_BAD_PASSWORD_ENTERED'        => [46, "unauthorized"],
            'CURLE_TOO_MANY_REDIRECTS'          => [47, "maxRedirect"],
            'CURLE_UNKNOWN_TELNET_OPTION'       => [48, "internalError"],
            'CURLE_TELNET_OPTION_SYNTAX'        => [49, "internalError"],
            'Unknown error 50'                  => [50, "internalError"],
            'Unknown error 51'                  => [51, "internalError"],
            'CURLE_GOT_NOTHING'                 => [52, "transmissionError"],
            'CURLE_SSL_ENGINE_NOTFOUND'         => [53, "invalidCertificate"],
            'CURLE_SSL_ENGINE_SETFAILED'        => [54, "invalidCertificate"],
            'CURLE_SEND_ERROR'                  => [55, "transmissionError"],
            'CURLE_RECV_ERROR'                  => [56, "transmissionError"],
            'CURLE_SHARE_IN_USE'                => [57, "internalError"],
            'CURLE_SSL_CERTPROBLEM'             => [58, "invalidCertificate"],
            'CURLE_SSL_CIPHER'                  => [59, "invalidCertificate"],
            'CURLE_SSL_CACERT'                  => [60, "invalidCertificate"],
            'CURLE_BAD_CONTENT_ENCODING'        => [61, "transmissionError"],
            'CURLE_LDAP_INVALID_URL'            => [62, "invalidUrl"],
            'CURLE_FILESIZE_EXCEEDED'           => [63, "transmissionError"],
            'CURLE_USE_SSL_FAILED'              => [64, "invalidCertificate"],
            'CURLE_SEND_FAIL_REWIND'            => [65, "transmissionError"],
            'CURLE_SSL_ENGINE_INITFAILED'       => [66, "invalidCertificate"],
            'CURLE_LOGIN_DENIED'                => [67, "forbidden"],
            'CURLE_TFTP_NOTFOUND'               => [68, "invalidUrl"],
            'CURLE_TFTP_PERM'                   => [69, "forbidden"],
            'CURLE_REMOTE_DISK_FULL'            => [70, "transmissionError"],
            'CURLE_TFTP_ILLEGAL'                => [71, "internalError"],
            'CURLE_TFTP_UNKNOWNID'              => [72, "internalError"],
            'CURLE_REMOTE_FILE_EXISTS'          => [73, "transmissionError"],
            'CURLE_TFTP_NOSUCHUSER'             => [74, "transmissionError"],
            'CURLE_CONV_FAILED'                 => [75, "internalError"],
            'CURLE_CONV_REQD'                   => [76, "internalError"],
            'CURLE_SSL_CACERT_BADFILE'          => [77, "invalidCertificate"],
            'CURLE_REMOTE_FILE_NOT_FOUND'       => [78, "invalidUrl"],
            'CURLE_SSH'                         => [79, "internalError"],
            'CURLE_SSL_PINNEDPUBKEYNOTMATCH'    => [90, "invalidCertificate"],
            'CURLE_SSL_INVALIDCERTSTATUS'       => [91, "invalidCertificate"],
            'CURLE_HTTP2_STREAM'                => [92, "transmissionError"],
            'CURLE_RECURSIVE_API_CALL'          => [93, "internalError"],
            'CURLE_AUTH_ERROR'                  => [94, "unauthorized"],
            'CURLE_HTTP3'                       => [95, "transmissionError"],
            'CURLE_QUIC_CONNECT_ERROR'          => [96, "connectionFailed"],
            'Hypothetical error 2112'           => [2112, "internalError"],
        ];
    }

    /** @dataProvider provideHTTPErrors */
    public function testHandleHttpErrors(int $code, string $message): void {
        $e = $this->mockGuzzleException(BadResponseException::class, "Irrelevant message", $code);
        $this->assertException($message, "Feed");
        throw new FeedException("", ['url' => "https://example.com/"], $e);
    }

    public function provideHTTPErrors() {
        $specials = [
            401 => "unauthorized",
            403 => "forbidden",
            404 => "invalidUrl",
            408 => "timeout",
            410 => "invalidUrl",
            414 => "invalidUrl",
            451 => "invalidUrl",
        ];
        $out = array_fill(400, (600 - 400), "transmissionError");
        foreach ($specials as $k => $t) {
            $out[$k] = $t;
        }
        foreach ($out as $k => $t) {
            $out[$k] = [$k, $t];
        }
        return $out;
    }

    /** @dataProvider providePicoFeedException */
    public function testHandlePicofeedException(PicoFeedException $e, string $message) {
        $this->assertException($message, "Feed");
        throw new FeedException("", ['url' => "https://example.com/"], $e);
    }

    public function providePicoFeedException() {
        return [
            'Failed feed discovery' => [new \PicoFeed\Reader\SubscriptionNotFoundException,  "subscriptionNotFound"],
            'Unsupported format'    => [new \PicoFeed\Reader\UnsupportedFeedFormatException, "unsupportedFeedFormat"],
            'Malformed XML'         => [new \PicoFeed\Parser\MalformedXmlException,          "malformedXml"],
            'XML entity expansion'  => [new \PicoFeed\Parser\XmlEntityException,             "xmlEntity"],
        ];
    }

    public function testHandleExcessRedirections() {
        $e = $this->mockGuzzleException(TooManyRedirectsException::class, "Irrelevant message", 404);
        $this->assertException("maxRedirect", "Feed");
        throw new FeedException("", ['url' => "https://example.com/"], $e);
    }

    public function testHandleGenericStreamErrors() {
        $e = $this->mockGuzzleException(TransferException::class, "Error creating resource: Irrelevant message", 403);
        $this->assertException("transmissionError", "Feed");
        throw new FeedException("", ['url' => "https://example.com/"], $e);
    }

    public function testHandleUnexpectedError() {
        $e = new \Exception;
        $this->assertException("internalError", "Feed");
        throw new FeedException("", ['url' => "https://example.com/"], $e);
    }
}
