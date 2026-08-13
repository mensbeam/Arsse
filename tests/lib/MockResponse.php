<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Test;

use donatj\MockWebServer\RequestInfo;
use donatj\MockWebServer\ResponseInterface;

class MockResponse implements ResponseInterface {
    protected const DOCROOT = __DIR__."/../docroot";
    protected $status;
    protected $headers;
    protected $body;

    public function getRef(): string {
        return "019ffcbf-51b47924a0afd0833baf10d4";
    }

    public function getBody(RequestInfo $r): string {
        if (!isset($this->body)) {
            $this->makeResponse($r);
        }
        return $this->body;
    }

    public function getStatus(RequestInfo $r): int {
        if (!isset($this->status)) {
            $this->makeResponse($r);
        }
        return $this->status;
    }

    public function getHeaders(RequestInfo $r): array {
        if (!isset($this->headers)) {
            $this->makeResponse($r);
        }
        return $this->headers;
    }

    public function makeResponse(RequestInfo $r): void {
        // gather the data for the response from our test fixtures
        $url = $r->getParsedUri()['path'];
        if ($url === "/") {
            $url = "/index";
        }
        $test = self::DOCROOT.$url.".php";
        if (!file_exists($test)) {
            $details = [
                'code'    => 499,
                'content' => "Test '$test' missing.",
                'mime'    => "application/octet-stream",
                'lastMod' => time(),
                'cache'   => true,
                'fields'  => [],
            ];
        } else {
            $details = array_merge([ // default values for response
                'code'    => 200,
                'content' => "",
                'mime'    => "application/octet-stream",
                'lastMod' => time(),
                'cache'   => true,
                'fields'  => [],
            ], (include $test));
        }
        // prepare the output
        $this->status = $details['code'];
        $this->body = (string) $details['content'];
        $h = [];
        if (strlen($this->body)) {
            $h[] = "Content-Type: ".$details['mime'];
            if ($details['cache']) {
                $h[] = 'ETag: "'.md5($this->body).'"';
            }
        }
        if ($details['cache']) {
            $h[] = "Last-Modified: ".gmdate("D, d M Y H:i:s \G\M\T", $details['lastMod']);
        }
        $this->headers = array_merge($h, $details['fields']);
    }
}
