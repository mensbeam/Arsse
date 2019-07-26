<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\Date;

class Service {
    const DRIVER_NAMES = [
        'serial'     => \JKingWeb\Arsse\Service\Serial\Driver::class,
        'subprocess' => \JKingWeb\Arsse\Service\Subprocess\Driver::class,
        'curl'       => \JKingWeb\Arsse\Service\Curl\Driver::class,
    ];

    /** @var Service\Driver */
    protected $drv;
    /** @var \DateInterval */
    protected $interval;

    public static function driverList(): array {
        $sep = \DIRECTORY_SEPARATOR;
        $path = __DIR__.$sep."Service".$sep;
        $classes = [];
        foreach (glob($path."*".$sep."Driver.php") as $file) {
            $name = basename(dirname($file));
            $class = NS_BASE."User\\$name\\Driver";
            $classes[$class] = $class::driverName();
        }
        return $classes;
    }

    public function __construct() {
        $driver = Arsse::$conf->serviceDriver;
        $this->drv = new $driver();
        $this->interval = Arsse::$conf->serviceFrequency;
    }

    public function watch(bool $loop = true): \DateTimeInterface {
        $t = new \DateTime();
        do {
            $this->checkIn();
            static::cleanupPre();
            $list = Arsse::$db->feedListStale();
            if ($list) {
                $this->drv->queue(...$list);
                $this->drv->exec();
                $this->drv->clean();
                unset($list);
            }
            static::cleanupPost();
            $t->add($this->interval);
            if ($loop) {
                do {
                    @time_sleep_until($t->getTimestamp());
                } while ($t->getTimestamp() > time());
            }
        } while ($loop);
        return $t;
    }

    public function checkIn(): bool {
        return Arsse::$db->metaSet("service_last_checkin", time(), "datetime");
    }

    public static function hasCheckedIn(): bool {
        $checkin = Arsse::$db->metaGet("service_last_checkin");
        // if the service has never checked in, return false
        if (!$checkin) {
            return false;
        }
        // convert the check-in timestamp to a DateTime instance
        $checkin = Date::normalize($checkin, "sql");
        // get the checking interval
        $int = Arsse::$conf->serviceFrequency;
        // subtract twice the checking interval from the current time to yield the earliest acceptable check-in time
        $limit = new \DateTime();
        $limit->sub($int);
        $limit->sub($int);
        // return whether the check-in time is within the acceptable limit
        return ($checkin >= $limit);
    }

    public static function cleanupPre(): bool {
        // mark unsubscribed feeds as orphaned and delete orphaned feeds that are beyond their retention period
        Arsse::$db->feedCleanup();
        // delete expired log-in sessions
        Arsse::$db->sessionCleanup();
        return true;
    }

    public static function cleanupPost(): bool {
        // delete old articles, according to configured thresholds
        $deleted = Arsse::$db->articleCleanup();
        // if any articles were deleted, perform database maintenance
        if ($deleted) {
            Arsse::$db->driverMaintenance();
        }
        return true;
    }
}
