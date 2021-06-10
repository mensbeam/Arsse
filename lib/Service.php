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

    public function __construct() {
        $driver = Arsse::$conf->serviceDriver;
        $this->drv = new $driver();
    }

    public function watch(bool $loop = true): \DateTimeInterface {
        $this->loop = $loop;
        $this->signalInit();
        $t = new \DateTime();
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
                    pcntl_signal_dispatch();
                } while ($this->loop && $t->getTimestamp() > time());
            }
            // @codeCoverageIgnoreEnd
        } while ($this->loop);
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
        return $checkin >= $limit;
    }

    public static function cleanupPre(): bool {
        // mark unsubscribed feeds as orphaned and delete orphaned feeds that are beyond their retention period
        Arsse::$db->feedCleanup();
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
        }
    }

    /** Changes the condition for the service loop upon receiving a termination signal
     * 
     * @codeCoverageIgnore */
    protected function sigTerm(int $signo): void {
        $this->loop = false;
    }

    /** Daemonizes the process via the traditional sysvinit double-fork procedure
     * 
     * @codeCoverageIgnore
     */
    public static function fork(string $pidfile): void {
        // check that the PID file is not already used by another process
        static::checkPID($pidfile, false);
        // We will follow systemd's recommended daemonizing process as much as possible:
        # Close all open file descriptors except standard input, output, and error (i.e. the first three file descriptors 0, 1, 2). This ensures that no accidentally passed file descriptor stays around in the daemon process. On Linux, this is best implemented by iterating through /proc/self/fd, with a fallback of iterating from file descriptor 3 to the value returned by getrlimit() for RLIMIT_NOFILE.
        // We should have no open file descriptors at this time. Even if we did, I'm not certain how they should be closed from PHP
        # Reset all signal handlers to their default. This is best done by iterating through the available signals up to the limit of _NSIG and resetting them to SIG_DFL.
        // We have not yet set any signal handlers, so this should be fine
        # Reset the signal mask using sigprocmask().
        // Not possible to my knowledge
        # Sanitize the environment block, removing or resetting environment variables that might negatively impact daemon runtime.
        //Not necessary; we don't use the environment
        # Call fork(), to create a background process.
        $pipe = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        switch (@pcntl_fork()) {
            case -1:
                // Unable to fork
                throw new \Exception("Unable to fork");
            case 0:
                fclose($pipe[0]);
                # In the child, call setsid() to detach from any terminal and create an independent session.
                @posix_setsid();
                # In the child, call fork() again, to ensure that the daemon can never re-acquire a terminal again. (This relevant if the program — and all its dependencies — does not carefully specify `O_NOCTTY` on each and every single `open()` call that might potentially open a TTY device node.)
                switch (@pcntl_fork()) {
                    case -1:
                        // Unable to fork
                        throw new \Exception("Unable to fork");
                    case 0:
                        // We do some things out of order because as far as I know there's no way to reconnect stdin, stdout, and stderr without closing the channel to the parent first
                        # In the daemon process, write the daemon PID (as returned by getpid()) to a PID file, for example /run/foobar.pid (for a hypothetical daemon "foobar") to ensure that the daemon cannot be started more than once. This must be implemented in race-free fashion so that the PID file is only updated when it is verified at the same time that the PID previously stored in the PID file no longer exists or belongs to a foreign process.
                        static::checkPID($pidfile, true);
                        # In the daemon process, drop privileges, if possible and applicable.
                        // already done
                        # From the daemon process, notify the original process started that initialization is complete. This can be implemented via an unnamed pipe or similar communication channel that is created before the first fork() and hence available in both the original and the daemon process.
                        fwrite($pipe[1], (string) posix_getpid());
                        fclose($pipe[1]);
                        // now everything else is done in order
                        # In the daemon process, connect /dev/null to standard input, output, and error.
                        fclose(STDIN);
                        fclose(STDOUT);
                        fclose(STDERR);
                        global $STDIN, $STDOUT, $STDERR;
                        $STDIN = fopen("/dev/null", "r");
                        $STDOUT = fopen("/dev/null", "w");
                        $STDERR = fopen("/dev/null", "w");
                        # In the daemon process, reset the umask to 0, so that the file modes passed to open(), mkdir() and suchlike directly control the access mode of the created files and directories.
                        umask(0);
                        # In the daemon process, change the current directory to the root directory (/), in order to avoid that the daemon involuntarily blocks mount points from being unmounted.
                        chdir("/");
                        return;                   
                    default:
                        # Call exit() in the first child, so that only the second child (the actual daemon process) stays around. This ensures that the daemon process is re-parented to init/PID 1, as all daemons should be.
                        exit;
                }
            default:
                fclose($pipe[1]);
                fread($pipe[0], 100);
                fclose($pipe[0]);
                # Call exit() in the original process. The process that invoked the daemon must be able to rely on that this exit() happens after initialization is complete and all external communication channels are established and accessible.
                exit;
        }
    }

    protected static function checkPID(string $pidfile, bool $lock) {
        if (!$lock) {
            if (file_exists($pidfile)) {
                $pid = @file_get_contents($pidfile);
                if (preg_match("/^\d+$/s", (string) $pid)) {
                    if (@posix_kill((int) $pid, 0)) {
                        throw new \Exception("Process already exists");
                    }
                }
            }
        } else {
            if ($f = @fopen($pidfile, "c+")) {
                if (@flock($f, \LOCK_EX | \LOCK_NB)) {
                    // confirm that some other process didn't get in before us
                    $pid = fread($f, 100);
                    if (preg_match("/^\d+$/s", (string) $pid)) {
                        if (@posix_kill((int) $pid, 0)) {
                            throw new \Exception("Process already exists");
                        }
                    }
                    // write the PID to the pidfile
                    rewind($f);
                    ftruncate($f, 0);
                    fwrite($f, (string) posix_getpid());
                    fclose($f);
                } else {
                    throw new \Exception("Process already exists");
                }
            } else {
                throw new Exception("Could not write to PID file");
            }
        }
    }

    /** Resolves the PID file path and ensures the file or parent directory is writable */
    public static function resolvePID(string $pidfile): string {
        $dir = dirname($pidfile);
        $file = basename($pidfile);
        if (!strlen($file)) {
            throw new Service\Exception("pidNotFile", ['pidfile' => $dir]);
        } elseif ($base = @static::realpath($dir)) {
            $out = "$base/$file";
            if (file_exists($out)) {
                if (!is_readable($out) && !is_writable($out)) {
                    throw new Service\Exception("pidUnusable", ['pidfile' => $out]);
                } elseif (!is_readable($out)) {
                    throw new Service\Exception("pidunreadable", ['pidfile' => $out]);
                } elseif (!is_writable($out)) {
                    throw new Service\Exception("pidUnwritable", ['pidfile' => $out]);
                } elseif (!is_file($out)) {
                    throw new Service\Exception("pidNotFile", ['pidfile' => $out]);
                }
            } elseif (!is_writable($base)) {
                throw new Service\Exception("pidUncreatable", ['pidfile' => $out]);
            }
        } else {
            throw new Service\Exception("pidDirNotFound", ['piddir' => $dir]);
        }
        return $out;
    }

    protected static function realpath(string $path) {
        return @realpath($path);
    }
}
