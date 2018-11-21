<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Db;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Db\Statement;
use JKingWeb\Arsse\Db\PDOStatement;

/**
 * @covers \JKingWeb\Arsse\Db\SQLite3\Statement<extended>
 * @covers \JKingWeb\Arsse\Db\SQLite3\ExceptionBuilder
 * @covers \JKingWeb\Arsse\Db\PDOStatement<extended>
 * @covers \JKingWeb\Arsse\Db\PDOError */
class TestStatement extends \JKingWeb\Arsse\Test\AbstractTest {
    public function provideDrivers() {
        $this->setConf();
        $drvSqlite3 = (function() {
            if (\JKingWeb\Arsse\Db\SQLite3\Driver::requirementsMet()) {
                $d = new \SQLite3(Arsse::$conf->dbSQLite3File);
                $d->enableExceptions(true);
                return $d;
            }
        })();
        $drvPgsql = (function() {
            if (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::requirementsMet()) {
                $connString = \JKingWeb\Arsse\Db\PostgreSQL\Driver::makeConnectionString(true, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, Arsse::$conf->dbPostgreSQLDb, Arsse::$conf->dbPostgreSQLHost, Arsse::$conf->dbPostgreSQLPort, "");
                $c = new \PDO("pgsql:".$connString, Arsse::$conf->dbPostgreSQLUser, Arsse::$conf->dbPostgreSQLPass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                foreach (\JKingWeb\Arsse\Db\PostgreSQL\PDODriver::makeSetupQueries(Arsse::$conf->dbPostgreSQLSchema) as $q) {
                    $c->exec($q);
                }
                return $c;
            }
        })();
        $drvPdo = (function() {
            if (\JKingWeb\Arsse\Db\SQLite3\PDODriver::requirementsMet()) {
                return new \PDO("sqlite:".Arsse::$conf->dbSQLite3File, "", "", [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            }
        })();
        return [
            'SQLite 3' => [isset($drvSqlite3), false, \JKingWeb\Arsse\Db\SQLite3\Statement::class, function(string $query, array $types = []) use($drvSqlite3) {
                $s = $drvSqlite3->prepare($query);
                return [$drvSqlite3, $s, $types];
            }],
            'PDO SQLite 3' => [isset($drvPdo), true, \JKingWeb\Arsse\Db\PDOStatement::class, function(string $query, array $types = []) use($drvPdo) {
                $s = $drvPdo->prepare($query);
                return [$drvPdo, $s, $types];
            }],
            'PDO PostgreSQL' => [isset($drvPgsql), true, \JKingWeb\Arsse\Db\PDOStatement::class, function(string $query, array $types = []) use($drvPgsql) {
                $s = $drvPgsql->prepare($query);
                return [$drvPgsql, $s, $types];
            }],
        ];
    }

    /** @dataProvider provideDrivers */
    public function testConstructStatement(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $this->assertInstanceOf(Statement::class, new $class(...$func("SELECT ? as value")));
    }

    /** @dataProvider provideBindings */
    public function testBindATypedValue(bool $driverTestable, string $class, \Closure $func, $value, string $type, string $exp) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        if ($exp=="null") {
            $query = "SELECT (cast(? as text) is null) as pass";
        } else {
            $query = "SELECT ($exp = ?) as pass";
        }
        $typeStr = "'".str_replace("'", "''", $type)."'";
        $s = new $class(...$func($query));
        $s->retype(...[$type]);
        $act = $s->run(...[$value])->getValue();
        $this->assertTrue((bool) $act);
    }

    /** @dataProvider provideBinaryBindings */
    public function testHandleBinaryData(bool $driverTestable, string $class, \Closure $func, $value, string $type, string $exp) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        if ($exp=="null") {
            $query = "SELECT (cast(? as text) is null) as pass";
        } else {
            $query = "SELECT ($exp = ?) as pass";
        }
        $typeStr = "'".str_replace("'", "''", $type)."'";
        $s = new $class(...$func($query));
        $s->retype(...[$type]);
        $act = $s->run(...[$value])->getValue();
        $this->assertTrue((bool) $act);
    }

    /** @dataProvider provideDrivers */
    public function testBindMissingValue(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $s = new $class(...$func("SELECT ? as value", ["int"]));
        $val = $s->runArray()->getRow()['value'];
        $this->assertSame(null, $val);
    }

    /** @dataProvider provideDrivers */
    public function testBindMultipleValues(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [
            'one' => 1,
            'two' => 2,
        ];
        $exp = $stringCoersion ? $this->stringify($exp) : $exp;
        $s = new $class(...$func("SELECT ? as one, ? as two", ["int", "int"]));
        $val = $s->runArray([1,2])->getRow();
        $this->assertSame($exp, $val);
    }

    /** @dataProvider provideDrivers */
    public function testBindRecursively(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $exp = [
            'one'   => 1,
            'two'   => 2,
            'three' => 3,
            'four'  => 4,
        ];
        $exp = $stringCoersion ? $this->stringify($exp) : $exp;
        $s = new $class(...$func("SELECT ? as one, ? as two, ? as three, ? as four", ["int", ["int", "int"], "int"]));
        $val = $s->runArray([1, [2, 3], 4])->getRow();
        $this->assertSame($exp, $val);
    }

    /** @dataProvider provideDrivers */
    public function testBindWithoutType(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        $this->assertException("paramTypeMissing", "Db");
        $s = new $class(...$func("SELECT ? as value", []));
        $s->runArray([1]);
    }

    /** @dataProvider provideDrivers */
    public function testViolateConstraint(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        (new $class(...$func("CREATE TABLE if not exists arsse_meta(key varchar(255) primary key not null, value text)")))->run();
        $s = new $class(...$func("INSERT INTO arsse_meta(key) values(?)", ["str"]));
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        $s->runArray([null]);
    }

    /** @dataProvider provideDrivers */
    public function testMismatchTypes(bool $driverTestable, bool $stringCoersion, string $class, \Closure $func) {
        if (!$driverTestable) {
            $this->markTestSkipped();
        }
        (new $class(...$func("CREATE TABLE if not exists arsse_feeds(id integer primary key not null, url text not null)")))->run();
        $s = new $class(...$func("INSERT INTO arsse_feeds(id,url) values(?,?)", ["str", "str"]));
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $s->runArray(['ook', 'eek']);
    }

    public function provideBindings() {
        $dateMutable = new \DateTime("Noon Today", new \DateTimezone("America/Toronto"));
        $dateImmutable = new \DateTimeImmutable("Noon Today", new \DateTimezone("America/Toronto"));
        $dateUTC = new \DateTime("@".$dateMutable->getTimestamp(), new \DateTimezone("UTC"));
        $tests = [
            'Null as integer' => [null, "integer", "null"],
            'Null as float' => [null, "float", "null"],
            'Null as string' => [null, "string", "null"],
            'Null as datetime' => [null, "datetime", "null"],
            'Null as boolean' => [null, "boolean", "null"],
            'Null as strict integer' => [null, "strict integer", "0"],
            'Null as strict float' => [null, "strict float", "0.0"],
            'Null as strict string' => [null, "strict string", "''"],
            'Null as strict datetime' => [null, "strict datetime", "'1970-01-01 00:00:00'"],
            'Null as strict boolean' => [null, "strict boolean", "0"],
            'True as integer' => [true, "integer", "1"],
            'True as float' => [true, "float", "1.0"],
            'True as string' => [true, "string", "'1'"],
            'True as datetime' => [true, "datetime", "null"],
            'True as boolean' => [true, "boolean", "1"],
            'True as strict integer' => [true, "strict integer", "1"],
            'True as strict float' => [true, "strict float", "1.0"],
            'True as strict string' => [true, "strict string", "'1'"],
            'True as strict datetime' => [true, "strict datetime", "'1970-01-01 00:00:00'"],
            'True as strict boolean' => [true, "strict boolean", "1"],
            'False as integer' => [false, "integer", "0"],
            'False as float' => [false, "float", "0.0"],
            'False as string' => [false, "string", "''"],
            'False as datetime' => [false, "datetime", "null"],
            'False as boolean' => [false, "boolean", "0"],
            'False as strict integer' => [false, "strict integer", "0"],
            'False as strict float' => [false, "strict float", "0.0"],
            'False as strict string' => [false, "strict string", "''"],
            'False as strict datetime' => [false, "strict datetime", "'1970-01-01 00:00:00'"],
            'False as strict boolean' => [false, "strict boolean", "0"],
            'Integer as integer' => [2112, "integer", "2112"],
            'Integer as float' => [2112, "float", "2112.0"],
            'Integer as string' => [2112, "string", "'2112'"],
            'Integer as datetime' => [2112, "datetime", "'1970-01-01 00:35:12'"],
            'Integer as boolean' => [2112, "boolean", "1"],
            'Integer as strict integer' => [2112, "strict integer", "2112"],
            'Integer as strict float' => [2112, "strict float", "2112.0"],
            'Integer as strict string' => [2112, "strict string", "'2112'"],
            'Integer as strict datetime' => [2112, "strict datetime", "'1970-01-01 00:35:12'"],
            'Integer as strict boolean' => [2112, "strict boolean", "1"],
            'Integer zero as integer' => [0, "integer", "0"],
            'Integer zero as float' => [0, "float", "0.0"],
            'Integer zero as string' => [0, "string", "'0'"],
            'Integer zero as datetime' => [0, "datetime", "'1970-01-01 00:00:00'"],
            'Integer zero as boolean' => [0, "boolean", "0"],
            'Integer zero as strict integer' => [0, "strict integer", "0"],
            'Integer zero as strict float' => [0, "strict float", "0.0"],
            'Integer zero as strict string' => [0, "strict string", "'0'"],
            'Integer zero as strict datetime' => [0, "strict datetime", "'1970-01-01 00:00:00'"],
            'Integer zero as strict boolean' => [0, "strict boolean", "0"],
            'Float as integer' => [2112.5, "integer", "2112"],
            'Float as float' => [2112.5, "float", "2112.5"],
            'Float as string' => [2112.5, "string", "'2112.5'"],
            'Float as datetime' => [2112.5, "datetime", "'1970-01-01 00:35:12'"],
            'Float as boolean' => [2112.5, "boolean", "1"],
            'Float as strict integer' => [2112.5, "strict integer", "2112"],
            'Float as strict float' => [2112.5, "strict float", "2112.5"],
            'Float as strict string' => [2112.5, "strict string", "'2112.5'"],
            'Float as strict datetime' => [2112.5, "strict datetime", "'1970-01-01 00:35:12'"],
            'Float as strict boolean' => [2112.5, "strict boolean", "1"],
            'Float zero as integer' => [0.0, "integer", "0"],
            'Float zero as float' => [0.0, "float", "0.0"],
            'Float zero as string' => [0.0, "string", "'0'"],
            'Float zero as datetime' => [0.0, "datetime", "'1970-01-01 00:00:00'"],
            'Float zero as boolean' => [0.0, "boolean", "0"],
            'Float zero as strict integer' => [0.0, "strict integer", "0"],
            'Float zero as strict float' => [0.0, "strict float", "0.0"],
            'Float zero as strict string' => [0.0, "strict string", "'0'"],
            'Float zero as strict datetime' => [0.0, "strict datetime", "'1970-01-01 00:00:00'"],
            'Float zero as strict boolean' => [0.0, "strict boolean", "0"],
            'ASCII string as integer' => ["Random string", "integer", "0"],
            'ASCII string as float' => ["Random string", "float", "0.0"],
            'ASCII string as string' => ["Random string", "string", "'Random string'"],
            'ASCII string as datetime' => ["Random string", "datetime", "null"],
            'ASCII string as boolean' => ["Random string", "boolean", "1"],
            'ASCII string as strict integer' => ["Random string", "strict integer", "0"],
            'ASCII string as strict float' => ["Random string", "strict float", "0.0"],
            'ASCII string as strict string' => ["Random string", "strict string", "'Random string'"],
            'ASCII string as strict datetime' => ["Random string", "strict datetime", "'1970-01-01 00:00:00'"],
            'ASCII string as strict boolean' => ["Random string", "strict boolean", "1"],
            'UTF-8 string as integer' => ["\u{e9}", "integer", "0"],
            'UTF-8 string as float' => ["\u{e9}", "float", "0.0"],
            'UTF-8 string as string' => ["\u{e9}", "string", "char(233)"],
            'UTF-8 string as datetime' => ["\u{e9}", "datetime", "null"],
            'UTF-8 string as boolean' => ["\u{e9}", "boolean", "1"],
            'UTF-8 string as strict integer' => ["\u{e9}", "strict integer", "0"],
            'UTF-8 string as strict float' => ["\u{e9}", "strict float", "0.0"],
            'UTF-8 string as strict string' => ["\u{e9}", "strict string", "char(233)"],
            'UTF-8 string as strict datetime' => ["\u{e9}", "strict datetime", "'1970-01-01 00:00:00'"],
            'UTF-8 string as strict boolean' => ["\u{e9}", "strict boolean", "1"],
            'ISO 8601 string as integer' => ["2017-01-09T13:11:17", "integer", "0"],
            'ISO 8601 string as float' => ["2017-01-09T13:11:17", "float", "0.0"],
            'ISO 8601 string as string' => ["2017-01-09T13:11:17", "string", "'2017-01-09T13:11:17'"],
            'ISO 8601 string as datetime' => ["2017-01-09T13:11:17", "datetime", "'2017-01-09 13:11:17'"],
            'ISO 8601 string as boolean' => ["2017-01-09T13:11:17", "boolean", "1"],
            'ISO 8601 string as strict integer' => ["2017-01-09T13:11:17", "strict integer", "0"],
            'ISO 8601 string as strict float' => ["2017-01-09T13:11:17", "strict float", "0.0"],
            'ISO 8601 string as strict string' => ["2017-01-09T13:11:17", "strict string", "'2017-01-09T13:11:17'"],
            'ISO 8601 string as strict datetime' => ["2017-01-09T13:11:17", "strict datetime", "'2017-01-09 13:11:17'"],
            'ISO 8601 string as strict boolean' => ["2017-01-09T13:11:17", "strict boolean", "1"],
            'Arbitrary date string as integer' => ["Today", "integer", "0"],
            'Arbitrary date string as float' => ["Today", "float", "0.0"],
            'Arbitrary date string as string' => ["Today", "string", "'Today'"],
            'Arbitrary date string as datetime' => ["Today", "datetime", "'".date_create("Today", new \DateTimezone("UTC"))->format("Y-m-d H:i:s")."'"],
            'Arbitrary date string as boolean' => ["Today", "boolean", "1"],
            'Arbitrary date string as strict integer' => ["Today", "strict integer", "0"],
            'Arbitrary date string as strict float' => ["Today", "strict float", "0.0"],
            'Arbitrary date string as strict string' => ["Today", "strict string", "'Today'"],
            'Arbitrary date string as strict datetime' => ["Today", "strict datetime", "'".date_create("Today", new \DateTimezone("UTC"))->format("Y-m-d H:i:s")."'"],
            'Arbitrary date string as strict boolean' => ["Today", "strict boolean", "1"],
            'DateTime as integer' => [$dateMutable, "integer", $dateUTC->getTimestamp()],
            'DateTime as float' => [$dateMutable, "float", $dateUTC->getTimestamp().".0"],
            'DateTime as string' => [$dateMutable, "string", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTime as datetime' => [$dateMutable, "datetime", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTime as boolean' => [$dateMutable, "boolean", "1"],
            'DateTime as strict integer' => [$dateMutable, "strict integer", $dateUTC->getTimestamp()],
            'DateTime as strict float' => [$dateMutable, "strict float", $dateUTC->getTimestamp().".0"],
            'DateTime as strict string' => [$dateMutable, "strict string", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTime as strict datetime' => [$dateMutable, "strict datetime", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTime as strict boolean' => [$dateMutable, "strict boolean", "1"],
            'DateTimeImmutable as integer' => [$dateImmutable, "integer", $dateUTC->getTimestamp()],
            'DateTimeImmutable as float' => [$dateImmutable, "float", $dateUTC->getTimestamp().".0"],
            'DateTimeImmutable as string' => [$dateImmutable, "string", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTimeImmutable as datetime' => [$dateImmutable, "datetime", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTimeImmutable as boolean' => [$dateImmutable, "boolean", "1"],
            'DateTimeImmutable as strict integer' => [$dateImmutable, "strict integer", $dateUTC->getTimestamp()],
            'DateTimeImmutable as strict float' => [$dateImmutable, "strict float", $dateUTC->getTimestamp().".0"],
            'DateTimeImmutable as strict string' => [$dateImmutable, "strict string", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTimeImmutable as strict datetime' => [$dateImmutable, "strict datetime", "'".$dateUTC->format("Y-m-d H:i:s")."'"],
            'DateTimeImmutable as strict boolean' => [$dateImmutable, "strict boolean", "1"],
        ];
        $decorators = $this->provideSyntaxDecorators();
        foreach ($this->provideDrivers() as $drvName => list($drv, $stringCoersion, $class, $func)) {
            $conv = $decorators[$drvName] ?? $conv = $decorators[''];
            foreach ($tests as $index => list($value, $type, $exp)) {
                $t = preg_replace("<^strict >", "", $type);
                $exp = ($exp=="null") ? $exp : $conv($exp, $t);
                yield "$index ($drvName)" => [$drv, $class, $func, $value, $type, $exp];
            }
        }
    }

    public function provideBinaryBindings() {
        $dateMutable = new \DateTime("Noon Today", new \DateTimezone("America/Toronto"));
        $dateImmutable = new \DateTimeImmutable("Noon Today", new \DateTimezone("America/Toronto"));
        $dateUTC = new \DateTime("@".$dateMutable->getTimestamp(), new \DateTimezone("UTC"));
        $tests = [
            'Null as binary' => [null, "binary", "null"],
            'Null as strict binary' => [null, "strict binary", "x''"],
            'True as binary' => [true, "binary", "x'31'"],
            'True as strict binary' => [true, "strict binary", "x'31'"],
            'False as binary' => [false, "binary", "x''"],
            'False as strict binary' => [false, "strict binary", "x''"],
            'Integer as binary' => [2112, "binary", "x'32313132'"],
            'Integer as strict binary' => [2112, "strict binary", "x'32313132'"],
            'Integer zero as binary' => [0, "binary", "x'30'"],
            'Integer zero as strict binary' => [0, "strict binary", "x'30'"],
            'Float as binary' => [2112.5, "binary", "x'323131322e35'"],
            'Float as strict binary' => [2112.5, "strict binary", "x'323131322e35'"],
            'Float zero as binary' => [0.0, "binary", "x'30'"],
            'Float zero as strict binary' => [0.0, "strict binary", "x'30'"],
            'ASCII string as binary' => ["Random string", "binary", "x'52616e646f6d20737472696e67'"],
            'ASCII string as strict binary' => ["Random string", "strict binary", "x'52616e646f6d20737472696e67'"],
            'UTF-8 string as binary' => ["\u{e9}", "binary", "x'c3a9'"],
            'UTF-8 string as strict binary' => ["\u{e9}", "strict binary", "x'c3a9'"],
            'Binary string as integer' => [chr(233).chr(233), "integer", "0"],
            'Binary string as float' => [chr(233).chr(233), "float", "0.0"],
            'Binary string as string' => [chr(233).chr(233), "string", "'".chr(233).chr(233)."'"],
            'Binary string as binary' => [chr(233).chr(233), "binary", "x'e9e9'"],
            'Binary string as datetime' => [chr(233).chr(233), "datetime", "null"],
            'Binary string as boolean' => [chr(233).chr(233), "boolean", "1"],
            'Binary string as strict integer' => [chr(233).chr(233), "strict integer", "0"],
            'Binary string as strict float' => [chr(233).chr(233), "strict float", "0.0"],
            'Binary string as strict string' => [chr(233).chr(233), "strict string", "'".chr(233).chr(233)."'"],
            'Binary string as strict binary' => [chr(233).chr(233), "strict binary", "x'e9e9'"],
            'Binary string as strict datetime' => [chr(233).chr(233), "strict datetime", "'1970-01-01 00:00:00'"],
            'Binary string as strict boolean' => [chr(233).chr(233), "strict boolean", "1"],
            'ISO 8601 string as binary' => ["2017-01-09T13:11:17", "binary", "x'323031372d30312d30395431333a31313a3137'"],
            'ISO 8601 string as strict binary' => ["2017-01-09T13:11:17", "strict binary", "x'323031372d30312d30395431333a31313a3137'"],
            'Arbitrary date string as binary' => ["Today", "binary", "x'546f646179'"],
            'Arbitrary date string as strict binary' => ["Today", "strict binary", "x'546f646179'"],
            'DateTime as binary' => [$dateMutable, "binary", "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            'DateTime as strict binary' => [$dateMutable, "strict binary", "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            'DateTimeImmutable as binary' => [$dateImmutable, "binary", "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
            'DateTimeImmutable as strict binary' => [$dateImmutable, "strict binary", "x'".bin2hex($dateUTC->format("Y-m-d H:i:s"))."'"],
        ];
        $decorators = $this->provideSyntaxDecorators();
        foreach ($this->provideDrivers() as $drvName => list($drv, $stringCoersion, $class, $func)) {
            $conv = $decorators[$drvName] ?? $conv = $decorators[''];
            if ($drvName=="PDO PostgreSQL") {
                // skip PostgreSQL for these tests
                $drv = false;
            }
            foreach ($tests as $index => list($value, $type, $exp)) {
                $t = preg_replace("<^strict >", "", $type);
                $exp = ($exp=="null") ? $exp : $conv($exp, $t);
                yield "$index ($drvName)" => [$drv, $class, $func, $value, $type, $exp];
            }
        }
    }

    function provideSyntaxDecorators() {
        return [
            'PDO PostgreSQL' => (function($v, $t) {
                switch ($t) {
                    case "float":
                        return (substr($v, -2)==".0") ? "'".substr($v, 0, strlen($v) - 2)."'" : "'$v'";
                    case "string":
                        if (preg_match("<^char\((\d+)\)$>", $v, $match)) {
                            return "U&'\\+".str_pad(dechex((int) $match[1]), 6, "0", \STR_PAD_LEFT)."'";
                        } else {
                            return $v;
                        }
                    default:
                        return $v;
                }
            }),
            'PDO SQLite 3' => (function($v, $t) {
                if ($t=="float") {
                    return (substr($v, -2)==".0") ? "'".substr($v, 0, strlen($v) - 2)."'" : "'$v'";
                } else {
                    return $v;
                }
            }),
            'SQLite 3' => (function($v, $t) {
                return $v;
            }),
            '' => (function($v, $t) {
                return $v;
            }),
        ];
    }
}
