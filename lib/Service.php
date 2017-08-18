<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use JKingWeb\Arsse\Misc\Date;

class Service {
    
    /** @var Service\Driver */
    protected $drv;
    /** @var \DateInterval */
    protected $interval;

    static public function driverList(): array {
        $sep = \DIRECTORY_SEPARATOR;
        $path = __DIR__.$sep."Service".$sep;
        $classes = [];
        foreach(glob($path."*".$sep."Driver.php") as $file) {
            $name = basename(dirname($file));
            $class = NS_BASE."User\\$name\\Driver";
            $classes[$class] = $class::driverName();
        }
        return $classes;
    }
    
    public static function interval(): \DateInterval {
        try{
            return new \DateInterval(Arsse::$conf->serviceFrequency);
        } catch(\Exception $e) {
            return new \DateInterval("PT2M");
        }
    }

    function __construct() {
        $driver = Arsse::$conf->serviceDriver;
        $this->drv = new $driver();
        $this->interval = static::interval();
    }

    function watch(bool $loop = true): \DateTimeInterface {
        $t = new \DateTime();
        do {
            $this->checkIn();
            static::cleanupPre();
            $list = Arsse::$db->feedListStale();
            if($list) {
                $this->drv->queue(...$list);
                $this->drv->exec();
                $this->drv->clean();
                unset($list);
            }
            static::cleanupPost();
            $t->add($this->interval);
            if($loop) {
                do {
                    @time_sleep_until($t->getTimestamp());
                } while($t->getTimestamp() > time());
            }
        } while($loop);
        return $t;
    }

    function checkIn(): bool {
        return Arsse::$db->metaSet("service_last_checkin", time(), "datetime");
    }

    static function hasCheckedIn(): bool {
        $checkin = Arsse::$db->metaGet("service_last_checkin");
        // if the service has never checked in, return false
        if(!$checkin) {
            return false;
        }
        // convert the check-in timestamp to a DateTime instance
        $checkin = Date::normalize($checkin, "sql");
        // get the checking interval
        $int = static::interval();
        // subtract twice the checking interval from the current time to the earliest acceptable check-in time
        $limit = new \DateTime();
        $limit->sub($int);
        $limit->sub($int);
        // return whether the check-in time is within the acceptable limit
        return ($checkin >= $limit);
    }

    static function cleanupPre(): bool {
        // mark unsubscribed feeds as orphaned and delete orphaned feeds that are beyond their retention period
        return Arsse::$db->feedCleanup();
    }

    static function cleanupPost(): bool {
        // delete old articles, according to configured threasholds
        return Arsse::$db->articleCleanup();
    }
}