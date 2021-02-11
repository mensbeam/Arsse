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
    arsse.php daemon
    arsse.php feed refresh-all
    arsse.php feed refresh <n>
    arsse.php conf save-defaults [<file>]
    arsse.php user [list]
    arsse.php user add <username> [<password>] [--admin]
    arsse.php user remove <username>
    arsse.php user show <username>
    arsse.php user set <username> <property> <value>
    arsse.php user unset <username> <property>
    arsse.php user set-pass <username> [<password>]
        [--oldpass=<pass>] [--fever]
    arsse.php user unset-pass <username>
        [--oldpass=<pass>] [--fever]
    arsse.php user auth <username> <password> [--fever]
    arsse.php token list <username>
    arsse.php token create <username> [<label>]
    arsse.php token revoke <username> [<token>]
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

    user add <username> [<password>] [--admin]

    Adds the user specified by <username>, with the provided password
    <password>. If no password is specified, a random password will be
    generated and printed to standard output. The --admin option will make 
    the user an administrator, which allows them to manage users via the 
    Miniflux protocol, among other things.

    user remove <username>

    Removes the user specified by <username>. Data related to the user, 
    including folders and subscriptions, are immediately deleted. Feeds to
    which the user was subscribed will be retained and refreshed until the
    configured retention time elapses.

    user show <username>

    Displays the metadata of a user in a basic tabular format. See below for
    details on the various properties displayed.

    user set <username> <property> <value>

    Sets a user's metadata property to the supplied value. See below for
    details on the various properties available.

    user unset <username> <property>

    Sets a user's metadata property to its default value. See below for
    details on the various properties available. What the default value
    for a property evaluates to depends on which protocol is used. 

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

    token list <username>

    Lists available tokens for <username> in a simple tabular format. These 
    tokens act as an alternative means of authentication for the Miniflux
    protocol and may be required by some clients. They do not expire.

    token create <username> [<label>]

    Creates a new login token for <username> and prints it. These tokens act
    as an alternative means of authentication for the Miniflux protocol and
    may be required by some clients. An optional label may be specified to 
    give the token a meaningful name.

    token revoke <username> [<token>]

    Deletes the specified token from the database. The token itself must be
    supplied, not its label. If it is omitted all tokens are revoked.

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

User metadata:

    User metadata are primarily used by the Miniflux protocol, and most 
    properties have identical or similar names to those used by Miniflux.
    Properties may also affect other protocols, or conversely may have no
    effect even when using the Miniflux protocol; this is noted below when
    appropriate.
    
    Booleans accept any of the values true/false, 1/0, yes/no, on/off.
    
    The following metadata properties exist for each user:

    num
        Integer. The numeric identifier of the user. This is assigned at user
        creation and is read-only.
    admin
        Boolean. Whether the user is an administrator. Administrators may
        manage other users via the Miniflux protocol, and also may trigger
        feed updates manually via the Nextcloud News protocol.
    lang
        String. The preferred language of the user, as a BCP 47 language tag
        e.g. "en-ca". Note that since The Arsse currently only includes 
        English text it is not used by The Arsse itself, but clients may
        use this metadatum in protocols which expose it.
    tz
        String. The time zone of the user, as a tzdata identifier e.g.
        "America/Los_Angeles". 
    root_folder_name
        String. The name of the root folder, in protocols which allow it to
        be renamed. 
    sort_asc
        Boolean. Whether the user prefers ascending sort order for articles.
        Descending order is usually the default, but explicitly setting this
        property false will also make a preference for descending order 
        explicit.
    theme
        String. The user's preferred theme. This is not used by The Arsse
        itself, but clients may use this metadatum in protocols which expose
        it.
    page_size
        Integer. The user's preferred page size when listing articles. This is
        not used by The Arsse itself, but clients may use this metadatum in 
        protocols which expose it.
    shortcuts
        Boolean. Whether to enable keyboard shortcuts. This is not used by 
        The Arsse itself, but clients may use this metadatum in protocols which
        expose it.
    gestures
        Boolean. Whether to enable touch gestures. This is not used by 
        The Arsse itself, but clients may use this metadatum in protocols which
        expose it.
    reading_time
        Boolean. Whether to calculate and display the estimated reading time
        for articles. Currently The Arsse does not calculate reading time, so
        changing this will likely have no effect.
    stylesheet
        String. A user CSS stylesheet. This is not used by  The Arsse itself,
        but clients may use this metadatum in protocols which expose it.
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
            if ($cmd && !in_array($cmd, ["", "conf save-defaults"])) {
                // only certain commands don't require configuration to be loaded
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
                    Arsse::$obj->get(Service::class)->watch(true);
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
}
