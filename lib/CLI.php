<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\REST\Fever\User as Fever;
use JKingWeb\Arsse\ImportExport\OPML;

class CLI {
    const USAGE = <<<USAGE_TEXT
Usage:
    arsse.php daemon
    arsse.php feed refresh-all
    arsse.php feed refresh <n>
    arsse.php conf save-defaults [<file>]
    arsse.php user [list]
    arsse.php user add <username> [<password>]
    arsse.php user remove <username>
    arsse.php user set-pass <username> [<password>]
        [--oldpass=<pass>] [--fever]
    arsse.php user unset-pass <username>
        [--oldpass=<pass>] [--fever]
    arsse.php user auth <username> <password> [--fever]
    arsse.php export <username> [<file>] 
        [-f | --flat]
    arsse.php import <username> [<file>] 
        [-f | --flat] [-r | --replace]
    arsse.php --version
    arsse.php --help | -h

The Arsse command-line interface currently allows you to start the refresh
daemon, refresh all feeds or a specific feed by numeric ID, manage users,
or save default configuration to a sample file.
USAGE_TEXT;

    protected function usage($prog): string {
        $prog = basename($prog);
        return str_replace("arsse.php", $prog, self::USAGE);
    }

    protected function command(array $options, $args): string {
        foreach ($options as $cmd) {
            foreach (explode(" ", $cmd) as $part) {
                if (!$args[$part]) {
                    continue 2;
                }
            }
            return $cmd;
        }
        return "";
    }

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

    public function dispatch(array $argv = null) {
        $argv = $argv ?? $_SERVER['argv'];
        $argv0 = array_shift($argv);
        $args = \Docopt::handle($this->usage($argv0), [
            'argv' => $argv,
            'help' => false,
        ]);
        try {
            $cmd = $this->command(["--help", "--version", "daemon", "feed refresh", "feed refresh-all", "conf save-defaults", "user", "export", "import"], $args);
            if ($cmd && !in_array($cmd, ["--help", "--version", "conf save-defaults"])) {
                // only certain commands don't require configuration to be loaded
                $this->loadConf();
            }
            switch ($cmd) {
                case "--help":
                    echo $this->usage($argv0).\PHP_EOL;
                    return 0;
                case "--version":
                    echo Arsse::VERSION.\PHP_EOL;
                    return 0;
                case "daemon":
                    $this->getInstance(Service::class)->watch(true);
                    return 0;
                case "feed refresh":
                    return (int) !Arsse::$db->feedUpdate((int) $args['<n>'], true);
                case "feed refresh-all":
                    $this->getInstance(Service::class)->watch(false);
                    return 0;
                case "conf save-defaults":
                    $file = $this->resolveFile($args['<file>'], "w");
                    return (int) !$this->getInstance(Conf::class)->exportFile($file, true);
                case "user":
                    return $this->userManage($args);
                case "export":
                    $u = $args['<username>'];
                    $file = $this->resolveFile($args['<file>'], "w");
                    return (int) !$this->getInstance(OPML::class)->exportFile($file, $u, $args['--flat']);
                case "import":
                    $u = $args['<username>'];
                    $file = $this->resolveFile($args['<file>'], "w");
                    return (int) !$this->getInstance(OPML::class)->importFile($file, $u, $args['--flat'], $args['--replace']);
            }
        } catch (AbstractException $e) {
            $this->logError($e->getMessage());
            return $e->getCode();
        }
    }

    /** @codeCoverageIgnore */
    protected function logError(string $msg) {
        fwrite(STDERR, $msg.\PHP_EOL);
    }

    protected function getInstance(string $class) {
        return new $class;
    }

    protected function userManage($args): int {
        switch ($this->command(["add", "remove", "set-pass", "unset-pass", "list", "auth"], $args)) {
            case "add":
                return $this->userAddOrSetPassword("add", $args["<username>"], $args["<password>"]);
            case "set-pass":
                if ($args['--fever']) {
                    $passwd = $this->getInstance(Fever::class)->register($args["<username>"], $args["<password>"]);
                    if (is_null($args["<password>"])) {
                        echo $passwd.\PHP_EOL;
                    }
                    return 0;
                } else {
                    return $this->userAddOrSetPassword("passwordSet", $args["<username>"], $args["<password>"], $args["--oldpass"]);
                }
            case "unset-pass":
                if ($args['--fever']) {
                    $this->getInstance(Fever::class)->unregister($args["<username>"]);
                } else {
                    Arsse::$user->passwordUnset($args["<username>"], $args["--oldpass"]);
                }
                return 0;
            case "remove":
                return (int) !Arsse::$user->remove($args["<username>"]);
            case "auth":
                return $this->userAuthenticate($args["<username>"], $args["<password>"], $args["--fever"]);
            case "list":
            case "":
                return $this->userList();
        }
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
        $result  = $fever ? $this->getInstance(Fever::class)->authenticate($user, $password) : Arsse::$user->auth($user, $password);
        if ($result) {
            echo Arsse::$lang->msg("CLI.Auth.Success").\PHP_EOL;
            return 0;
        } else {
            echo Arsse::$lang->msg("CLI.Auth.Failure").\PHP_EOL;
            return 1;
        }
    }
}
