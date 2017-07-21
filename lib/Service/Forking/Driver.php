<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Service\Forking;
use JKingWeb\Arsse\Arsse;

class Driver implements \JKingWeb\Arsse\Service\Driver {
    protected $queue = [];
    
    static function driverName(): string {
        return Arsse::$lang->msg("Driver.Service.Forking.Name");
    }

    static function requirementsMet(): bool {
        return function_exists("popen");
    }
    
    function __construct() {
    }

    function queue(int ...$feeds): int {
        $this->queue = array_merge($this->queue, $feeds);
        return sizeof($this->queue);
    }

    function exec(): int {
        $pp = [];
        while($this->queue) {
            $id = (int) array_shift($this->queue);
            array_push($pp, popen('"'.\PHP_BINARY.'" "'.$_SERVER['argv'][0].'" feed refresh '.$id, "r"));
        }
        while($pp) {
            $p = array_pop($pp);
            fgets($p); // TODO: log output
            pclose($p);
        }
        return Arsse::$conf->serviceQueueWidth - sizeof($this->queue);
    }

    function clean(): bool {
        $this->queue = [];
        return true;
    }
}