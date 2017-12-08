<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Test;

use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;

/** @coversNothing */
abstract class AbstractTest extends \PHPUnit\Framework\TestCase {
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

    public function approximateTime($exp, $act) {
        if (is_null($act)) {
            return null;
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

    public function assertTime($exp, $test, string $msg = null) {
        $test = $this->approximateTime($exp, $test);
        $exp  = Date::transform($exp, "iso8601");
        $test = Date::transform($test, "iso8601");
        $this->assertSame($exp, $test, $msg);
    }

    public function clearData(bool $loadLang = true): bool {
        $r = new \ReflectionClass(\JKingWeb\Arsse\Arsse::class);
        $props = array_keys($r->getStaticProperties());
        foreach ($props as $prop) {
            Arsse::$$prop = null;
        }
        if ($loadLang) {
            Arsse::$lang = new \JKingWeb\Arsse\Lang();
        }
        return true;
    }
}
