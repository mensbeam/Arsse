<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

abstract class AbstractException extends \Exception {
    public const CODES = [
        "Exception.uncoded"                           => -1,
        "Exception.unknown"                           => 10000,
        "Exception.constantUnknown"                   => 10001,
        "Exception.arrayEmpty"                        => 10002,
        "ExceptionType.strictFailure"                 => 10011,
        "ExceptionType.typeUnknown"                   => 10012,
        "Exception.extMissing"                        => 10021,
        "Lang/Exception.defaultFileMissing"           => 10101,
        "Lang/Exception.fileMissing"                  => 10102,
        "Lang/Exception.fileUnreadable"               => 10103,
        "Lang/Exception.fileCorrupt"                  => 10104,
        "Lang/Exception.stringMissing"                => 10105,
        "Lang/Exception.stringInvalid"                => 10106,
        "Lang/Exception.dataInvalid"                  => 10107,
        "Db/Exception.extMissing"                     => 10201,
        "Db/Exception.fileMissing"                    => 10202,
        "Db/Exception.fileUnusable"                   => 10203,
        "Db/Exception.fileUnreadable"                 => 10204,
        "Db/Exception.fileUnwritable"                 => 10205,
        "Db/Exception.fileUncreatable"                => 10206,
        "Db/Exception.fileCorrupt"                    => 10207,
        "Db/Exception.connectionFailure"              => 10208,
        "Db/Exception.updateTooNew"                   => 10211,
        "Db/Exception.updateManual"                   => 10212,
        "Db/Exception.updateManualOnly"               => 10213,
        "Db/Exception.updateFileMissing"              => 10214,
        "Db/Exception.updateFileUnusable"             => 10215,
        "Db/Exception.updateFileUnreadable"           => 10216,
        "Db/Exception.updateFileError"                => 10217,
        "Db/Exception.updateFileIncomplete"           => 10218,
        "Db/Exception.updateSchemaChange"             => 10219,
        "Db/Exception.paramTypeInvalid"               => 10221,
        "Db/Exception.paramTypeUnknown"               => 10222,
        "Db/Exception.paramTypeMissing"               => 10223,
        "Db/Exception.engineErrorGeneral"             => 10224, // this symbol may have engine-specific duplicates to accomodate engine-specific error string construction
        "Db/Exception.savepointStatusUnknown"         => 10225,
        "Db/Exception.savepointInvalid"               => 10226,
        "Db/Exception.savepointStale"                 => 10227,
        "Db/Exception.resultReused"                   => 10228,
        "Db/ExceptionRetry.schemaChange"              => 10229,
        "Db/ExceptionInput.invalidValue"              => 10230,
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
        "Db/ExceptionTimeout.logicalLock"             => 10241,
        "Conf/Exception.fileMissing"                  => 10301,
        "Conf/Exception.fileUnusable"                 => 10302,
        "Conf/Exception.fileUnreadable"               => 10303,
        "Conf/Exception.fileUnwritable"               => 10304,
        "Conf/Exception.fileUncreatable"              => 10305,
        "Conf/Exception.fileCorrupt"                  => 10306,
        "Conf/Exception.typeMismatch"                 => 10311,
        "Conf/Exception.semanticMismatch"             => 10312,
        "Conf/Exception.ambiguousDefault"             => 10313,
        "User/Exception.authMissing"                  => 10411,
        "User/Exception.authFailed"                   => 10412,
        "User/ExceptionConflict.doesNotExist"         => 10402,
        "User/ExceptionConflict.alreadyExists"        => 10403,
        "User/ExceptionSession.invalid"               => 10431,
        "User/ExceptionInput.invalidTimezone"         => 10441,
        "User/ExceptionInput.invalidValue"            => 10442,
        "User/ExceptionInput.invalidNonZeroInteger"   => 10443,
        "User/ExceptionInput.invalidUsername"         => 10444,
        "Feed/Exception.internalError"                => 10500,
        "Feed/Exception.invalidCertificate"           => 10501,
        "Feed/Exception.invalidUrl"                   => 10502,
        "Feed/Exception.maxRedirect"                  => 10503,
        "Feed/Exception.maxSize"                      => 10504,
        "Feed/Exception.timeout"                      => 10505,
        "Feed/Exception.forbidden"                    => 10506,
        "Feed/Exception.unauthorized"                 => 10507,
        "Feed/Exception.transmissionError"            => 10508,
        "Feed/Exception.connectionFailed"             => 10509,
        "Feed/Exception.malformedXml"                 => 10511,
        "Feed/Exception.xmlEntity"                    => 10512,
        "Feed/Exception.subscriptionNotFound"         => 10521,
        "Feed/Exception.unsupportedFeedFormat"        => 10522,
        "ImportExport/Exception.fileMissing"          => 10601,
        "ImportExport/Exception.fileUnreadable"       => 10603,
        "ImportExport/Exception.fileUnwritable"       => 10604,
        "ImportExport/Exception.fileUncreatable"      => 10605,
        "ImportExport/Exception.invalidSyntax"        => 10611,
        "ImportExport/Exception.invalidSemantics"     => 10612,
        "ImportExport/Exception.invalidFolderName"    => 10613,
        "ImportExport/Exception.invalidFolderCopy"    => 10614,
        "ImportExport/Exception.invalidTagName"       => 10615,
        "Rule/Exception.invalidPattern"               => 10701,
        "Service/Exception.pidNotFile"                => 10801,
        "Service/Exception.pidDirMissing"             => 10802,
        "Service/Exception.pidDirUnresolvable"        => 10803,
        "Service/Exception.pidUnusable"               => 10804,
        "Service/Exception.pidUnreadable"             => 10805,
        "Service/Exception.pidUnwritable"             => 10806,
        "Service/Exception.pidUncreatable"            => 10807,
        "Service/Exception.pidCorrupt"                => 10808,
        "Service/Exception.pidDuplicate"              => 10809,
        "Service/Exception.pidLocked"                 => 10810,
        "Service/Exception.pidInaccessible"           => 10811,
        "Service/Exception.forkFailed"                => 10812,
    ];

    protected $symbol;
    protected $params;

    public function __construct(string $msgID = "", $vars = null, \Throwable $e = null) {
        $this->symbol = $msgID;
        $this->params = $vars ?? [];
        if ($msgID === "") {
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
            $msg = (Arsse::$lang ?? new Lang)->msg($msg, $vars);
        }
        parent::__construct($msg, $code, $e);
    }

    public function getSymbol(): string {
        return $this->symbol;
    }

    public function getParams(): array {
        return $this->params;
    }
}
