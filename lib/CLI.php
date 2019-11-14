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
    arsse.php import <username> [<file>]
        [-f | --flat] [-r | --replace]
    arsse.php export <username> [<file>] 
        [-f | --flat]
    arsse.php --version
    arsse.php -h | --help

The Arsse command-line interface can be used to perform various administrative
tasks such as starting the newsfeed refresh service, managing users, and 
importing or exporting data.

Commands:

    daemon

    Starts the newsfeed refreshing service, which will refresh stale feeds at
    the configured interval automatically.

    feed refresh-all

    Refreshes any stale feeds once, then exits. This performs the same 
    function as the daemon command without looping; this is useful if use of
    a scheduler such a cron is preferred over a persitent service.

    feed refresh <n>

    Refreshes a single feed by numeric ID. This is principally for internal
    use as the feed ID numbers are not usually exposed to the user.

    conf save-defaults [<file>]

    Prints default configuration parameters to standard output, or to <file>
    if specified. Each parameter is annotated with a short description of its
    purpose and usage.

    user [list]

    Prints a list of all existing users, one per line.

    user add <username> [<password>]

    Adds the user specified by <username>, with the provided password
    <password>. If no password is specified, a random password will be
    generated and printed to standard output.

    user remove <username>

    Removes the user specified by <username>. Data related to the user, 
    including folders and subscriptions, are immediately deleted. Feeds to
    which the user was subscribed will be retained and refreshed until the
    configured retention time elapses.

    user set-pass <username> [<password>]

    Changes <username>'s password to <password>. If no password is specified,
    a random password will be generated and printed to standard output.

    The --oldpass=<pass> option can be used to supply a user's exiting 
    password if this is required by the authentication driver to change a
    password. Currently this is not used by any existing driver.
    
    The --fever option sets a user's Fever protocol password instead of their
    general password. As Fever requires that passwords be stored insecurely,
    users do not have Fever passwords by default, and logging in to the Fever
    protocol is disabled until a password is set. It is highly recommended
    that a user's Fever password be different from their general password.

    user unset-pass <username>

    Unsets a user's password, effectively disabling their account. As with
    password setting, the --oldpass and --fever options may be used.

    user auth <username> <password>

    Tests logging in as <username> with password <password>. This only checks
    that the user's password is correctly recognized; it has no side effects.

    The --fever option may be used to test the user's Fever protocol password,
    if any.

    import <username> [<file>]

    Imports the feeds, folders, and tags found in the OPML formatted <file>
    into the account of <username>. If no file is specified, data is instead
    read from standard input.

    The --replace option interprets the OPML file as the list of all desired 
    feeds, folders and tags, performing any deletion or moving of existing 
    entries which do not appear in the flle. If this option is not specified,
    the file is assumed to list desired additions only.

    The --flat option can be used to ignore any folder structures in the file,
    importing any feeds only into the root folder.

    export <username> [<file>]

    Exports <username>'s feeds, folders, and tags to the OPML file specified
    by <file>, or standard output if none is provided. Note that due to a 
    limitation of the OPML format, any commas present in tag names will not be
    retained in the export.

    The --flat option can be used to omit folders from the export. Some OPML
    implementations may not support folders, or arbitrary nesting; this option
    may be used when planning to import into such software.
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
            $cmd = $this->command(["-h", "--help", "--version", "daemon", "feed refresh", "feed refresh-all", "conf save-defaults", "user", "export", "import"], $args);
            if ($cmd && !in_array($cmd, ["-h", "--help", "--version", "conf save-defaults"])) {
                // only certain commands don't require configuration to be loaded
                $this->loadConf();
            }
            switch ($cmd) {
                case "-h":
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
                    return (int) !$this->getInstance(OPML::class)->exportFile($file, $u, ($args['--flat'] || $args['-f']));
                case "import":
                    $u = $args['<username>'];
                    $file = $this->resolveFile($args['<file>'], "r");
                    return (int) !$this->getInstance(OPML::class)->importFile($file, $u, ($args['--flat'] || $args['-f']), ($args['--replace'] || $args['-r']));
            }
        } catch (AbstractException $e) {
            $this->logError($e->getMessage());
            return $e->getCode();
        }
    } // @codeCoverageIgnore

    /** @codeCoverageIgnore */
    protected function logError(string $msg) {
        fwrite(STDERR, $msg.\PHP_EOL);
    }

    /** @codeCoverageIgnore */
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
                // no break
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
    } // @codeCoverageIgnore

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
