<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\CLI;

use GuzzleHttp\Exception\ClientException;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\CLI;
use JKingWeb\Arsse\REST\Fever\User as FeverUser;
use JKingWeb\Arsse\REST\Miniflux\Token as MinifluxToken;
use JKingWeb\Arsse\ImportExport\OPML;

/** @covers \JKingWeb\Arsse\CLI */
class TestCLI extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        self::clearData();
        $this->cli = \Phake::partialMock(CLI::class);
        \Phake::when($this->cli)->logError->thenReturn(null);
        \Phake::when($this->cli)->loadConf->thenReturn(true);
    }

    public function assertConsole(CLI $cli, string $command, int $exitStatus, string $output = "", bool $pattern = false): void {
        $argv = \Clue\Arguments\split($command);
        $output = strlen($output) ? $output.\PHP_EOL : "";
        if ($pattern) {
            $this->expectOutputRegex($output);
        } else {
            $this->expectOutputString($output);
        }
        $this->assertSame($exitStatus, $cli->dispatch($argv));
    }

    public function testPrintVersion(): void {
        $this->assertConsole($this->cli, "arsse.php --version", 0, Arsse::VERSION);
        \Phake::verify($this->cli, \Phake::times(0))->loadConf;
    }

    /** @dataProvider provideHelpText */
    public function testPrintHelp(string $cmd, string $name): void {
        $this->assertConsole($this->cli, $cmd, 0, str_replace("arsse.php", $name, CLI::USAGE));
        \Phake::verify($this->cli, \Phake::times(0))->loadConf;
    }

    public function provideHelpText(): iterable {
        return [
            ["arsse.php --help", "arsse.php"],
            ["arsse     --help", "arsse"],
            ["thearsse  --help", "thearsse"],
            ["arsse.php -h", "arsse.php"],
            ["arsse     -h", "arsse"],
            ["thearsse  -h", "thearsse"],
        ];
    }

    public function testStartTheDaemon(): void {
        $srv = \Phake::mock(Service::class);
        \Phake::when(Arsse::$obj)->get(Service::class)->thenReturn($srv);
        \Phake::when($srv)->watch->thenReturn(new \DateTimeImmutable);
        $this->assertConsole($this->cli, "arsse.php daemon", 0);
        \Phake::verify($this->cli)->loadConf;
        \Phake::verify($srv)->watch(true);
    }

    public function testRefreshAllFeeds(): void {
        $srv = \Phake::mock(Service::class);
        \Phake::when(Arsse::$obj)->get(Service::class)->thenReturn($srv);
        \Phake::when($srv)->watch->thenReturn(new \DateTimeImmutable);
        $this->assertConsole($this->cli, "arsse.php feed refresh-all", 0);
        \Phake::verify($this->cli)->loadConf;
        \Phake::verify($srv)->watch(false);
    }

    /** @dataProvider provideFeedUpdates */
    public function testRefreshAFeed(string $cmd, int $exitStatus, string $output): void {
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->feedUpdate(1, true)->thenReturn(true);
        \Phake::when(Arsse::$db)->feedUpdate(2, true)->thenThrow(new \JKingWeb\Arsse\Feed\Exception("", ['url' => "http://example.com/"], $this->mockGuzzleException(ClientException::class, "", 404)));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
        \Phake::verify($this->cli)->loadConf;
        \Phake::verify(Arsse::$db)->feedUpdate;
    }

    public function provideFeedUpdates(): iterable {
        return [
            ["arsse.php feed refresh 1", 0,     ""],
            ["arsse.php feed refresh 2", 10502, ""],
        ];
    }

    /** @dataProvider provideDefaultConfigurationSaves */
    public function testSaveTheDefaultConfiguration(string $cmd, int $exitStatus, string $file): void {
        $conf = \Phake::mock(Conf::class);
        \Phake::when(Arsse::$obj)->get(Conf::class)->thenReturn($conf);
        \Phake::when($conf)->exportFile("php://output", true)->thenReturn(true);
        \Phake::when($conf)->exportFile("good.conf", true)->thenReturn(true);
        \Phake::when($conf)->exportFile("bad.conf", true)->thenThrow(new \JKingWeb\Arsse\Conf\Exception("fileUnwritable"));
        $this->assertConsole($this->cli, $cmd, $exitStatus);
        \Phake::verify($this->cli, \Phake::times(0))->loadConf;
        \Phake::verify($conf)->exportFile($file, true);
    }

    public function provideDefaultConfigurationSaves(): iterable {
        return [
            ["arsse.php conf save-defaults",           0,     "php://output"],
            ["arsse.php conf save-defaults -",         0,     "php://output"],
            ["arsse.php conf save-defaults good.conf", 0,     "good.conf"],
            ["arsse.php conf save-defaults bad.conf",  10304, "bad.conf"],
        ];
    }

    /** @dataProvider provideUserList */
    public function testListUsers(string $cmd, array $list, int $exitStatus, string $output): void {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("list")->willReturn($list);
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserList(): iterable {
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
    public function testAddAUser(string $cmd, int $exitStatus, string $output): void {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("add")->will($this->returnCallback(function($user, $pass = null) {
            switch ($user) {
                case "john.doe@example.com":
                    throw new \JKingWeb\Arsse\User\ExceptionConflict("alreadyExists");
                case "jane.doe@example.com":
                    return is_null($pass) ? "random password" : $pass;
            }
        }));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserAdditions(): iterable {
        return [
            ["arsse.php user add john.doe@example.com",          10403, ""],
            ["arsse.php user add jane.doe@example.com",          0,     "random password"],
            ["arsse.php user add jane.doe@example.com superman", 0,     ""],
        ];
    }

    public function testAddAUserAsAdministrator(): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("add")->willReturn("random password");
        Arsse::$user->method("propertiesSet")->willReturn([]);
        Arsse::$user->expects($this->exactly(1))->method("add")->with("jane.doe@example.com", null);
        Arsse::$user->expects($this->exactly(1))->method("propertiesSet")->with("jane.doe@example.com", ['admin' => true]);
        $this->assertConsole($this->cli, "arsse.php user add jane.doe@example.com --admin", 0, "random password");
    }

    /** @dataProvider provideUserAuthentication */
    public function testAuthenticateAUser(string $cmd, int $exitStatus, string $output): void {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("auth")->will($this->returnCallback(function($user, $pass) {
            return
                ($user === "john.doe@example.com" && $pass === "secret") ||
                ($user === "jane.doe@example.com" && $pass === "superman")
            ;
        }));
        $fever = \Phake::mock(FeverUser::class);
        \Phake::when(Arsse::$obj)->get(FeverUser::class)->thenReturn($fever);
        \Phake::when($fever)->authenticate->thenReturn(false);
        \Phake::when($fever)->authenticate("john.doe@example.com", "ashalla")->thenReturn(true);
        \Phake::when($fever)->authenticate("jane.doe@example.com", "thx1138")->thenReturn(true);
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserAuthentication(): iterable {
        $l = new \JKingWeb\Arsse\Lang;
        $success = $l("CLI.Auth.Success");
        $failure = $l("CLI.Auth.Failure");
        return [
            ["arsse.php user auth john.doe@example.com secret",          0, $success],
            ["arsse.php user auth john.doe@example.com superman",        1, $failure],
            ["arsse.php user auth jane.doe@example.com secret",          1, $failure],
            ["arsse.php user auth jane.doe@example.com superman",        0, $success],
            ["arsse.php user auth john.doe@example.com ashalla --fever", 0, $success],
            ["arsse.php user auth john.doe@example.com thx1138 --fever", 1, $failure],
            ["arsse.php user auth --fever jane.doe@example.com ashalla", 1, $failure],
            ["arsse.php user auth --fever jane.doe@example.com thx1138", 0, $success],
        ];
    }

    /** @dataProvider provideUserRemovals */
    public function testRemoveAUser(string $cmd, int $exitStatus, string $output): void {
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("remove")->will($this->returnCallback(function($user) {
            if ($user === "john.doe@example.com") {
                return true;
            }
            throw new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist");
        }));
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserRemovals(): iterable {
        return [
            ["arsse.php user remove john.doe@example.com", 0,     ""],
            ["arsse.php user remove jane.doe@example.com", 10402, ""],
        ];
    }

    /** @dataProvider provideUserPasswordChanges */
    public function testChangeAUserPassword(string $cmd, int $exitStatus, string $output): void {
        $passwordChange = function($user, $pass = null) {
            switch ($user) {
                case "jane.doe@example.com":
                    throw new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist");
                case "john.doe@example.com":
                    return is_null($pass) ? "random password" : $pass;
            }
        };
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("passwordSet")->will($this->returnCallback($passwordChange));
        $fever = \Phake::mock(FeverUser::class);
        \Phake::when(Arsse::$obj)->get(FeverUser::class)->thenReturn($fever);
        \Phake::when($fever)->register->thenReturnCallback($passwordChange);
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserPasswordChanges(): iterable {
        return [
            ["arsse.php user set-pass john.doe@example.com",                  0,     "random password"],
            ["arsse.php user set-pass john.doe@example.com superman",         0,     ""],
            ["arsse.php user set-pass jane.doe@example.com",                  10402, ""],
            ["arsse.php user set-pass john.doe@example.com --fever",          0,     "random password"],
            ["arsse.php user set-pass --fever john.doe@example.com superman", 0,     ""],
            ["arsse.php user set-pass jane.doe@example.com --fever",          10402, ""],
        ];
    }

    /** @dataProvider provideUserPasswordClearings */
    public function testClearAUserPassword(string $cmd, int $exitStatus, string $output): void {
        $passwordClear = function($user) {
            switch ($user) {
                case "jane.doe@example.com":
                    throw new \JKingWeb\Arsse\User\ExceptionConflict("doesNotExist");
                case "john.doe@example.com":
                    return true;
            }
        };
        // FIXME: Phake is somehow unable to mock the User class correctly, so we use PHPUnit's mocks instead
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("passwordUnset")->will($this->returnCallback($passwordClear));
        $fever = \Phake::mock(FeverUser::class);
        \Phake::when(Arsse::$obj)->get(FeverUser::class)->thenReturn($fever);
        \Phake::when($fever)->unregister->thenReturnCallback($passwordClear);
        $this->assertConsole($this->cli, $cmd, $exitStatus, $output);
    }

    public function provideUserPasswordClearings(): iterable {
        return [
            ["arsse.php user unset-pass john.doe@example.com",                  0,     ""],
            ["arsse.php user unset-pass jane.doe@example.com",                  10402, ""],
            ["arsse.php user unset-pass john.doe@example.com --fever",          0,     ""],
            ["arsse.php user unset-pass jane.doe@example.com --fever",          10402, ""],
        ];
    }

    /** @dataProvider provideOpmlExports */
    public function testExportToOpml(string $cmd, int $exitStatus, string $file, string $user, bool $flat): void {
        $opml = \Phake::mock(OPML::class);
        \Phake::when(Arsse::$obj)->get(OPML::class)->thenReturn($opml);
        \Phake::when($opml)->exportFile("php://output", $user, $flat)->thenReturn(true);
        \Phake::when($opml)->exportFile("good.opml", $user, $flat)->thenReturn(true);
        \Phake::when($opml)->exportFile("bad.opml", $user, $flat)->thenThrow(new \JKingWeb\Arsse\ImportExport\Exception("fileUnwritable"));
        $this->assertConsole($this->cli, $cmd, $exitStatus);
        \Phake::verify($this->cli)->loadConf;
        \Phake::verify($opml)->exportFile($file, $user, $flat);
    }

    public function provideOpmlExports(): iterable {
        return [
            ["arsse.php export john.doe@example.com",                  0,     "php://output", "john.doe@example.com", false],
            ["arsse.php export john.doe@example.com -",                0,     "php://output", "john.doe@example.com", false],
            ["arsse.php export john.doe@example.com good.opml",        0,     "good.opml",    "john.doe@example.com", false],
            ["arsse.php export john.doe@example.com bad.opml",         10604, "bad.opml",     "john.doe@example.com", false],
            ["arsse.php export john.doe@example.com --flat",           0,     "php://output", "john.doe@example.com", true],
            ["arsse.php export john.doe@example.com - --flat",         0,     "php://output", "john.doe@example.com", true],
            ["arsse.php export --flat john.doe@example.com good.opml", 0,     "good.opml",    "john.doe@example.com", true],
            ["arsse.php export john.doe@example.com bad.opml --flat",  10604, "bad.opml",     "john.doe@example.com", true],
            ["arsse.php export jane.doe@example.com",                  0,     "php://output", "jane.doe@example.com", false],
            ["arsse.php export jane.doe@example.com -",                0,     "php://output", "jane.doe@example.com", false],
            ["arsse.php export jane.doe@example.com good.opml",        0,     "good.opml",    "jane.doe@example.com", false],
            ["arsse.php export jane.doe@example.com bad.opml",         10604, "bad.opml",     "jane.doe@example.com", false],
            ["arsse.php export jane.doe@example.com --flat",           0,     "php://output", "jane.doe@example.com", true],
            ["arsse.php export jane.doe@example.com - --flat",         0,     "php://output", "jane.doe@example.com", true],
            ["arsse.php export --flat jane.doe@example.com good.opml", 0,     "good.opml",    "jane.doe@example.com", true],
            ["arsse.php export jane.doe@example.com bad.opml --flat",  10604, "bad.opml",     "jane.doe@example.com", true],
            ["arsse.php export john.doe@example.com -f",               0,     "php://output", "john.doe@example.com", true],
            ["arsse.php export john.doe@example.com - -f",             0,     "php://output", "john.doe@example.com", true],
            ["arsse.php export -f john.doe@example.com good.opml",     0,     "good.opml",    "john.doe@example.com", true],
            ["arsse.php export john.doe@example.com bad.opml -f",      10604, "bad.opml",     "john.doe@example.com", true],
            ["arsse.php export jane.doe@example.com -f",               0,     "php://output", "jane.doe@example.com", true],
            ["arsse.php export jane.doe@example.com - -f",             0,     "php://output", "jane.doe@example.com", true],
            ["arsse.php export -f jane.doe@example.com good.opml",     0,     "good.opml",    "jane.doe@example.com", true],
            ["arsse.php export jane.doe@example.com bad.opml -f",      10604, "bad.opml",     "jane.doe@example.com", true],
        ];
    }

    /** @dataProvider provideOpmlImports */
    public function testImportFromOpml(string $cmd, int $exitStatus, string $file, string $user, bool $flat, bool $replace): void {
        $opml = \Phake::mock(OPML::class);
        \Phake::when(Arsse::$obj)->get(OPML::class)->thenReturn($opml);
        \Phake::when($opml)->importFile("php://input", $user, $flat, $replace)->thenReturn(true);
        \Phake::when($opml)->importFile("good.opml", $user, $flat, $replace)->thenReturn(true);
        \Phake::when($opml)->importFile("bad.opml", $user, $flat, $replace)->thenThrow(new \JKingWeb\Arsse\ImportExport\Exception("fileUnreadable"));
        $this->assertConsole($this->cli, $cmd, $exitStatus);
        \Phake::verify($this->cli)->loadConf;
        \Phake::verify($opml)->importFile($file, $user, $flat, $replace);
    }

    public function provideOpmlImports(): iterable {
        return [
            ["arsse.php import john.doe@example.com",                            0,     "php://input", "john.doe@example.com", false, false],
            ["arsse.php import john.doe@example.com -",                          0,     "php://input", "john.doe@example.com", false, false],
            ["arsse.php import john.doe@example.com good.opml",                  0,     "good.opml",   "john.doe@example.com", false, false],
            ["arsse.php import john.doe@example.com bad.opml",                   10603, "bad.opml",    "john.doe@example.com", false, false],
            ["arsse.php import john.doe@example.com --flat",                     0,     "php://input", "john.doe@example.com", true,  false],
            ["arsse.php import john.doe@example.com - --flat",                   0,     "php://input", "john.doe@example.com", true,  false],
            ["arsse.php import --flat john.doe@example.com good.opml",           0,     "good.opml",   "john.doe@example.com", true,  false],
            ["arsse.php import john.doe@example.com bad.opml --flat",            10603, "bad.opml",    "john.doe@example.com", true,  false],
            ["arsse.php import jane.doe@example.com",                            0,     "php://input", "jane.doe@example.com", false, false],
            ["arsse.php import jane.doe@example.com -",                          0,     "php://input", "jane.doe@example.com", false, false],
            ["arsse.php import jane.doe@example.com good.opml",                  0,     "good.opml",   "jane.doe@example.com", false, false],
            ["arsse.php import jane.doe@example.com bad.opml",                   10603, "bad.opml",    "jane.doe@example.com", false, false],
            ["arsse.php import jane.doe@example.com --flat",                     0,     "php://input", "jane.doe@example.com", true,  false],
            ["arsse.php import jane.doe@example.com - --flat",                   0,     "php://input", "jane.doe@example.com", true,  false],
            ["arsse.php import --flat jane.doe@example.com good.opml",           0,     "good.opml",   "jane.doe@example.com", true,  false],
            ["arsse.php import jane.doe@example.com bad.opml --flat",            10603, "bad.opml",    "jane.doe@example.com", true,  false],
            ["arsse.php import john.doe@example.com --replace",                  0,     "php://input", "john.doe@example.com", false, true],
            ["arsse.php import john.doe@example.com - -r",                       0,     "php://input", "john.doe@example.com", false, true],
            ["arsse.php import --replace john.doe@example.com good.opml",        0,     "good.opml",   "john.doe@example.com", false, true],
            ["arsse.php import -r john.doe@example.com bad.opml",                10603, "bad.opml",    "john.doe@example.com", false, true],
            ["arsse.php import --replace john.doe@example.com --flat",           0,     "php://input", "john.doe@example.com", true,  true],
            ["arsse.php import -r john.doe@example.com - --flat",                0,     "php://input", "john.doe@example.com", true,  true],
            ["arsse.php import --flat john.doe@example.com good.opml -r",        0,     "good.opml",   "john.doe@example.com", true,  true],
            ["arsse.php import --replace john.doe@example.com bad.opml --flat",  10603, "bad.opml",    "john.doe@example.com", true,  true],
            ["arsse.php import jane.doe@example.com -r ",                        0,     "php://input", "jane.doe@example.com", false, true],
            ["arsse.php import jane.doe@example.com - --replace",                0,     "php://input", "jane.doe@example.com", false, true],
            ["arsse.php import -r jane.doe@example.com good.opml",               0,     "good.opml",   "jane.doe@example.com", false, true],
            ["arsse.php import --replace jane.doe@example.com bad.opml",         10603, "bad.opml",    "jane.doe@example.com", false, true],
            ["arsse.php import jane.doe@example.com --flat -r",                  0,     "php://input", "jane.doe@example.com", true,  true],
            ["arsse.php import jane.doe@example.com - --flat --replace",         0,     "php://input", "jane.doe@example.com", true,  true],
            ["arsse.php import --flat jane.doe@example.com good.opml -r",        0,     "good.opml",   "jane.doe@example.com", true,  true],
            ["arsse.php import jane.doe@example.com bad.opml --replace --flat",  10603, "bad.opml",    "jane.doe@example.com", true,  true],
        ];
    }

    public function testShowMetadataOfAUser(): void {
        $data = [
            'num'              => 42,
            'admin'            => false,
            'lang'             => "en-ca",
            'tz'               => "America/Toronto",
            'root_folder_name' => null,
            'sort_asc'         => true,
            'theme'            => null,
            'page_size'        => 50,
            'shortcuts'        => true,
            'gestures'         => null,
            'reading_time'     => false,
            'stylesheet'       => "body {color:gray}",
        ];
        $exp = implode(\PHP_EOL, [
            "num               42",
            "admin             false",
            "lang              'en-ca'",
            "tz                'America/Toronto'",
            "root_folder_name  NULL",
            "sort_asc          true",
            "theme             NULL",
            "page_size         50",
            "shortcuts         true",
            "gestures          NULL",
            "reading_time      false",
            "stylesheet        'body {color:gray}'",
        ]);
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesGet")->willReturn($data);
        Arsse::$user->expects($this->once())->method("propertiesGet")->with("john.doe@example.com", true);
        $this->assertConsole($this->cli, "arsse.php user show john.doe@example.com", 0, $exp);
    }

    /** @dataProvider provideMetadataChanges */
    public function testSetMetadataOfAUser(string $cmd, string $user, array $in, array $out, int $exp): void {
        Arsse::$user = $this->createMock(User::class);
        Arsse::$user->method("propertiesSet")->willReturn($out);
        Arsse::$user->expects($this->once())->method("propertiesSet")->with($user, $in);
        $this->assertConsole($this->cli, $cmd, $exp, "");
    }

    public function provideMetadataChanges(): iterable {
        return [
            ["arsse.php user set john admin true", "john", ['admin' => "true"], ['admin' => "true"], 0],
            ["arsse.php user set john bogus 1",    "john", ['bogus' => "1"],    [],                  1],
            ["arsse.php user unset john admin",    "john", ['admin' => null],   ['admin' => null],   0],
            ["arsse.php user unset john bogus",    "john", ['bogus' => null],   [],                  1],
        ];
    }

    public function testListTokens(): void {
        $data = [
            ['label' => 'Ook', 'id' => "TOKEN 1"],
            ['label' => 'Eek', 'id' => "TOKEN 2"],
            ['label' => null,  'id' => "TOKEN 3"],
            ['label' => 'Ack', 'id' => "TOKEN 4"],
        ];
        $exp = implode(\PHP_EOL, [
            "TOKEN 3  ",
            "TOKEN 4  Ack",
            "TOKEN 2  Eek",
            "TOKEN 1  Ook",
        ]);
        $t = \Phake::mock(MinifluxToken::class);
        \Phake::when(Arsse::$obj)->get(MinifluxToken::class)->thenReturn($t);
        \Phake::when($t)->tokenList->thenReturn($data);
        $this->assertConsole($this->cli, "arsse.php token list john", 0, $exp);
        \Phake::verify($t)->tokenList("john");
    }

    public function testCreateToken(): void {
        $t = \Phake::mock(MinifluxToken::class);
        \Phake::when(Arsse::$obj)->get(MinifluxToken::class)->thenReturn($t);
        \Phake::when($t)->tokenGenerate->thenReturn("RANDOM TOKEN");
        $this->assertConsole($this->cli, "arse.php token create jane", 0, "RANDOM TOKEN");
        \Phake::verify($t)->tokenGenerate("jane", null);
    }

    public function testCreateTokenWithLabel(): void {
        $t = \Phake::mock(MinifluxToken::class);
        \Phake::when(Arsse::$obj)->get(MinifluxToken::class)->thenReturn($t);
        \Phake::when($t)->tokenGenerate->thenReturn("RANDOM TOKEN");
        $this->assertConsole($this->cli, "arse.php token create jane Ook", 0, "RANDOM TOKEN");
        \Phake::verify($t)->tokenGenerate("jane", "Ook");
    }

    public function testRevokeAToken(): void {
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->tokenRevoke->thenReturn(true);
        $this->assertConsole($this->cli, "arse.php token revoke jane TOKEN_ID", 0);
        \Phake::verify(Arsse::$db)->tokenRevoke("jane", "miniflux.login", "TOKEN_ID");
    }

    public function testRevokeAllTokens(): void {
        Arsse::$db = \Phake::mock(Database::class);
        \Phake::when(Arsse::$db)->tokenRevoke->thenReturn(true);
        $this->assertConsole($this->cli, "arse.php token revoke jane", 0);
        \Phake::verify(Arsse::$db)->tokenRevoke("jane", "miniflux.login", null);
    }
}
