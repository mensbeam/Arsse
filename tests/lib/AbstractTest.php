<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Db\Driver;
use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;

/** @coversNothing */
abstract class AbstractTest extends \PHPUnit\Framework\TestCase {
    public function setUp() {
        self::clearData();
    }

    public function tearDown() {
        self::clearData();
    }

    public static function clearData(bool $loadLang = true) {
        date_default_timezone_set("America/Toronto");
        $r = new \ReflectionClass(\JKingWeb\Arsse\Arsse::class);
        $props = array_keys($r->getStaticProperties());
        foreach ($props as $prop) {
            Arsse::$$prop = null;
        }
        if ($loadLang) {
            Arsse::$lang = new \JKingWeb\Arsse\Lang();
        }
    }

    public static function setConf(array $conf = [], bool $force = true) {
        $defaults = [
            'dbSQLite3File'      => ":memory:",
            'dbSQLite3Timeout'   => 0,
            'dbPostgreSQLHost'   => $_ENV['ARSSE_TEST_PGSQL_HOST']   ?: "",
            'dbPostgreSQLPort'   => $_ENV['ARSSE_TEST_PGSQL_PORT']   ?: 5432,
            'dbPostgreSQLUser'   => $_ENV['ARSSE_TEST_PGSQL_USER']   ?: "arsse_test",
            'dbPostgreSQLPass'   => $_ENV['ARSSE_TEST_PGSQL_PASS']   ?: "arsse_test",
            'dbPostgreSQLDb'     => $_ENV['ARSSE_TEST_PGSQL_DB']     ?: "arsse_test",
            'dbPostgreSQLSchema' => $_ENV['ARSSE_TEST_PGSQL_SCHEMA'] ?: "arsse_test",
            'dbMySQLHost'        => $_ENV['ARSSE_TEST_MYSQL_HOST']   ?: "localhost",
            'dbMySQLPort'        => $_ENV['ARSSE_TEST_MYSQL_PORT']   ?: 3306,
            'dbMySQLUser'        => $_ENV['ARSSE_TEST_MYSQL_USER']   ?: "arsse_test",
            'dbMySQLPass'        => $_ENV['ARSSE_TEST_MYSQL_PASS']   ?: "arsse_test",
            'dbMySQLDb'          => $_ENV['ARSSE_TEST_MYSQL_DB']     ?: "arsse_test",
        ];
        Arsse::$conf = (($force ? null : Arsse::$conf) ?? (new Conf))->import($defaults)->import($conf);
    }

    public function assertException($msg = "", string $prefix = "", string $type = "Exception") {
        if (func_num_args()) {
            if ($msg instanceof \JKingWeb\Arsse\AbstractException) {
                $this->expectException(get_class($msg));
                $this->expectExceptionCode($msg->getCode());
            } else {
                $class = \JKingWeb\Arsse\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
                $msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
                if (array_key_exists($msgID, Exception::CODES)) {
                    $code = Exception::CODES[$msgID];
                } else {
                    $code = 0;
                }
                $this->expectException($class);
                $this->expectExceptionCode($code);
            }
        } else {
            // expecting a standard PHP exception
            $this->expectException(\Throwable::class);
        }
    }

    protected function assertMessage(MessageInterface $exp, MessageInterface $act, string $text = '') {
        if ($exp instanceof ResponseInterface) {
            $this->assertInstanceOf(ResponseInterface::class, $act, $text);
            $this->assertEquals($exp->getStatusCode(), $act->getStatusCode(), $text);
        } elseif ($exp instanceof RequestInterface) {
            if ($exp instanceof ServerRequestInterface) {
                $this->assertInstanceOf(ServerRequestInterface::class, $act, $text);
                $this->assertEquals($exp->getAttributes(), $act->getAttributes(), $text);
            }
            $this->assertInstanceOf(RequestInterface::class, $act, $text);
            $this->assertSame($exp->getMethod(), $act->getMethod(), $text);
            $this->assertSame($exp->getRequestTarget(), $act->getRequestTarget(), $text);
        }
        if ($exp instanceof JsonResponse) {
            $this->assertEquals($exp->getPayload(), $act->getPayload(), $text);
            $this->assertSame($exp->getPayload(), $act->getPayload(), $text);
        } else {
            $this->assertEquals((string) $exp->getBody(), (string) $act->getBody(), $text);
        }
        $this->assertEquals($exp->getHeaders(), $act->getHeaders(), $text);
    }

    public function assertTime($exp, $test, string $msg = '') {
        $test = $this->approximateTime($exp, $test);
        $exp  = Date::transform($exp, "iso8601");
        $test = Date::transform($test, "iso8601");
        $this->assertSame($exp, $test, $msg);
    }

    public function approximateTime($exp, $act) {
        if (is_null($act)) {
            return null;
        } elseif (is_null($exp)) {
            return $act;
        }
        $target = Date::normalize($exp)->getTimeStamp();
        $value = Date::normalize($act)->getTimeStamp();
        if ($value >= ($target - 1) && $value <= ($target + 1)) {
            // if the actual time is off by no more than one second, it's acceptable
            return $exp;
        } else {
            return $act;
        }
    }

    public function stringify($value) {
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->v($v);
            } elseif (is_int($v) || is_float($v)) {
                $value[$k] = (string) $v;
            }
        }
        return $value;
    }

    public function primeDatabase(Driver $drv, array $data): bool {
        $tr = $drv->begin();
        foreach ($data as $table => $info) {
            $cols = array_map(function($v) {
                return '"'.str_replace('"', '""', $v).'"';
            }, array_keys($info['columns']));
            $cols = implode(",", $cols);
            $bindings = array_values($info['columns']);
            $params = implode(",", array_fill(0, sizeof($info['columns']), "?"));
            $s = $drv->prepareArray("INSERT INTO $table($cols) values($params)", $bindings);
            foreach ($info['rows'] as $row) {
                $s->runArray($row);
            }
        }
        $tr->commit();
        $this->primed = true;
        return true;
    }

    public function compareExpectations(Driver $drv, array $expected): bool {
        foreach ($expected as $table => $info) {
            $cols = array_map(function($v) {
                return '"'.str_replace('"', '""', $v).'"';
            }, array_keys($info['columns']));
            $cols = implode(",", $cols);
            $types = $info['columns'];
            $data = $drv->prepare("SELECT $cols from $table")->run()->getAll();
            $cols = array_keys($info['columns']);
            foreach ($info['rows'] as $index => $row) {
                $this->assertCount(sizeof($cols), $row, "The number of values for array index $index does not match the number of fields");
                $row = array_combine($cols, $row);
                foreach ($data as $index => $test) {
                    foreach ($test as $col => $value) {
                        switch ($types[$col]) {
                            case "datetime":
                                $test[$col] = $this->approximateTime($row[$col], $value);
                                break;
                            case "int":
                                $test[$col] = ValueInfo::normalize($value, ValueInfo::T_INT | ValueInfo::M_DROP | valueInfo::M_NULL);
                                break;
                            case "float":
                                $test[$col] = ValueInfo::normalize($value, ValueInfo::T_FLOAT | ValueInfo::M_DROP | valueInfo::M_NULL);
                                break;
                            case "bool":
                                $test[$col] = (int) ValueInfo::normalize($value, ValueInfo::T_BOOL | ValueInfo::M_DROP | valueInfo::M_NULL);
                                break;
                        }
                    }
                    if ($row===$test) {
                        $data[$index] = $test;
                        break;
                    }
                }
                $this->assertContains($row, $data, "Table $table does not contain record at array index $index.");
                $found = array_search($row, $data, true);
                unset($data[$found]);
            }
            $this->assertSame([], $data);
        }
        return true;
    }

    public function primeExpectations(array $source, array $tableSpecs = null): array {
        $out = [];
        foreach ($tableSpecs as $table => $columns) {
            // make sure the source has the table we want
            $this->assertArrayHasKey($table, $source, "Source for expectations does not contain requested table $table.");
            $out[$table] = [
                'columns' => [],
                'rows'    => array_fill(0, sizeof($source[$table]['rows']), []),
            ];
            // make sure the source has all the columns we want for the table
            $cols = array_flip($columns);
            $cols = array_intersect_key($cols, $source[$table]['columns']);
            $this->assertSame(array_keys($cols), $columns, "Source for table $table does not contain all requested columns");
            // get a map of source value offsets and keys
            $targets = array_flip(array_keys($source[$table]['columns']));
            foreach ($cols as $key => $order) {
                // fill the column-spec
                $out[$table]['columns'][$key] = $source[$table]['columns'][$key];
                foreach ($source[$table]['rows'] as $index => $row) {
                    // fill each row column-wise with re-ordered values
                    $out[$table]['rows'][$index][$order] = $row[$targets[$key]];
                }
            }
        }
        return $out;
    }

    public function assertResult(array $expected, Result $data) {
        $data = $data->getAll();
        $this->assertCount(sizeof($expected), $data, "Number of result rows (".sizeof($data).") differs from number of expected rows (".sizeof($expected).")");
        if (sizeof($expected)) {
            // make sure the expectations are consistent
            foreach ($expected as $exp) {
                if (!isset($keys)) {
                    $keys = $exp;
                    continue;
                }
                $this->assertSame(array_keys($keys), array_keys($exp), "Result set expectations are irregular");
            }
            // filter the result set to contain just the desired keys (we don't care if the result has extra keys)
            $rows = [];
            foreach ($data as $row) {
                $rows[] = array_intersect_key($row, $keys);
            }
            // compare the result set to the expectations
            foreach ($rows as $row) {
                $this->assertContains($row, $expected, "Result set contains unexpected record.");
                $found = array_search($row, $expected);
                unset($expected[$found]);
            }
            $this->assertArraySubset($expected, [], false, "Expectations not in result set.");
        }
    }
}
