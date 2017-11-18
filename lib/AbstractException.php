<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

abstract class AbstractException extends \Exception {
    const CODES = [        
        "Exception.uncoded"                           => -1,
        "Exception.unknown"                           => 10000,
        "Exception.constantUnknown"                   => 10001,
        "ExceptionType.strictFailure"                 => 10011,
        "ExceptionType.typeUnknown"                   => 10012,
        "Lang/Exception.defaultFileMissing"           => 10101,
        "Lang/Exception.fileMissing"                  => 10102,
        "Lang/Exception.fileUnreadable"               => 10103,
        "Lang/Exception.fileCorrupt"                  => 10104,
        "Lang/Exception.stringMissing"                => 10105,
        "Lang/Exception.stringInvalid"                => 10106,
        "Db/Exception.extMissing"                     => 10201,
        "Db/Exception.fileMissing"                    => 10202,
        "Db/Exception.fileUnusable"                   => 10203,
        "Db/Exception.fileUnreadable"                 => 10204,
        "Db/Exception.fileUnwritable"                 => 10205,
        "Db/Exception.fileUncreatable"                => 10206,
        "Db/Exception.fileCorrupt"                    => 10207,
        "Db/Exception.updateTooNew"                   => 10211,
        "Db/Exception.updateManual"                   => 10212,
        "Db/Exception.updateManualOnly"               => 10213,
        "Db/Exception.updateFileMissing"              => 10214,
        "Db/Exception.updateFileUnusable"             => 10215,
        "Db/Exception.updateFileUnreadable"           => 10216,
        "Db/Exception.updateFileError"                => 10217,
        "Db/Exception.updateFileIncomplete"           => 10218,
        "Db/Exception.paramTypeInvalid"               => 10221,
        "Db/Exception.paramTypeUnknown"               => 10222,
        "Db/Exception.paramTypeMissing"               => 10223,
        "Db/Exception.engineErrorGeneral"             => 10224, // this symbol may have engine-specific duplicates to accomodate engine-specific error string construction
        "Db/Exception.savepointStatusUnknown"         => 10225,
        "Db/Exception.savepointInvalid"               => 10226,
        "Db/Exception.savepointStale"                 => 10227,
        "Db/Exception.resultReused"                   => 10228,
        "Db/ExceptionInput.missing"                   => 10231,
        "Db/ExceptionInput.whitespace"                => 10232,
        "Db/ExceptionInput.tooLong"                   => 10233,
        "Db/ExceptionInput.tooShort"                  => 10234,
        "Db/ExceptionInput.idMissing"                 => 10235,
        "Db/ExceptionInput.constraintViolation"       => 10236,
        "Db/ExceptionInput.engineConstraintViolation" => 10236,
        "Db/ExceptionInput.typeViolation"             => 10237,
        "Db/ExceptionInput.engineTypeViolation"       => 10237,
        "Db/ExceptionInput.circularDependence"        => 10238,
        "Db/ExceptionInput.subjectMissing"            => 10239,
        "Db/ExceptionTimeout.general"                 => 10241,
        "Conf/Exception.fileMissing"                  => 10301,
        "Conf/Exception.fileUnusable"                 => 10302,
        "Conf/Exception.fileUnreadable"               => 10303,
        "Conf/Exception.fileUnwritable"               => 10304,
        "Conf/Exception.fileUncreatable"              => 10305,
        "Conf/Exception.fileCorrupt"                  => 10306,
        "User/Exception.functionNotImplemented"       => 10401,
        "User/Exception.doesNotExist"                 => 10402,
        "User/Exception.alreadyExists"                => 10403,
        "User/Exception.authMissing"                  => 10411,
        "User/Exception.authFailed"                   => 10412,
        "User/ExceptionAuthz.notAuthorized"           => 10421,
        "User/ExceptionSession.invalid"               => 10431,
        "Feed/Exception.invalidCertificate"           => 10501,
        "Feed/Exception.invalidUrl"                   => 10502,
        "Feed/Exception.maxRedirect"                  => 10503,
        "Feed/Exception.maxSize"                      => 10504,
        "Feed/Exception.timeout"                      => 10505,
        "Feed/Exception.forbidden"                    => 10506,
        "Feed/Exception.unauthorized"                 => 10507,
        "Feed/Exception.malformedXml"                 => 10511,
        "Feed/Exception.xmlEntity"                    => 10512,
        "Feed/Exception.subscriptionNotFound"         => 10521,
        "Feed/Exception.unsupportedFeedFormat"        => 10522,
    ];

    public function __construct(string $msgID = "", $vars = null, \Throwable $e = null) {
        if ($msgID=="") {
            $msg = "Exception.unknown";
            $code = 10000;
        } else {
            $class = get_called_class();
            $codeID = str_replace("\\", "/", str_replace(NS_BASE, "", $class)).".$msgID";
            if (!array_key_exists($codeID, self::CODES)) {
                throw new Exception("uncoded", $codeID);
            } else {
                $code = self::CODES[$codeID];
                $msg = "Exception.".str_replace("\\", "/", $class).".$msgID";
            }
            $msg = Arsse::$lang->msg($msg, $vars);
        }
        parent::__construct($msg, $code, $e);
    }
}
