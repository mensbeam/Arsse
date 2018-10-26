<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Service\Curl;

use JKingWeb\Arsse\Arsse;

class Driver implements \JKingWeb\Arsse\Service\Driver {
    protected $options = [];
    protected $queue;
    protected $handles = [];

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Service.Curl.Name");
    }

    public static function requirementsMet(): bool {
        return extension_loaded("curl");
    }

    public function __construct() {
        //default curl options for individual requests
        $this->options = [
            \CURLOPT_URL => Arsse::$serviceCurlBase."index.php/apps/news/api/v1-2/feeds/update",
            \CURLOPT_CUSTOMREQUEST => "GET",
            \CURLOPT_FAILONERROR => false,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_FORBID_REUSE => false,
            \CURLOPT_CONNECTTIMEOUT => 20,
            \CURLOPT_DNS_CACHE_TIMEOUT => 360, // FIXME: this should probably be twice the update-check interval so that the DNS cache is always in memory
            \CURLOPT_PROTOCOLS => \CURLPROTO_HTTP | \CURLPROTO_HTTPS,
            \CURLOPT_DEFAULT_PROTOCOL => "https",
            \CURLOPT_USERAGENT => Arsse::$conf->fetchUserAgentString,
            \CURLMOPT_MAX_HOST_CONNECTIONS => Arsse::$conf->serviceQueueWidth,
            \CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            \CURLOPT_HEADER => false,
        ];
        // start an async session
        $this->queue = curl_multi_init();
        // enable pipelining
        curl_multi_setopt($this->queue, \CURLMOPT_PIPELINING, 1);
    }

    public function queue(int ...$feeds): int {
        foreach ($feeds as $id) {
            $h = curl_init();
            curl_setopt($h, \CURLOPT_POSTFIELDS, json_encode(['userId' => "", 'feedId' => $id]));
            $this->handles[] = $h;
            curl_multi_add_handle($this->queue, $h);
        }
        return sizeof($this->handles);
    }

    public function exec(): int {
        $active = 0;
        do {
            curl_multi_exec($this->queue, $active);
            curl_multi_select($this->queue);
        } while ($active > 0);
        return Arsse::$conf->serviceQueueWidth - $active;
    }

    public function clean(): bool {
        foreach ($this->handles as $h) {
            curl_multi_remove_handle($this->queue, $h);
            curl_close($h);
        }
        $this->handles = [];
        return true;
    }
}
