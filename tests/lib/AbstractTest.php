<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test;
use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Data;

abstract class AbstractTest extends \PHPUnit\Framework\TestCase {
    use \JKingWeb\Arsse\Misc\DateFormatter;

    function assertException(string $msg = "", string $prefix = "", string $type = "Exception") {
        if(func_num_args()) {
            $class = \JKingWeb\Arsse\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
            $msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
            if(array_key_exists($msgID, Exception::CODES)) {
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

    function assertTime($exp, $test) {
        $exp  = $this->dateTransform($exp, "iso8601");
        $test = $this->dateTransform($test, "iso8601");
        $this->assertSame($exp, $test);
    }

    function clearData(bool $loadLang = true): bool {
        $r = new \ReflectionClass(\JKingWeb\Arsse\Data::class);
        $props = array_keys($r->getStaticProperties());
        foreach($props as $prop) {
            Data::$$prop = null;
        }
        if($loadLang) {
            Data::$l = new \JKingWeb\Arsse\Lang();
        }
        return true;
    }
}