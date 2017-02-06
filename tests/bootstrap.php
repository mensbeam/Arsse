<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

const BASE = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;
const NS_BASE = __NAMESPACE__."\\";

spl_autoload_register(function ($class) {
	if($class=="SimplePie") return;
	$file = str_replace("\\", DIRECTORY_SEPARATOR, $class);
    $file = BASE."vendor".DIRECTORY_SEPARATOR.$file.".php";
	if (file_exists($file)) {
		require_once $file;
    }
});

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

ignore_user_abort(true);