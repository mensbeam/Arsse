<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class ExceptionFatal extends Exception {
	public function __construct($msg = "", $code = 0, $e = null) {
		\Exception::__construct($msg, $code, $e);
	}
}