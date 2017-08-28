<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class CLI {
    protected $args = [];
    
    protected function usage(): string {
        $prog = basename($_SERVER['argv'][0]);
        return <<<USAGE_TEXT
Usage:
    $prog daemon
    $prog feed refresh <n>
    $prog conf save-defaults <file>
    $prog user add <username> [<password>]
    $prog --version
    $prog --help | -h

The Arsse command-line interface currently allows you to start the refresh
daemon, refresh a specific feed by numeric ID, add a user, or save default 
configuration to a sample file.
USAGE_TEXT;
    }

    function __construct(array $argv = null) {
        if(is_null($argv)) {
            $argv = array_slice($_SERVER['argv'], 1);
        }
        $this->args = \Docopt::handle($this->usage(), [
            'argv' => $argv,
            'help' => true,
            'version' => VERSION,
        ]);
    }

    protected function loadConf(): bool {
        // FIXME: this should be a method of the Conf class
        Arsse::load(new Conf());
        if(file_exists(BASE."config.php")) {
            Arsse::$conf->importFile(BASE."config.php");
        }
        // command-line operations will never respect authorization
        Arsse::$user->authorizationEnabled(false);
        return true;
    }

    function dispatch(array $args = null): int {
        // act on command line
        if(is_null($args)) {
            $args = $this->args;
        }
        if($this->command("daemon", $args)) {
            $this->loadConf();
            return $this->daemon();
        } else if($this->command("feed refresh", $args)) {
            $this->loadConf();
            return $this->feedRefresh((int) $args['<n>']);
        } else if($this->command("conf save-defaults", $args)) {
            return $this->confSaveDefaults($args['<file>']);
        } else if($this->command("user add", $args)) {
            $this->loadConf();
            return $this->userAdd($args['<username>'], $args['<password>']);
        }
    }

    protected function command($cmd, $args): bool {
        foreach(explode(" ", $cmd) as $part) {
            if(!$args[$part]) {
                return false;
            }
        }
        return true;
    }

    function daemon(bool $loop = true): int {
        (new Service)->watch($loop);
        return 0; // FIXME: should return the exception code of thrown exceptions
    }

    function feedRefresh(int $id): int {
        return (int) !Arsse::$db->feedUpdate($id); // FIXME: exception error codes should be returned here
    }

    function confSaveDefaults(string $file): int {
        return (int) !(new Conf)->exportFile($file, true);
    }

    function userAdd(string $user, string $password = null): int {
        $passwd = Arsse::$user->add($user, $password);
        if(is_null($password)) {
            echo $passwd;
        }
        return 0;
    }
}