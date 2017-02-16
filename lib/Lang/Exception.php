<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Lang;

class Exception extends \JKingWeb\NewsSync\AbstractException {
    static $test = false; // used during PHPUnit testing only

    function __construct(string $msgID = "", $vars = null, \Throwable $e = null) {
        if(!self::$test) {
            parent::__construct($msgID, $vars, $e);
        } else {
            $codeID = "Lang/Exception.$msgID";
            if(!array_key_exists($codeID,self::CODES)) {
                $code = -1;
                $msg = "Exception.".str_replace("\\","/",parent::class).".uncoded";
                $vars = $msgID;
            } else {
                $code = self::CODES[$codeID];
                $msg = "Exception.".str_replace("\\","/",__CLASS__).".$msgID";
            }
            \Exception::__construct($msg, $code, $e);
        }
    }
}