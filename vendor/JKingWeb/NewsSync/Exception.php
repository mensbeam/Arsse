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
		"Db/Update/Exception.tooNew"			=> 10211,
		"Db/Update/Exception.fileMissing"		=> 10212,
		"Db/Update/Exception.fileUnusable"		=> 10213,
		"Db/Update/Exception.fileUnreadable"	=> 10214,
		"Db/Update/Exception.manual"			=> 10215,
		"Db/Update/Exception.manualOnly"		=> 10216,
		"Conf/Exception.fileMissing"			=> 10302,
		"Conf/Exception.fileUnusable"			=> 10303,
		"Conf/Exception.fileUnreadable"			=> 10304,
		"Conf/Exception.fileUnwritable"			=> 10305,
		"Conf/Exception.fileUncreatable"		=> 10306,
		"Conf/Exception.fileCorrupt"			=> 10307,
		"User/Exception.functionNotImplemented"	=> 10401, 
		"User/Exception.doesNotExist"			=> 10402,
		"User/Exception.alreadyExists"			=> 10403,
		"User/Exception.authMissing"			=> 10411,
		"User/Exception.authFailed"				=> 10412,
		"User/Exception.notAuthorized" 			=> 10421,
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