<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test;
use JKingWeb\Arsse\Exception;
use JKingWeb\Arsse\Data;

trait Tools {
    use \JKingWeb\Arsse\Misc\DateFormatter;

    function assertException(string $msg, string $prefix = "", string $type = "Exception") {
        $class = \JKingWeb\Arsse\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
        $msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
        if(array_key_exists($msgID, Exception::CODES)) {
            $code = Exception::CODES[$msgID];
        } else {
            $code = 0;
        }
        $this->expectException($class);
        $this->expectExceptionCode($code);
    }

    function assertTime($exp, $test) {
        $exp  = $this->dateTransform($exp);
        $test = $this->dateTransform($test);
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