<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

require_once __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."bootstrap.php";

trait TestingHelpers {
	function assertException(string $msg, string $prefix = "", string $type = "Exception") {
		$class = NS_BASE . ($prefix !== "" ? str_replace("/", "\\", $prefix) . "\\" : "") . $type;
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