<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\CLI;
use JKingWeb\Arsse\Misc\Date;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response\EmptyResponse;

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
            'dbSQLite3File' => ":memory:",
            'dbSQLite3Timeout' => 0,
            'dbPostgreSQLUser' => "arsse_test",
            'dbPostgreSQLPass' => "arsse_test",
            'dbPostgreSQLDb' => "arsse_test",
            'dbPostgreSQLSchema' => "arsse_test",
            'dbMySQLUser' => "arsse_test",
            'dbMySQLPass' => "arsse_test",
            'dbMySQLDb' => "arsse_test",
        ];
        Arsse::$conf = ($force ? null : Arsse::$conf) ?? (new Conf)->import($defaults)->import($conf);
    }

    public function assertException(string $msg = "", string $prefix = "", string $type = "Exception") {
        if (func_num_args()) {
            $class = \JKingWeb\Arsse\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
            $msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
            if (array_key_exists($msgID, Exception::CODES)) {
                $code = Exception::CODES[$msgID];
            } else {
                $code = 0;
            }
            $this->expectException($class);
            $this->expectExceptionCode($code);
        } else {
            // expecting a standard PHP exception
            $this->expectException(\Exception::class);
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
}
