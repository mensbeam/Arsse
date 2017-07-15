<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Service\Internal;
use JKingWeb\Arsse\Data;

class Driver implements \JKingWeb\Arsse\Service\Driver {
    protected $queue = [];
    
    static function driverName(): string {
        return Data::$lang->msg("Driver.Service.Internal.Name");
    }

    static function requirementsMet(): bool {
        // this driver has no requirements
        return true;
    }
    
    function __construct() {
    }

    function queue(int ...$feeds): int {
        $this->queue = array_merge($this->queue, $feeds);
        return sizeof($this->queue);
    }

    function exec(): int {
        while(sizeof($this->queue)) {
            $id = array_shift($this->queue);
            Data::$db->feedUpdate($id);
        }
        return Data::$conf->serviceQueueWidth - sizeof($this->queue);
    }

    function clean(): bool {
        $this->queue = [];
        return true;
    }
}