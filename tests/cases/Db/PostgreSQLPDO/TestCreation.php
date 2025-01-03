<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Db\PostgreSQLPDO;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\PostgreSQL\PDODriver as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group("slow")]
#[CoversClass(\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::class)]
class TestCreation extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp(): void {
        if (!Driver::requirementsMet()) {
            $this->markTestSkipped("PDO-PostgreSQL extension not loaded");
        }
    }

    #[DataProvider("provideConnectionStrings")]
    public function testGenerateConnectionString(bool $pdo, string $user, string $pass, string $db, string $host, int $port, string $service, string $exp): void {
        self::setConf();
        $timeout = (string) ceil(Arsse::$conf->dbTimeoutConnect ?? 0);
        $postfix = "application_name='arsse' client_encoding='UTF8' connect_timeout='$timeout'";
        $act = Driver::makeConnectionString($pdo, $user, $pass, $db, $host, $port, $service);
        if ($act === $postfix) {
            $this->assertSame($exp, "");
        } else {
            $test = substr($act, 0, strlen($act) - (strlen($postfix) + 1));
            $check = substr($act, strlen($test) + 1);
            $this->assertSame($postfix, $check);
            $this->assertSame($exp, $test);
        }
    }

    public static function provideConnectionStrings(): iterable {
        return [
            [false, "arsse",           "secret",   "arsse",     "",          5432, "",      "dbname='arsse' password='secret' user='arsse'"],
            [false, "arsse",           "p word",   "arsse",     "",          5432, "",      "dbname='arsse' password='p word' user='arsse'"],
            [false, "arsse",           "p'word",   "arsse",     "",          5432, "",      "dbname='arsse' password='p\\'word' user='arsse'"],
            [false, "arsse user",      "secret",   "arsse db",  "",          5432, "",      "dbname='arsse db' password='secret' user='arsse user'"],
            [false, "arsse",           "secret",   "",          "",          5432, "",      "password='secret' user='arsse'"],
            [false, "arsse",           "secret",   "arsse",     "localhost", 5432, "",      "dbname='arsse' host='localhost' password='secret' user='arsse'"],
            [false, "arsse",           "secret",   "arsse",     "",          9999, "",      "dbname='arsse' password='secret' port='9999' user='arsse'"],
            [false, "arsse",           "secret",   "arsse",     "localhost", 9999, "",      "dbname='arsse' host='localhost' password='secret' port='9999' user='arsse'"],
            [false, "arsse",           "secret",   "arsse",     "/socket",   9999, "",      "dbname='arsse' host='/socket' password='secret' user='arsse'"],
            [false, "T'Pau of Vulcan", "",         "",          "",          5432, "",      "user='T\\'Pau of Vulcan'"],
            [false, "T'Pau of Vulcan", "superman", "datumbase", "somehost",  2112, "arsse", "service='arsse'"],
            [true,  "arsse",           "secret",   "arsse",     "",          5432, "",      "dbname='arsse'"],
            [true,  "arsse",           "p word",   "arsse",     "",          5432, "",      "dbname='arsse'"],
            [true,  "arsse",           "p'word",   "arsse",     "",          5432, "",      "dbname='arsse'"],
            [true,  "arsse user",      "secret",   "arsse db",  "",          5432, "",      "dbname='arsse db'"],
            [true,  "arsse",           "secret",   "",          "",          5432, "",      ""],
            [true,  "arsse",           "secret",   "arsse",     "localhost", 5432, "",      "dbname='arsse' host='localhost'"],
            [true,  "arsse",           "secret",   "arsse",     "",          9999, "",      "dbname='arsse' port='9999'"],
            [true,  "arsse",           "secret",   "arsse",     "localhost", 9999, "",      "dbname='arsse' host='localhost' port='9999'"],
            [true,  "arsse",           "secret",   "arsse",     "/socket",   9999, "",      "dbname='arsse' host='/socket'"],
            [true,  "T'Pau of Vulcan", "",         "",          "",          5432, "",      ""],
            [true,  "T'Pau of Vulcan", "superman", "datumbase", "somehost",  2112, "arsse", "service='arsse'"],
        ];
    }

    public function testFailToConnect(): void {
        // PDO dies not distinguish between different connection failure modes
        self::setConf([
            'dbPostgreSQLHost' => "example.invalid",
        ]);
        $this->assertException("connectionFailure", "Db");
        new Driver;
    }
}
