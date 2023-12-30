<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Service\Serial;

use JKingWeb\Arsse\Arsse;

class Driver implements \JKingWeb\Arsse\Service\Driver {
    protected $queue = [];

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Service.Serial.Name");
    }

    public static function requirementsMet(): bool {
        // this driver has no requirements
        return true;
    }

    public function __construct() {
    }

    public function queue(int ...$feeds): int {
        $this->queue = array_merge($this->queue, $feeds);
        return sizeof($this->queue);
    }

    public function exec(): int {
        while (sizeof($this->queue)) {
            $id = array_shift($this->queue);
            Arsse::$db->subscriptionUpdate(null, $id);
        }
        return Arsse::$conf->serviceQueueWidth - sizeof($this->queue);
    }

    public function clean(): int {
        $out = sizeof($this->queue);
        $this->queue = [];
        return $out;
    }
}
