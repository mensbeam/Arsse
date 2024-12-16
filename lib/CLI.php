<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse;

use JKingWeb\Arsse\REST\Fever\User as Fever;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\REST\Miniflux\Token as Miniflux;
use JKingWeb\Arsse\Service\Daemon;

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

    man arsse 
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
        Arsse::bootstrap();
        return true;
    }

    protected function resolveFile($file, string $mode): string {
        // TODO: checking read/write permissions on the provided path may be useful
        $stdinOrStdout = in_array($mode, ["r", "r+"]) ? "php://input" : "php://output";
        return ($file === "-" ? null : $file) ?? $stdinOrStdout;
    }

    public function dispatch(?array $argv = null): int {
        $argv = $argv ?? $_SERVER['argv'];
        $argv0 = array_shift($argv);
        $args = \Docopt::handle($this->usage($argv0), [
            'argv' => $argv,
            'help' => false,
        ]);
        try {
            // ensure the require extensions are loaded
            Arsse::checkExtensions(...Arsse::REQUIRED_EXTENSIONS);
            // reconstitute multi-token commands (e.g. user add) into a single string
            $cmd = $this->command($args);
            if ($cmd && !in_array($cmd, ["", "conf save-defaults", "daemon"])) {
                // only certain commands don't require configuration to be loaded; daemon loads configuration after forking (if applicable)
                $this->loadConf();
            }
            // run the requested command
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
                        return $this->serviceFork($args['--fork']);
                    } else {
                        $this->loadConf();
                        Arsse::$obj->get(Service::class)->watch(true);
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
                        return $this->userAddOrSetPassword("passwordSet", $args["<username>"], $args["<password>"]);
                    }
                    // no break
                case "user unset-pass":
                    if ($args['--fever']) {
                        Arsse::$obj->get(Fever::class)->unregister($args["<username>"]);
                    } else {
                        Arsse::$user->passwordUnset($args["<username>"]);
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

    protected function serviceFork(string $pidfile): int {
        // initialize the object factory
        Arsse::$obj = Arsse::$obj ?? new Factory;
        // create a Daemon object which contains various helper functions
        $daemon = Arsse::$obj->get(Daemon::class);
        // resolve the PID file to its absolute path; this also checks its readability and writability
        $pidfile = $daemon->checkPIDFilePath($pidfile);
        // daemonize
        $daemon->fork($pidfile);
        // start the fetching service as normal
        $this->loadConf();
        Arsse::$obj->get(Service::class)->watch(true);
        // after the service has been shut down, delete the PID file and exit cleanly
        unlink($pidfile);
        return 0;
    }

    protected function userAddOrSetPassword(string $method, string $user, ?string $password = null, ?string $oldpass = null): int {
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
}
