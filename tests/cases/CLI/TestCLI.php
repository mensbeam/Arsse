<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\CLI;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\CLI;
use Phake;

/** @covers \JKingWeb\Arsse\CLI */
class TestCLI extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        self::clearData(false);
        $this->cli = Phake::partialMock(CLI::class);
        Phake::when($this->cli)->logError->thenReturn(null);
    }

    public function assertConsole(CLI $cli, string $command, int $exitStatus, string $output = "", bool $pattern = false) {
        $argv = \Clue\Arguments\split($command);
        $output = strlen($output) ? $output.\PHP_EOL : "";
        if ($pattern) {
            $this->expectOutputRegex($output);
        } else {
            $this->expectOutputString($output);
        }
        $this->assertSame($exitStatus, $cli->dispatch($argv));
    }

    public function assertLoaded(bool $loaded) {
        $r = new \ReflectionClass(Arsse::class);
        $props = array_keys($r->getStaticProperties());
        foreach ($props as $prop) {
            if ($loaded) {
                $this->assertNotNull(Arsse::$$prop, "Global $prop object should be loaded");
            } else {
                $this->assertNull(Arsse::$$prop, "Global $prop object should not be loaded");
            }
        }
    }

    public function testPrintVersion() {
        $this->assertConsole($this->cli, "arsse.php --version", 0, Arsse::VERSION);
        $this->assertLoaded(false);
    }

    /** @dataProvider provideHelpText */
    public function testPrintHelp(string $cmd, string $name) {
        $this->assertConsole($this->cli, $cmd, 0, str_replace("arsse.php", $name, CLI::USAGE));
        $this->assertLoaded(false);
    }

    public function provideHelpText() {
        return [
            ["arsse.php --help", "arsse.php"],
            ["arsse     --help", "arsse"],
            ["thearsse  --help", "thearsse"],
        ];
    }

    public function testStartTheDaemon() {
        $srv = Phake::mock(Service::class);
        Phake::when($srv)->watch->thenReturn(new \DateTimeImmutable);
        Phake::when($this->cli)->getService->thenReturn($srv);
        $this->assertConsole($this->cli, "arsse.php daemon", 0);
        $this->assertLoaded(true);
        Phake::verify($srv)->watch(true);
        Phake::verify($this->cli)->getService;
    }

    /** @dataProvider provideFeedUpdates */
    public function testRefreshAFeed(string $cmd, int $exitStatus, string $output) {
        Arsse::$db = Phake::mock(Database::class);
        Phake::when(Arsse::$db)->feedUpdate(1, true)->thenReturn(true);
        Phake::when(Arsse::$db)->feedUpdate(2, true)->thenThrow(new \JKingWeb\Arsse\Feed\Exception("http://example.com/", new \PicoFeed\Client\InvalidUrlException));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
        $this->assertLoaded(true);
        Phake::verify(Arsse::$db)->feedUpdate;
    }

    public function provideFeedUpdates() {
        return [
            ["arsse.php feed refresh 1", 0,     ""],
            ["arsse.php feed refresh 2", 10502, ""],
        ];
    }

    /** @dataProvider provideDefaultConfigurationSaves */
    public function testSaveTheDefaultConfiguration(string $cmd, int $exitStatus, string $file) {
        $conf = Phake::mock(Conf::class);
        Phake::when($conf)->exportFile("php://output", true)->thenReturn(true);
        Phake::when($conf)->exportFile("good.conf", true)->thenReturn(true);
        Phake::when($conf)->exportFile("bad.conf", true)->thenThrow(new \JKingWeb\Arsse\Conf\Exception("fileUnwritable"));
        Phake::when($this->cli)->getConf->thenReturn($conf);
        $this->assertConsole($this->cli, $cmd, $exitStatus);
        $this->assertLoaded(false);
        Phake::verify($conf)->exportFile($file, true);
    }

    public function provideDefaultConfigurationSaves() {
        return [
            ["arsse.php conf save-defaults",           0,     "php://output"],
            ["arsse.php conf save-defaults -",         0,     "php://output"],
            ["arsse.php conf save-defaults good.conf", 0,     "good.conf"],
            ["arsse.php conf save-defaults bad.conf",  10304, "bad.conf"],
        ];
    }

    /** @dataProvider provideUserList */
    public function testListUsers(string $cmd, array $list, int $exitStatus, string $output) {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("list")->willReturn($list);
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserList() {
        $list = ["john.doe@example.com", "jane.doe@example.com"];
        $str = implode(PHP_EOL, $list);
        return [
            ["arsse.php user list", $list, 0, $str],
            ["arsse.php user",      $list, 0, $str],
            ["arsse.php user list", [],    0, ""],
            ["arsse.php user",      [],    0, ""],
        ];
    }

    /** @dataProvider provideUserAdditions */
    public function testAddAUser(string $cmd, int $exitStatus, string $output) {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("add")->will($this->returnCallback(function($user, $pass = null) {
            switch ($user) {
                case "john.doe@example.com":
                    throw new \JKingWeb\Arsse\User\Exception("alreadyExists");
                case "jane.doe@example.com":
                    return is_null($pass) ? "random password" : $pass;
            }
        }));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserAdditions() {
        return [
            ["arsse.php user add john.doe@example.com",          10403, ""],
            ["arsse.php user add jane.doe@example.com",          0,     "random password"],
            ["arsse.php user add jane.doe@example.com superman", 0,     ""],
        ];
    }

    /** @dataProvider provideUserAuthentication */
    public function testAuthenticateAUser(string $cmd, int $exitStatus, string $output) {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("auth")->will($this->returnCallback(function($user, $pass) {
            return (
                ($user === "john.doe@example.com" && $pass === "secret") ||
                ($user === "jane.doe@example.com" && $pass === "superman")
            );
        }));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserAuthentication() {
        $l = new \JKingWeb\Arsse\Lang;
        return [
            ["arsse.php user auth john.doe@example.com secret",     0, $l("CLI.Auth.Success")],
            ["arsse.php user auth john.doe@example.com superman",   1, $l("CLI.Auth.Failure")],
            ["arsse.php user auth jane.doe@example.com secret",     1, $l("CLI.Auth.Failure")],
            ["arsse.php user auth jane.doe@example.com superman",   0, $l("CLI.Auth.Success")],
        ];
    }

    /** @dataProvider provideUserRemovals */
    public function testRemoveAUser(string $cmd, int $exitStatus, string $output) {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("remove")->will($this->returnCallback(function($user) {
            if ($user === "john.doe@example.com") {
                return true;
            }
            throw new \JKingWeb\Arsse\User\Exception("doesNotExist");
        }));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserRemovals() {
        return [
            ["arsse.php user remove john.doe@example.com", 0,     ""],
            ["arsse.php user remove jane.doe@example.com", 10402, ""],
        ];
    }

    /** @dataProvider provideUserPasswordChanges */
    public function testChangeAUserPassword(string $cmd, int $exitStatus, string $output) {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("passwordSet")->will($this->returnCallback(function($user, $pass = null) {
            switch ($user) {
                case "jane.doe@example.com":
                    throw new \JKingWeb\Arsse\User\Exception("doesNotExist");
                case "john.doe@example.com":
                    return is_null($pass) ? "random password" : $pass;
            }
        }));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserPasswordChanges() {
        return [
            ["arsse.php user set-pass john.doe@example.com",          0,     "random password"],
            ["arsse.php user set-pass john.doe@example.com superman", 0,     ""],
            ["arsse.php user set-pass jane.doe@example.com",          10402, ""],
        ];
    }
}
