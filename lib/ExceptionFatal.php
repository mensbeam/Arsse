<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

class ExceptionFatal extends AbstractException {
    public function __construct($msg = "", $code = 0, $e = null) {
        \Exception::__construct($msg, $code, $e);
    }
}