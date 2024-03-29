<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\Service;

use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Arsse;

class Daemon {
    protected const PID_PATTERN = '/^([1-9]\d{0,77})?$/D'; // no more than 78 digits (256-bit unsigned integer), starting with a digit other than zero
    protected const REQUIRED_EXTENSIONS = ["posix", "pcntl"];

    public function __construct() {
        Arsse::checkExtensions(...self::REQUIRED_EXTENSIONS);
    }

    /** Daemonizes the process via the traditional sysvinit double-fork procedure
     *
     * @codeCoverageIgnore
     */
    public function fork(string $pidfile): void {
        // check that the PID file is not already used by another process
        $this->checkPID($pidfile, false);
        // We will follow systemd's recommended daemonizing process as much as possible:
        # Close all open file descriptors except standard input, output, and error (i.e. the first three file descriptors 0, 1, 2). This ensures that no accidentally passed file descriptor stays around in the daemon process. On Linux, this is best implemented by iterating through /proc/self/fd, with a fallback of iterating from file descriptor 3 to the value returned by getrlimit() for RLIMIT_NOFILE.
        // We should have no open file descriptors at this time. Even if we did, I'm not certain how they should be closed from PHP
        # Reset all signal handlers to their default. This is best done by iterating through the available signals up to the limit of _NSIG and resetting them to SIG_DFL.
        // We have not yet set any signal handlers, so this should be fine
        # Reset the signal mask using sigprocmask().
        pcntl_sigprocmask(\SIG_SETMASK, []);
        # Sanitize the environment block, removing or resetting environment variables that might negatively impact daemon runtime.
        //Not necessary; we don't use the environment
        # Call fork(), to create a background process.
        $pipe = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        switch (@pcntl_fork()) {
            case -1:
                // Unable to fork
                throw new Exception("forkFailed", ['instance' => 1]);
            case 0:
                fclose($pipe[0]);
                # In the child, call setsid() to detach from any terminal and create an independent session.
                try {
                    if (@posix_setsid() === -1) {
                        throw new Exception("forkFailed", ['instance' => 1]);
                    }
                    # In the child, call fork() again, to ensure that the daemon can never re-acquire a terminal again. (This relevant if the program — and all its dependencies — does not carefully specify `O_NOCTTY` on each and every single `open()` call that might potentially open a TTY device node.)
                    switch (@pcntl_fork()) {
                        case -1:
                            // Unable to fork
                            throw new Exception("forkFailed", ['instance' => 2]);
                        case 0:
                            // We do some things out of order because as far as I know there's no way to reconnect stdin, stdout, and stderr without closing the channel to the parent first
                            # In the daemon process, write the daemon PID (as returned by getpid()) to a PID file, for example /run/foobar.pid (for a hypothetical daemon "foobar") to ensure that the daemon cannot be started more than once. This must be implemented in race-free fashion so that the PID file is only updated when it is verified at the same time that the PID previously stored in the PID file no longer exists or belongs to a foreign process.
                            $this->writePID($pidfile);
                            # In the daemon process, drop privileges, if possible and applicable.
                            // already done
                            # From the daemon process, notify the original process started that initialization is complete. This can be implemented via an unnamed pipe or similar communication channel that is created before the first fork() and hence available in both the original and the daemon process.
                            fwrite($pipe[1], (string) posix_getpid());
                            fclose($pipe[1]);
                            // now everything else is done in order, but beyond this point any errors cannot be reported back to the original process
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
                } catch (AbstractException $e) {
                    // transmit the exception back to the original process, which will re-create the exception if necessary
                    @fwrite($pipe[1], json_encode([get_class($e), $e->getSymbol(), $e->getParams()]));
                    exit;
                }
            default:
                fclose($pipe[1]);
                $result = json_decode(fread($pipe[0], 100), true);
                if (is_array($result)) {
                    [$class, $symbol, $params] = $result;
                    throw new $class($symbol, $params);
                }
                fclose($pipe[0]);
                # Call exit() in the original process. The process that invoked the daemon must be able to rely on that this exit() happens after initialization is complete and all external communication channels are established and accessible.
                exit;
        }
    }

    public function checkPID(string $pidfile): void {
        if (file_exists($pidfile)) {
            $pid = @file_get_contents($pidfile);
            if ($pid !== false) {
                if (preg_match(static::PID_PATTERN, $pid)) {
                    if (strlen($pid) && $this->processExists((int) $pid)) {
                        throw new Exception("pidDuplicate", ['pid' => $pid]);
                    }
                } else {
                    throw new Exception("pidCorrupt", ['pidfile' => $pidfile]);
                }
            } else {
                throw new Exception("pidInaccessible", ['pidfile' => $pidfile]);
            }
        }
    }

    public function writePID(string $pidfile): void {
        if ($f = @fopen($pidfile, "c+")) {
            if (@flock($f, \LOCK_EX | \LOCK_NB)) {
                try {
                    // confirm that some other process didn't get in before us
                    $pid = fread($f, 80);
                    if (preg_match(static::PID_PATTERN, (string) $pid)) {
                        if (strlen($pid) && $this->processExists((int) $pid)) {
                            throw new Exception("pidDuplicate", ['pid' => $pid]);
                        }
                    } else {
                        throw new Exception("pidCorrupt", ['pidfile' => $pidfile]);
                    }
                    // write the PID to the pidfile
                    rewind($f);
                    if (!ftruncate($f, 0) || !fwrite($f, (string) posix_getpid())) {
                        throw new Exception("pidInaccessible", ['pidfile' => $pidfile]);
                    }
                } finally {
                    flock($f, \LOCK_UN);
                    fclose($f);
                }
            } else {
                throw new Exception("pidLocked", ['pidfile' => $pidfile]);
            }
        } else {
            throw new Exception("pidInaccessible", ['pidfile' => $pidfile]);
        }
    }

    /** Wrapper around posix_kill (with signal 0) to facilitation testing
     *
     * @codeCoverageIgnore
     */
    protected function processExists(int $pid): bool {
        return @posix_kill($pid, 0);
    }

    /** Resolves the PID file path and ensures the file or parent directory is writable */
    public function checkPIDFilePath(string $pidfile): string {
        $dir = dirname($pidfile);
        $file = basename($pidfile);
        $base = $this->resolveRelativePath($dir);
        if (!strlen($file)) {
            throw new Exception("pidNotFile", ['pidfile' => $dir]);
        } elseif ($base) {
            $out = "$base/$file";
            if (file_exists($out)) {
                if (!is_file($out)) {
                    throw new Exception("pidNotFile", ['pidfile' => $out]);
                } elseif (!is_readable($out) && !is_writable($out)) {
                    throw new Exception("pidUnusable", ['pidfile' => $out]);
                } elseif (!is_readable($out)) {
                    throw new Exception("pidUnreadable", ['pidfile' => $out]);
                } elseif (!is_writable($out)) {
                    throw new Exception("pidUnwritable", ['pidfile' => $out]);
                }
            } elseif (!is_dir($base)) {
                throw new Exception("pidDirMissing", ['piddir' => $dir]);
            } elseif (!is_writable($base)) {
                throw new Exception("pidUncreatable", ['pidfile' => $out]);
            }
        } else {
            throw new Exception("pidDirUnresolvable", ['piddir' => $dir]);
        }
        return $out;
    }

    /** Resolves paths with relative components
     *
     * This method has fewer filesystem access requirements than the native
     * realpath() function. The current working directory most be resolvable
     * for a relative path, but for absolute paths with relativelu components
     * the filesystem is not involved at all.
     *
     * Consequently symbolic links are not resolved.
     *
     * @return string|false
     */
    public function resolveRelativePath(string $path) {
        if ($path[0] !== "/") {
            $cwd = $this->cwd();
            if ($cwd === false) {
                return false;
            }
            $path = explode("/", substr($cwd, 1)."/".$path);
        } else {
            $path = explode("/", substr($path, 1));
        }
        $out = [];
        foreach ($path as $p) {
            if ($p === "..") {
                array_pop($out);
            } elseif ($p === ".") {
                continue;
            } else {
                $out[] = $p;
            }
        }
        return "/".implode("/", $out);
    }

    /** Wrapper around posix_getcwd to facilitate testing
     *
     * @return string|false
     * @codeCoverageIgnore
     */
    protected function cwd() {
        return posix_getcwd();
    }
}
