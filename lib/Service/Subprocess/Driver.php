<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Service\Subprocess;

use JKingWeb\Arsse\Arsse;

class Driver implements \JKingWeb\Arsse\Service\Driver {
    protected $queue = [];

    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Service.Subprocess.Name");
    }

    public static function requirementsMet(): bool {
        return function_exists("popen");
    }

    public function __construct() {
    }

    public function queue(int ...$feeds): int {
        $this->queue = array_merge($this->queue, $feeds);
        return sizeof($this->queue);
    }

    public function exec(): int {
        $pp = [];
        while ($this->queue) {
            $id = (int) array_shift($this->queue);
            $php = escapeshellarg(\PHP_BINARY);
            $arsse = escapeshellarg($_SERVER['argv'][0]);
            array_push($pp, $this->execCmd("$php $arsse feed refresh $id"));
        }
        while ($pp) {
            $p = array_pop($pp);
            fgets($p); // TODO: log output
            pclose($p);
        }
        return Arsse::$conf->serviceQueueWidth - sizeof($this->queue);
    }

    /** @codeCoverageIgnore */
    protected function execCmd(string $cmd) {
        return popen($cmd, "r");
    }

    public function clean(): int {
        $out = sizeof($this->queue);
        $this->queue = [];
        return $out;
    }
}
