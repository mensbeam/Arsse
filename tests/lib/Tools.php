<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test;
use \JKingWeb\NewsSync\Exception;

trait Tools {
	function assertException(string $msg, string $prefix = "", string $type = "Exception") {
		$class = \JKingWeb\NewsSync\NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
		$msgID = ($prefix !== "" ? $prefix . "/" : "") . $type. ".$msg";
		if(array_key_exists($msgID, Exception::CODES)) {
			$code = Exception::CODES[$msgID];
		} else {
			$code = 0;
		}
		$this->expectException($class);
		$this->expectExceptionCode($code);
	}
}