<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\REST\Fever\User as Fever;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\REST\Miniflux\Token as Miniflux;

class CLI {
    public const USAGE = <<<USAGE_TEXT
Usage:
    arsse.php user [list]
    arsse.php user add <username> [<password>] [--admin]
    arsse.php user remove <username>
    arsse.php user show <username>
    arsse.php user set <username> <property> <value>
    arsse.php user unset <username> <property>
    arsse.php user set-pass <username> [<password>] [--fever]
    arsse.php user unset-pass <username> [--fever]
    arsse.php user auth <username> <password> [--fever]
    arsse.php token list <username>
    arsse.php token create <username> [<label>]
    arsse.php token revoke <username> [<token>]
    arsse.php import <username> [<file>] [-f|--flat] [-r|--replace]
    arsse.php export <username> [<file>] [-f|--flat]
    arsse.php daemon [--fork=PIDFILE]
    arsse.php feed refresh-all
    arsse.php feed refresh <n>
    arsse.php conf save-defaults [<file>]
    arsse.php --version
    arsse.php -h|--help

The Arsse command-line interface can be used to perform various administrative
tasks such as starting the newsfeed refresh service, managing users, and 
importing or exporting data.

See the manual page for more details:

    man 1 arsse
USAGE_TEXT;

    protected function usage($prog): string {
        $prog = basename($prog);
        return str_replace("arsse.php", $prog, self::USAGE);
    }

    protected function command($args): string {
        $out = [];
        foreach ($args as $k => $v) {
            if (preg_match("/^[a-z]/", $k) && $v === true) {
                $out[] = $k;
            }
        }
        return implode(" ", $out);
    }

    /** @codeCoverageIgnore */
    protected function loadConf(): bool {
        $conf = file_exists(BASE."config.php") ? new Conf(BASE."config.php") : new Conf;
        Arsse::load($conf);
        return true;
    }

    protected function resolveFile($file, string $mode): string {
        // TODO: checking read/write permissions on the provided path may be useful
        $stdinOrStdout = in_array($mode, ["r", "r+"]) ? "php://input" : "php://output";
        return ($file === "-" ? null : $file) ?? $stdinOrStdout;
    }

    public function dispatch(array $argv = null): int {
        $argv = $argv ?? $_SERVER['argv'];
        $argv0 = array_shift($argv);
        $args = \Docopt::handle($this->usage($argv0), [
            'argv' => $argv,
            'help' => false,
        ]);
        try {
            $cmd = $this->command($args);
            if ($cmd && !in_array($cmd, ["", "conf save-defaults", "daemon"])) {
                // only certain commands don't require configuration to be loaded; daemon loads configuration after forking (if applicable)
                $this->loadConf();
            }
            switch ($cmd) {
                case "":
                    if ($args['--version']) {
                        echo Arsse::VERSION.\PHP_EOL;
                    } elseif ($args['--help'] || $args['-h']) {
                        echo $this->usage($argv0).\PHP_EOL;
                    }
                    return 0;
                case "daemon":
                    if ($args['--fork'] !== null) {
                        $pidfile = $this->resolvePID($args['--fork']);
                        $this->fork($pidfile);
                    }
                    $this->loadConf();
                    Arsse::$obj->get(Service::class)->watch(true);
                    if (isset($pidfile)) {
                        unlink($pidfile);
                    }
                    return 0;
                case "feed refresh":
                    return (int) !Arsse::$db->feedUpdate((int) $args['<n>'], true);
                case "feed refresh-all":
                    Arsse::$obj->get(Service::class)->watch(false);
                    return 0;
                case "conf save-defaults":
                    $file = $this->resolveFile($args['<file>'], "w");
                    return (int) !Arsse::$obj->get(Conf::class)->exportFile($file, true);
                case "export":
                    $u = $args['<username>'];
                    $file = $this->resolveFile($args['<file>'], "w");
                    return (int) !Arsse::$obj->get(OPML::class)->exportFile($file, $u, ($args['--flat'] || $args['-f']));
                case "import":
                    $u = $args['<username>'];
                    $file = $this->resolveFile($args['<file>'], "r");
                    return (int) !Arsse::$obj->get(OPML::class)->importFile($file, $u, ($args['--flat'] || $args['-f']), ($args['--replace'] || $args['-r']));
                case "token list":
                case "list token": // command reconstruction yields this order for "token list" command
                    return $this->tokenList($args['<username>']);
                case "token create":
                    echo Arsse::$obj->get(Miniflux::class)->tokenGenerate($args['<username>'], $args['<label>']).\PHP_EOL;
                    return 0;
                case "token revoke":
                    Arsse::$db->tokenRevoke($args['<username>'], "miniflux.login", $args['<token>']);
                    return 0;
                case "user add":
                    $out = $this->userAddOrSetPassword("add", $args["<username>"], $args["<password>"]);
                    if ($args['--admin']) {
                        Arsse::$user->propertiesSet($args["<username>"], ['admin' => true]);
                    }
                    return $out;
                case "user set-pass":
                    if ($args['--fever']) {
                        $passwd = Arsse::$obj->get(Fever::class)->register($args["<username>"], $args["<password>"]);
                        if (is_null($args["<password>"])) {
                            echo $passwd.\PHP_EOL;
                        }
                        return 0;
                    } else {
                        return $this->userAddOrSetPassword("passwordSet", $args["<username>"], $args["<password>"], $args["--oldpass"]);
                    }
                    // no break
                case "user unset-pass":
                    if ($args['--fever']) {
                        Arsse::$obj->get(Fever::class)->unregister($args["<username>"]);
                    } else {
                        Arsse::$user->passwordUnset($args["<username>"], $args["--oldpass"]);
                    }
                    return 0;
                case "user remove":
                    return (int) !Arsse::$user->remove($args["<username>"]);
                case "user show":
                    return $this->userShowProperties($args["<username>"]);
                case "user set":
                    return (int) !Arsse::$user->propertiesSet($args["<username>"], [$args["<property>"] => $args["<value>"]]);
                case "user unset":
                    return (int) !Arsse::$user->propertiesSet($args["<username>"], [$args["<property>"] => null]);
                case "user auth":
                    return $this->userAuthenticate($args["<username>"], $args["<password>"], $args["--fever"]);
                case "user list":
                case "user":
                    return $this->userList();
                default:
                    throw new Exception("constantUnknown", $cmd); // @codeCoverageIgnore
            }
        } catch (AbstractException $e) {
            $this->logError($e->getMessage());
            return $e->getCode();
        }
    } // @codeCoverageIgnore

    /** @codeCoverageIgnore */
    protected function logError(string $msg): void {
        fwrite(STDERR, $msg.\PHP_EOL);
    }

    protected function userAddOrSetPassword(string $method, string $user, string $password = null, string $oldpass = null): int {
        $passwd = Arsse::$user->$method(...array_slice(func_get_args(), 1));
        if (is_null($password)) {
            echo $passwd.\PHP_EOL;
        }
        return 0;
    }

    protected function userList(): int {
        $list = Arsse::$user->list();
        if ($list) {
            echo implode(\PHP_EOL, $list).\PHP_EOL;
        }
        return 0;
    }

    protected function userAuthenticate(string $user, string $password, bool $fever = false): int {
        $result = $fever ? Arsse::$obj->get(Fever::class)->authenticate($user, $password) : Arsse::$user->auth($user, $password);
        if ($result) {
            echo Arsse::$lang->msg("CLI.Auth.Success").\PHP_EOL;
            return 0;
        } else {
            echo Arsse::$lang->msg("CLI.Auth.Failure").\PHP_EOL;
            return 1;
        }
    }

    protected function userShowProperties(string $user): int {
        $data = Arsse::$user->propertiesGet($user);
        $len = array_reduce(array_keys($data), function($carry, $item) {
            return max($carry, strlen($item));
        }, 0) + 2;
        foreach ($data as $k => $v) {
            echo str_pad($k, $len, " ");
            echo var_export($v, true).\PHP_EOL;
        }
        return 0;
    }

    protected function tokenList(string $user): int {
        $list = Arsse::$obj->get(Miniflux::class)->tokenList($user);
        usort($list, function($v1, $v2) {
            return $v1['label'] <=> $v2['label'];
        });
        foreach ($list as $t) {
            echo $t['id']."  ".$t['label'].\PHP_EOL;
        }
        return 0;
    }

    protected function fork(string $pidfile): void {
        // check that the PID file is not already used by another process
        $this->checkPID($pidfile, false);
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
                        $this->checkPID($pidfile, true);
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

    protected function checkPID(string $pidfile, bool $lock) {
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
    protected function resolvePID(string $pidfile): string {
        $dir = dirname($pidfile);
        $file = basename($pidfile);
        if (!strlen($file)) {
            throw new \Exception("Specified PID file location must be a regular file");
        } elseif ($base = @realpath($dir)) {
            $out = "$base/$file";
            if (file_exists($out)) {
                if (!is_writable($out)) {
                    throw new \Exception("PID file is not writable");
                } elseif (!is_file($out)) {
                    throw new \Exception("Specified PID file location must be a regular file");
                }
            } elseif (!is_writable($base)) {
                throw new \Exception("Cannot create PID file");
            }
        } else {
            throw new \Exception("Parent directory of PID file does not exist");
        }
        return $out;
    }
}
