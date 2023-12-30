<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\Date;

class Service {
    public const DRIVER_NAMES = [
        'serial'     => \JKingWeb\Arsse\Service\Serial\Driver::class,
        'subprocess' => \JKingWeb\Arsse\Service\Subprocess\Driver::class,
    ];

    /** @var Service\Driver */
    protected $drv;
    protected $loop = false;
    protected $reload = false;

    public function __construct() {
        $driver = Arsse::$conf->serviceDriver;
        $this->drv = new $driver;
    }

    public function watch(bool $loop = true): \DateTimeInterface {
        $this->loop = $loop;
        $this->signalInit();
        $t = new \DateTime;
        do {
            $this->checkIn();
            static::cleanupPre();
            $list = Arsse::$db->feedListStale();
            if ($list) {
                $this->drv->queue(...$list);
                unset($list);
                $this->drv->exec();
                $this->drv->clean();
            }
            static::cleanupPost();
            $t->add(Arsse::$conf->serviceFrequency);
            // @codeCoverageIgnoreStart
            if ($this->loop) {
                do {
                    sleep((int) max(0, $t->getTimestamp() - time()));
                    if (function_exists("pcntl_signal_dispatch")) {
                        pcntl_signal_dispatch();
                        if ($this->reload) {
                            $this->reload();
                            fwrite(\STDERR, Arsse::$lang->msg("Service.Reload").\PHP_EOL);
                        }
                    }
                } while ($this->loop && $t->getTimestamp() > time());
            }
            // @codeCoverageIgnoreEnd
        } while ($this->loop);
        return $t;
    }

    public function reload(): void {
        $this->reload = false;
        Arsse::$user = Arsse::$db = Arsse::$conf = Arsse::$lang = Arsse::$obj = $this->drv = null;
        Arsse::bootstrap();
        $this->__construct();
    }

    public function checkIn(): bool {
        Arsse::$db->checkSchemaVersion();
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
        $limit = new \DateTime;
        $limit->sub($int);
        $limit->sub($int);
        // return whether the check-in time is within the acceptable limit
        return $checkin >= $limit;
    }

    public static function cleanupPre(): bool {
        // delete soft-deleted subscriptions that are beyond their retention period
        Arsse::$db->subscriptionCleanup();
        // do the same for icons
        Arsse::$db->iconCleanup();
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

    protected function signalInit(): void {
        if (function_exists("pcntl_async_signals") && function_exists("pcntl_signal")) {
            // receive asynchronous signals if supported
            pcntl_async_signals(true);
            foreach ([\SIGABRT, \SIGINT, \SIGTERM] as $sig) {
                pcntl_signal($sig, [$this, "sigTerm"]);
            }
            pcntl_signal(\SIGHUP, [$this, "sigHup"]);
        }
    }

    /** Changes the condition for the service loop upon receiving a termination signal
     *
     * @codeCoverageIgnore */
    protected function sigTerm(int $signo): void {
        $this->loop = false;
    }

    /** Changes the condition for the service loop upon receiving a hangup signal
     *
     * @codeCoverageIgnore */
    protected function sigHup(int $signo): void {
        $this->reload = true;
    }
}
