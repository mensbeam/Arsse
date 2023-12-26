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
use GetOpt\GetOpt;
use GetOpt\Command;
use GetOpt\Operand;
use GetOpt\Option;
use GetOpt\ArgumentException;

class CLI {
    protected $cli;

    public function __construct() {
        $cli = new GetOpt([], []);
        $cli->addOptions([
            Option::create("h", "help"),
            Option::create(null, "version"),
        ]);
        $cli->addCommands([
            Command::create("user list", null),
            Command::create("user add", null)
                ->addOperand(Operand::create("username", operand::REQUIRED))
                ->addOperand(Operand::create("password", Operand::OPTIONAL))
                ->addOption(Option::create(null, "admin")),
            Command::create("user remove", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED)),
            Command::create("user show", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED)),
            Command::create("user set", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED))
                ->addOperand(Operand::create("property", Operand::REQUIRED))
                ->addOperand(Operand::create("value", Operand::REQUIRED)),
            Command::create("user unset", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED))
                ->addOperand(Operand::create("property", Operand::REQUIRED)),
            Command::create("user set-pass", null)
                ->addOperand(Operand::create("username", operand::REQUIRED))
                ->addOperand(Operand::create("password", Operand::OPTIONAL))
                ->addOption(Option::create(null, "fever")),
            Command::create("user unset-pass", null)
                ->addOperand(Operand::create("username", operand::REQUIRED))
                ->addOption(Option::create(null, "fever")),
            Command::create("user auth", null)
                ->addOperand(Operand::create("username", operand::REQUIRED))
                ->addOperand(Operand::create("password", Operand::REQUIRED))
                ->addOption(Option::create(null, "fever")),
            Command::create("token list", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED)),
            Command::create("token create", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED))
                ->addOperand(Operand::create("label", Operand::OPTIONAL)),
            Command::create("token revoke", null)
                ->addOperand(Operand::create("username", Operand::REQUIRED))
                ->addOperand(Operand::create("token", Operand::OPTIONAL)),
            Command::create("import", null)
                ->addOperand(Operand::create("username", operand::REQUIRED))
                ->addOperand(Operand::create("file", Operand::OPTIONAL))
                ->addOption(Option::create("f", "flat"))
                ->addOption(Option::create("r", "replace")),
            Command::create("export", null)
                ->addOperand(Operand::create("username", operand::REQUIRED))
                ->addOperand(Operand::create("file", Operand::OPTIONAL))
                ->addOption(Option::create("f", "flat")),
            Command::create("daemon", null)
                ->addOption(Option::create(null, "fork", GetOpt::REQUIRED_ARGUMENT)->setArgumentName("pidfile")),
            Command::create("feed refresh-all", null),
            Command::create("feed refresh", null)
                ->addOperand(Operand::create("n", Operand::REQUIRED)),
            Command::create("conf save-defaults", null)
                ->addOperand(Operand::create("file", Operand::OPTIONAL)),
        ]);
        $this->cli = $cli;
    }

    public function usage(string $prog, bool $help = false): string {
        $cli = $this->cli;
        $out = "Usage:\n";
        foreach ($cli->getCommands() as $cmd) {
            $out .= "   $prog ".$cmd->getName();
            foreach ($cmd->getOperands() as $op) {
                $rep = "<".$op->getName().">";
                if (!$op->isRequired()) {
                    $rep = "[$rep]";
                }
                $out .= " $rep";
            }
            foreach ($cmd->getOptions() as $op) {
                $arg = $op->getArgument();
                $rep = "--".$op->getName()."";
                if ($op->getMode() === GetOpt::REQUIRED_ARGUMENT) {
                    $rep = "$rep=".$arg->getName();
                }
                if ($short = $op->getShort()) {
                    $srep = "-$short";
                    $rep = "$srep|$rep";
                }
                $out .= " [$rep]";
            }
            $out .= "\n";
        }
        foreach ($cli->getOptionObjects() as $op) {
            $rep = "--".$op->getName()."";
            if ($short = $op->getShort()) {
                $srep = "-$short";
                $rep = "$srep|$rep";
            }
            $out .= "   $prog $rep\n";
        }
        if ($help) {
            $out .= <<<HELP_TEXT
\nThe Arsse command-line interface can be used to perform various administrative
tasks such as starting the newsfeed refresh service, managing users, and 
importing or exporting data.

See the manual page for more details:

    man arsse\n
HELP_TEXT;
        }
        return $out;
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

    public function dispatch(array $argv = null): int {
        $cli = $this->cli;
        $argv = $argv ?? $_SERVER['argv'];
        $prog = array_shift($argv);
        try {
            $cli->process($argv);
            // ensure the require extensions are loaded
            Arsse::checkExtensions(...Arsse::REQUIRED_EXTENSIONS);
            $cmd = $cli->getCommand();
            $cmd = $cmd ? $cmd->getName() : "";
            if ($cmd && !in_array($cmd, ["", "conf save-defaults", "daemon"])) {
                // only certain commands don't require configuration to be loaded; daemon loads configuration after forking (if applicable)
                $this->loadConf();
            }
            // run the requested command
            switch ($cmd) {
                case "":
                    if ($cli->getOption("version")) {
                        echo Arsse::VERSION.\PHP_EOL;
                    } else {
                        echo $this->usage($prog, (bool) $cli->getOption("help"));
                    }
                    return 0;
                case "daemon":
                    if (($fork = $cli->getOption("fork")) !== null) {
                        return $this->serviceFork($fork);
                    } else {
                        $this->loadConf();
                        Arsse::$obj->get(Service::class)->watch(true);
                    }
                    return 0;
                case "feed refresh":
                    return (int) !Arsse::$db->feedUpdate((int) $cli->getOperand("n"), true);
                case "feed refresh-all":
                    Arsse::$obj->get(Service::class)->watch(false);
                    return 0;
                case "conf save-defaults":
                    $file = $this->resolveFile($cli->getOperand("file"), "w");
                    return (int) !Arsse::$obj->get(Conf::class)->exportFile($file, true);
                case "export":
                    $u = $cli->getOperand("username");
                    $file = $this->resolveFile($cli->getOperand("file"), "w");
                    return (int) !Arsse::$obj->get(OPML::class)->exportFile($file, $u, (bool) $cli->getOption("flat"));
                case "import":
                    $u = $cli->getOperand("username");
                    $file = $this->resolveFile($cli->getOperand("file"), "r");
                    return (int) !Arsse::$obj->get(OPML::class)->importFile($file, $u, (bool) $cli->getOption("flat"), (bool) $cli->getOption("replace"));
                case "token list":
                    return $this->tokenList($cli->getOperand("username"));
                case "token create":
                    echo Arsse::$obj->get(Miniflux::class)->tokenGenerate($cli->getOperand("username"), $cli->getOperand("label")).\PHP_EOL;
                    return 0;
                case "token revoke":
                    Arsse::$db->tokenRevoke($cli->getOperand("username"), "miniflux.login", $cli->getOperand("token"));
                    return 0;
                case "user add":
                    $out = $this->userAddOrSetPassword("add", $cli->getOperand("username"), $cli->getOperand("password"));
                    if ($cli->getOption("admin")) {
                        Arsse::$user->propertiesSet($cli->getOperand("username"), ['admin' => true]);
                    }
                    return $out;
                case "user set-pass":
                    if ($cli->getOption("fever")) {
                        $passwd = Arsse::$obj->get(Fever::class)->register($cli->getOperand("username"), $cli->getOperand("password"));
                        if ($cli->getOperand("password") === null) {
                            echo $passwd.\PHP_EOL;
                        }
                        return 0;
                    } else {
                        return $this->userAddOrSetPassword("passwordSet", $cli->getOperand("username"), $cli->getOperand("password"));
                    }
                    // no break
                case "user unset-pass":
                    if ($cli->getOption("fever")) {
                        Arsse::$obj->get(Fever::class)->unregister($cli->getOperand("username"));
                    } else {
                        Arsse::$user->passwordUnset($cli->getOperand("username"));
                    }
                    return 0;
                case "user remove":
                    return (int) !Arsse::$user->remove($cli->getOperand("username"));
                case "user show":
                    return $this->userShowProperties($cli->getOperand("username"));
                case "user set":
                    return (int) !Arsse::$user->propertiesSet($cli->getOperand("username"), [$cli->getOperand("property") => $cli->getOperand("value")]);
                case "user unset":
                    return (int) !Arsse::$user->propertiesSet($cli->getOperand("username"), [$cli->getOperand("property") => null]);
                case "user auth":
                    return $this->userAuthenticate($cli->getOperand("username"), $cli->getOperand("password"), (bool) $cli->getOption("fever"));
                case "user list":
                    return $this->userList();
                default:
                    throw new Exception("constantUnknown", $cmd); // @codeCoverageIgnore
            }
        } catch (ArgumentException $e) {
            // invalid command was entered
            $this->logError(trim($this->usage($prog)));
            return 1;
        } catch (AbstractException $e) {
            // some other error
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
