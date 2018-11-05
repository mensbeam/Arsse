<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

use Docopt\Response as Opts;

class CLI {
    protected $args = [];

    protected function usage(): string {
        $prog = basename($_SERVER['argv'][0]);
        return <<<USAGE_TEXT
Usage:
    $prog daemon
    $prog feed refresh <n>
    $prog conf save-defaults [<file>]
    $prog user [list]
    $prog user add <username> [<password>]
    $prog user remove <username>
    $prog user set-pass [--oldpass=<pass>] <username> [<password>]
    $prog user auth <username> <password>
    $prog --version
    $prog --help | -h

The Arsse command-line interface currently allows you to start the refresh
daemon, refresh a specific feed by numeric ID, manage users, or save default
configuration to a sample file.
USAGE_TEXT;
    }

    public function __construct(array $argv = null) {
        $argv = $argv ?? array_slice($_SERVER['argv'], 1);
        $this->args = \Docopt::handle($this->usage(), [
            'argv' => $argv,
            'help' => true,
            'version' => Arsse::VERSION,
        ]);
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
        // command-line operations will never respect authorization
        Arsse::$user->authorizationEnabled(false);
        return true;
    }

    public function dispatch(array $args = null): int {
        // act on command line
        $args = $args ?? $this->args;
        try {
            switch ($this->command(["daemon", "feed refresh", "conf save-defaults", "user"], $args)) {
                case "daemon":
                    $this->loadConf();
                    return $this->daemon();
                case "feed refresh":
                    $this->loadConf();
                    return $this->feedRefresh((int) $args['<n>']);
                case "conf save-defaults":
                    return $this->confSaveDefaults($args['<file>']);
                case "user":
                    $this->loadConf();
                    return $this->userManage($args);
            }
        } catch (AbstractException $e) {
            fwrite(STDERR, $e->getMessage().\PHP_EOL);
            return $e->getCode();
        }
    }

    public function daemon(bool $loop = true): int {
        (new Service)->watch($loop);
        return 0; // FIXME: should return the exception code of thrown exceptions
    }

    public function feedRefresh(int $id): int {
        return (int) !Arsse::$db->feedUpdate($id); // FIXME: exception error codes should be returned here
    }

    public function confSaveDefaults(string $file = null): int {
        $file = ($file=="-" ? null : $file) ?? STDOUT;
        return (int) !(new Conf)->exportFile($file, true);
    }

    public function userManage($args): int {
        switch ($this->command(["add", "remove", "set-pass", "list", "auth"], $args)) {
            case "add":
                return $this->userAddOrSetPassword("add", $args["<username>"], $args["<password>"]);
            case "set-pass":
                return $this->userAddOrSetPassword("passwordSet", $args["<username>"], $args["<password>"], $args["<oldpass>"]);
            case "remove":
                return (int) !Arsse::$user->remove($args["<username>"]);
            case "auth":
                return $this->userAuthenticate($args["<username>"], $args["<password>"]);
            case "list":
            case "":
                return $this->userList();
        }
    }

    protected function userAddOrSetPassword(string $method, string $user, string $password = null, string $oldpass = null): int {
        $args = \func_get_args();
        array_shift($args);
        $passwd = Arsse::$user->$method(...$args);
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

    protected function userAuthenticate(string $user, string $password): int {
        if (Arsse::$user->auth($user, $password)) {
            echo Arsse::$lang->msg("CLI.Auth.Success").\PHP_EOL;
            return 0;
        } else {
            echo Arsse::$lang->msg("CLI.Auth.Failure").\PHP_EOL;
            return 1;
        }
    }
}
