<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Service\Forking;

use JKingWeb\Arsse\Arsse;

class Driver implements \JKingWeb\Arsse\Service\Driver {
    protected $queue = [];
    
    public static function driverName(): string {
        return Arsse::$lang->msg("Driver.Service.Forking.Name");
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
            $php = '"'.\PHP_BINARY.'"';
            $arsse = '"'.$_SERVER['argv'][0].'"';
            array_push($pp, popen("$php $arsse feed refresh $id", "r"));
        }
        while ($pp) {
            $p = array_pop($pp);
            fgets($p); // TODO: log output
            pclose($p);
        }
        return Arsse::$conf->serviceQueueWidth - sizeof($this->queue);
    }

    public function clean(): bool {
        $this->queue = [];
        return true;
    }
}
