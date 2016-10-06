<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

class Exception extends \Exception {

	const CODES = [
		"Exception.Misc"						=> 10000,
		"Lang/Exception.defaultFileMissing"		=> 10101,
		"Lang/Exception.fileMissing"			=> 10102,
		"Lang/Exception.fileUnreadable"			=> 10103,
		"Lang/Exception.fileCorrupt"			=> 10104,
		"Lang/Exception.stringMissing" 			=> 10105,
		"Db/Exception.extMissing"				=> 10201,
		"Db/Exception.fileMissing"				=> 10202,
		"Db/Exception.fileUnusable"				=> 10203,
		"Db/Exception.fileUnreadable"			=> 10204,
		"Db/Exception.fileUnwritable"			=> 10205,
		"Db/Exception.fileUncreatable"			=> 10206,
		"Db/Exception.fileCorrupt"				=> 10207,
	];

	public function __construct(string $msgID = "", $vars = null, \Throwable $e = null) {
		if($msgID=="") {
			$msg = "";
			$code = 0;
		} else {
			$msg = "Exception.".str_replace("\\","/",get_called_class()).".$msgID";
			$msg = Lang::msg($msg, $vars);
			$codeID = str_replace("\\", "/", str_replace(NS_BASE, "", get_called_class()));
			if(!array_key_exists($codeID,self::CODES)) {
				$code = 0;
			} else {
				$code = self::CODES[$codeID];
			}
		}
		parent::__construct($msg, $code, $e);
	}
}