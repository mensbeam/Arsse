<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use Eloquent\Phony\Mock\Handle\InstanceHandle;
use Eloquent\Phony\Phpunit\Phony;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\Db\Driver;
use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Factory;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\Misc\URL;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\XmlResponse;

/** @coversNothing */
abstract class AbstractTest extends \PHPUnit\Framework\TestCase {
    use \DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

    protected $objMock;
    protected $confMock;
    protected $langMock;
    protected $dbMock;
    protected $userMock;

    public function setUp(): void {
        self::clearData();
        // create the object factory as a mock
        $this->objMock = Arsse::$obj = $this->mock(Factory::class);
        $this->objMock->get->does(function(string $class) {
            return new $class;
        });
    }

    public static function clearData(bool $loadLang = true): void {
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

    public static function setConf(array $conf = [], bool $force = true): void {
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

    protected function serverRequest(string $method, string $url, string $urlPrefix, array $headers = [], array $vars = [], $body = null, string $type = "", $params = [], string $user = null): ServerRequestInterface {
        $server = [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI'    => $url,
        ];
        if (strlen($type)) {
            $server['HTTP_CONTENT_TYPE'] = $type;
        }
        if (isset($params)) {
            if (is_array($params)) {
                $params = implode("&", array_map(function($v, $k) {
                    return rawurlencode($k).(isset($v) ? "=".rawurlencode($v) : "");
                }, $params, array_keys($params)));
            }
            $url = URL::queryAppend($url, (string) $params);
            $params = null;
        }
        $q = parse_url($url, \PHP_URL_QUERY);
        if (strlen($q ?? "")) {
            parse_str($q, $params);
        } else {
            $params = [];
        }
        $parsedBody = null;
        if (isset($body)) {
            if (is_string($body) && in_array(strtolower($type), ["", "application/x-www-form-urlencoded"])) {
                parse_str($body, $parsedBody);
            } elseif (!is_string($body) && in_array(strtolower($type), ["application/json", "text/json"])) {
                $body = json_encode($body, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            } elseif (!is_string($body) && in_array(strtolower($type), ["", "application/x-www-form-urlencoded"])) {
                $parsedBody = $body;
                $body = http_build_query($body, "a", "&");
            }
        }
        $server = array_merge($server, $vars);
        $req = new ServerRequest($server, [], $url, $method, "php://memory", [], [], $params, $parsedBody);
        if (isset($user)) {
            if (strlen($user)) {
                $req = $req->withAttribute("authenticated", true)->withAttribute("authenticatedUser", $user);
            } else {
                $req = $req->withAttribute("authenticationFailed", true);
            }
        }
        if (strlen($type) && strlen($body ?? "")) {
            $req = $req->withHeader("Content-Type", $type);
        }
        foreach ($headers as $key => $value) {
            if (!is_null($value)) {
                $req = $req->withHeader($key, $value);
            } else {
                $req = $req->withoutHeader($key);
            }
        }
        $target = substr(URL::normalize($url), strlen($urlPrefix));
        $req = $req->withRequestTarget($target);
        if (strlen($body ?? "")) {
            $p = $req->getBody();
            $p->write($body);
            $req = $req->withBody($p);
        }
        return $req;
    }

    public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void {
        if (method_exists(parent::class, "assertMatchesRegularExpression")) {
            parent::assertMatchesRegularExpression($pattern, $string, $message);
        } else {
            parent::assertRegExp($pattern, $string, $message);
        }
    }

    public static function assertFileDoesNotExist(string $filename, string $message = ''): void {
        if (method_exists(parent::class, "assertFileDoesNotExist")) {
            parent::assertFileDoesNotExist($filename, $message);
        } else {
            parent::assertFileNotExists($filename, $message);
        }
    }

    public function assertException($msg = "", string $prefix = "", string $type = "Exception"): void {
        if (func_num_args()) {
            if ($msg instanceof \JKingWeb\Arsse\AbstractException) {
                $this->expectException(get_class($msg));
                $this->expectExceptionCode($msg->getCode());
            } else {
                $class = \JKingWeb\Arsse\NS_BASE.($prefix !== "" ? str_replace("/", "\\", $prefix)."\\" : "").$type;
                $msgID = ($prefix !== "" ? $prefix."/" : "").$type.".$msg";
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

    protected function assertMessage(MessageInterface $exp, MessageInterface $act, string $text = ''): void {
        if ($exp instanceof ResponseInterface) {
            $this->assertInstanceOf(ResponseInterface::class, $act, $text);
            $this->assertSame($exp->getStatusCode(), $act->getStatusCode(), $text);
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
            $this->assertInstanceOf(JsonResponse::class, $act, $text);
            $this->assertEquals($exp->getPayload(), $act->getPayload(), $text);
            $this->assertSame($exp->getPayload(), $act->getPayload(), $text);
        } elseif ($exp instanceof XmlResponse) {
            $this->assertInstanceOf(XmlResponse::class, $act, $text);
            $this->assertXmlStringEqualsXmlString((string) $exp->getBody(), (string) $act->getBody(), $text);
        } else {
            $this->assertSame((string) $exp->getBody(), (string) $act->getBody(), $text);
        }
        $this->assertEquals($exp->getHeaders(), $act->getHeaders(), $text);
    }

    public function assertTime($exp, $test, string $msg = ''): void {
        $test = $this->approximateTime($exp, $test);
        $exp = Date::transform($exp, "iso8601");
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
                $value[$k] = $this->stringify($v);
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
                $this->assertCount(sizeof($cols), $row, "The number of columns in array index $index of expectations for table $table does not match its definition");
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
                    if ($row === $test) {
                        $data[$index] = $test;
                        break;
                    }
                }
                $this->assertContains($row, $data, "Actual Table $table does not contain record at expected array index $index");
                $found = array_search($row, $data, true);
                unset($data[$found]);
            }
            $this->assertSame([], $data, "Actual table $table contains extra rows not in expectations");
        }
        return true;
    }

    public function primeExpectations(array $source, array $tableSpecs): array {
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

    public function assertResult(array $expected, Result $data): void {
        $data = $data->getAll();
        // stringify our expectations if necessary
        if (static::$stringOutput ?? false) {
            $expected = $this->stringify($expected);
            // MySQL is extra-special and mixes strings and integers, so we cast the data, too
            if ((static::$implementation ?? "") === "MySQL") {
                $data = $this->stringify($data);
            }
        }
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
            $keys = array_keys($keys);
            foreach ($data as $row) {
                $r = [];
                foreach ($keys as $k) {
                    if (array_key_exists($k, $row)) {
                        $r[$k] = $row[$k];
                    }
                }
                $rows[] = $r;
            }
            // compare the result set to the expectations
            foreach ($rows as $row) {
                $this->assertContains($row, $expected, "Result set contains unexpected record.\n".var_export($expected, true));
                $found = array_search($row, $expected);
                unset($expected[$found]);
            }
            $this->assertArraySubset($expected, [], false, "Expectations not in result set.");
        }
    }

    /** Guzzle's exception classes require some fairly complicated construction; this abstracts it all away so that only message and code need be supplied  */
    protected function mockGuzzleException(string $class, ?string $message = null, ?int $code = null, ?\Throwable $e = null): GuzzleException {
        if (is_a($class, RequestException::class, true)) {
            $req = $this->mock(RequestInterface::class);
            $res = $this->mock(ResponseInterface::class);
            $res->getStatusCode->returns($code ?? 0);
            return new $class($message ?? "", $req->get(), $res->get(), $e);
        } else {
            return new $class($message ?? "", $code ?? 0, $e);
        }
    }

    protected function mock(string $class): InstanceHandle {
        return Phony::mock($class);
    }

    protected function partialMock(string $class, ...$argument): InstanceHandle {
        return Phony::partialMock($class, $argument);
    }
}
