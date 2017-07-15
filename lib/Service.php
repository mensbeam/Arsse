<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class Service {
    
    /**
    * @var Service\Driver
    */
    protected $drv;
    /**
    * @var \DateInterval
    */
    protected $interval;
    
    function __construct() {
        $driver = Data::$conf->serviceDriver;
        $this->drv = new $driver();
        $this->interval = new \DateInterval(Data::$conf->serviceFrequency); // FIXME: this needs to fall back in case of incorrect input
    }

    function watch() {
        while(true) {
            $t = new \DateTime();
            $list = Data::$db->feedListStale();
            if($list) {
                echo date("H:i:s")." Updating feeds ".json_encode($list)."\n";
                // TODO: pre-cleanup
                $this->drv->queue(...$list);
                $this->drv->exec();
                $this->drv->clean();
                // TODO: post-cleanup
            } else {
                echo date("H:i:s")." No feeds to update; sleeping\n";
            }
            $t->add($this->interval);
            do {
                @time_sleep_until($t->getTimestamp());
            } while($t->getTimestamp() > time());
        }
    }
}