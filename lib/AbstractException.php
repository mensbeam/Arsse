<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;

abstract class AbstractException extends \Exception {

    const CODES = [
        "Exception.uncoded"                     => -1,
        "Exception.invalid"                     => 1, // this exception MUST NOT have a message string defined
        "Exception.unknown"                     => 10000,
        "Lang/Exception.defaultFileMissing"     => 10101,
        "Lang/Exception.fileMissing"            => 10102,
        "Lang/Exception.fileUnreadable"         => 10103,
        "Lang/Exception.fileCorrupt"            => 10104,
        "Lang/Exception.stringMissing"          => 10105,
        "Lang/Exception.stringInvalid"          => 10106,
        "Db/Exception.extMissing"               => 10201,
        "Db/Exception.fileMissing"              => 10202,
        "Db/Exception.fileUnusable"             => 10203,
        "Db/Exception.fileUnreadable"           => 10204,
        "Db/Exception.fileUnwritable"           => 10205,
        "Db/Exception.fileUncreatable"          => 10206,
        "Db/Exception.fileCorrupt"              => 10207,
        "Db/Exception.updateTooNew"             => 10211,
        "Db/Exception.updateFileMissing"        => 10212,
        "Db/Exception.updateFileUnusable"       => 10213,
        "Db/Exception.updateFileUnreadable"     => 10214,
        "Db/Exception.updateManual"             => 10215,
        "Db/Exception.updateManualOnly"         => 10216,
        "Db/Exception.paramTypeInvalid"         => 10401,
        "Db/Exception.paramTypeUnknown"         => 10402,
        "Db/Exception.paramTypeMissing"         => 10403,
        "Conf/Exception.fileMissing"            => 10302,
        "Conf/Exception.fileUnusable"           => 10303,
        "Conf/Exception.fileUnreadable"         => 10304,
        "Conf/Exception.fileUnwritable"         => 10305,
        "Conf/Exception.fileUncreatable"        => 10306,
        "Conf/Exception.fileCorrupt"            => 10307,
        "User/Exception.functionNotImplemented" => 10401,
        "User/Exception.doesNotExist"           => 10402,
        "User/Exception.alreadyExists"          => 10403,
        "User/Exception.authMissing"            => 10411,
        "User/Exception.authFailed"             => 10412,
        "User/ExceptionAuthz.notAuthorized"     => 10421,
        "Feed/Exception.invalidCertificate"     => 10501,
        "Feed/Exception.invalidUrl"             => 10502,
        "Feed/Exception.maxRedirect"            => 10503,
        "Feed/Exception.maxSize"                => 10504,
        "Feed/Exception.timeout"                => 10505,
        "Feed/Exception.forbidden"              => 10506,
        "Feed/Exception.unauthorized"           => 10507,
        "Feed/Exception.malformed"              => 10511,
        "Feed/Exception.xmlEntity"              => 10512,
        "Feed/Exception.subscriptionNotFound"   => 10521,
        "Feed/Exception.unsupportedFeedFormat"  => 10522
    ];

    public function __construct(string $msgID = "", $vars = null, \Throwable $e = null) {
        if($msgID=="") {
            $msg = "Exception.unknown";
            $code = 10000;
        } else {
            $class = get_called_class();
            $codeID = str_replace("\\", "/", str_replace(NS_BASE, "", $class)).".$msgID";
            if(!array_key_exists($codeID, self::CODES)) {
                throw new Exception("uncoded", $codeID);
            } else {
                $code = self::CODES[$codeID];
                $msg = "Exception.".str_replace("\\", "/", $class).".$msgID";
            }
            $msg = Lang::msg($msg, $vars);
        }
        parent::__construct($msg, $code, $e);
    }
}