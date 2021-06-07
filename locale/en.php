<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

return [
    'CLI.Auth.Success'                                                     => 'Authentication successful',
    'CLI.Auth.Failure'                                                     => 'Authentication failed',

    'API.Miniflux.DefaultCategoryName'                                     => "All",
    'API.Miniflux.ImportSuccess'                                           => 'Feeds imported successfully',
    'API.Miniflux.Error.401'                                               => 'Access Unauthorized',
    'API.Miniflux.Error.403'                                               => 'Access Forbidden',
    'API.Miniflux.Error.404'                                               => 'Resource Not Found',
    'API.Miniflux.Error.MissingInputValue'                                 => 'Required key "{field}" was not present in input',
    'API.Miniflux.Error.DuplicateInputValue'                               => 'Key "{field}" accepts only one value',
    'API.Miniflux.Error.InvalidBodyJSON'                                   => 'Invalid JSON payload: {0}',
    'API.Miniflux.Error.InvalidBodyXML'                                    => 'Invalid XML payload',
    'API.Miniflux.Error.InvalidBodyOPML'                                   => 'Payload is not a valid OPML document',
    'API.Miniflux.Error.InvalidInputType'                                  => 'Input key "{field}" of type {actual} was expected as {expected}',
    'API.Miniflux.Error.InvalidInputValue'                                 => 'Supplied value is not valid for input key "{field}"',
    'API.Miniflux.Error.Fetch404'                                          => 'Resource not found (404), this feed doesn\'t exists anymore, check the feed URL',
    'API.Miniflux.Error.Fetch401'                                          => 'You are not authorized to access this resource (invalid username/password)',
    'API.Miniflux.Error.Fetch403'                                          => 'Unable to fetch this resource (Status Code = 403)',
    'API.Miniflux.Error.FetchOther'                                        => 'Unable to fetch this resource',
    'API.Miniflux.Error.FetchFormat'                                       => 'Unsupported feed format',
    'API.Miniflux.Error.DuplicateCategory'                                 => 'This category already exists.',
    'API.Miniflux.Error.InvalidCategory'                                   => 'Invalid category title',
    'API.Miniflux.Error.MissingCategory'                                   => 'This category does not exist or does not belong to this user.',
    'API.Miniflux.Error.InvalidElevation'                                  => 'Only administrators can change permissions of standard users',
    'API.Miniflux.Error.DuplicateUser'                                     => 'The user name "{user}" already exists',
    'API.Miniflux.Error.DuplicateFeed'                                     => 'This feed already exists.',
    'API.Miniflux.Error.InvalidTitle'                                      => 'Invalid feed title',
    'API.Miniflux.Error.InvalidImportCategory'                             => 'Payload contains an invalid category name',
    'API.Miniflux.Error.DuplicateImportCategory'                           => 'Payload contains the same category name twice',
    'API.Miniflux.Error.FailedImportFeed'                                  => 'Unable to import feed at URL "{url}" (code {code}',
    'API.Miniflux.Error.InvalidImportLabel'                                => 'Payload contains an invalid label name',

    'API.TTRSS.Category.Uncategorized'                                     => 'Uncategorized',
    'API.TTRSS.Category.Special'                                           => 'Special',
    'API.TTRSS.Category.Labels'                                            => 'Labels',
    'API.TTRSS.Feed.All'                                                   => 'All articles',
    'API.TTRSS.Feed.Fresh'                                                 => 'Fresh articles',
    'API.TTRSS.Feed.Starred'                                               => 'Starred articles',
    'API.TTRSS.Feed.Published'                                             => 'Published articles',
    'API.TTRSS.Feed.Archived'                                              => 'Archived articles',
    'API.TTRSS.Feed.Read'                                                  => 'Recently read',
    'API.TTRSS.FeedCount'                                                  => '({0, number} {0, plural, one {feed} other {feeds}})',

    'Driver.Db.SQLite3.Name'                                               => 'SQLite 3',
    'Driver.Db.SQLite3PDO.Name'                                            => 'SQLite 3 (PDO)',
    'Driver.Db.PostgreSQL.Name'                                            => 'PostgreSQL',
    'Driver.Db.PostgreSQLPDO.Name'                                         => 'PostgreSQL (PDO)',
    'Driver.Db.MySQL.Name'                                                 => 'MySQL',
    'Driver.Db.MySQLPDO.Name'                                              => 'MySQL (PDO)',

    'Driver.Service.Serial.Name'                                           => 'Serialized',
    'Driver.Service.Subprocess.Name'                                       => 'Concurrent subprocess',

    'Driver.User.Internal.Name'                                            => 'Internal',

    // indicates programming error
    'Exception.JKingWeb/Arsse/Exception.uncoded'                           => 'The specified exception symbol {0} has no code specified in AbstractException.php',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Exception.unknown'                           => 'An unknown error has occurred',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Exception.constantUnknown'                   => 'Supplied constant value ({0}) is unknown or invalid in the context in which it was used',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Exception.arrayEmpty'                        => 'Supplied array "{0}" is empty, but should have at least one element',
    'Exception.JKingWeb/Arsse/ExceptionType.strictFailure'                 => 'Supplied value could not be normalized to {0, select,
        1 {null}
        2 {boolean}
        3 {integer}
        4 {float}
        5 {datetime}
        6 {string}
        7 {array}
        8 {DateInterval}
        other {requested type {0}}
     }',
     // indicates programming error
    'Exception.JKingWeb/Arsse/ExceptionType.typeUnknown'                   => 'Normalization type {0} is  not implemented',
    'Exception.JKingWeb/Arsse/Lang/Exception.defaultFileMissing'           => 'Default language file "{0}" missing',
    'Exception.JKingWeb/Arsse/Lang/Exception.fileMissing'                  => 'Language file "{0}" is not available',
    'Exception.JKingWeb/Arsse/Lang/Exception.fileUnreadable'               => 'Insufficient permissions to read language file "{0}"',
    'Exception.JKingWeb/Arsse/Lang/Exception.fileCorrupt'                  => 'Language file "{0}" is corrupt or does not conform to expected format',
    'Exception.JKingWeb/Arsse/Lang/Exception.stringMissing'                => 'Message string "{msgID}" missing from all loaded language files ({fileList})',
    'Exception.JKingWeb/Arsse/Lang/Exception.stringInvalid'                => 'Message string "{msgID}" is not a valid ICU message string (language files loaded: {fileList}): {error}',
    'Exception.JKingWeb/Arsse/Lang/Exception.dataInvalid'                  => 'Failed to format message message string "{msgID}" (language files loaded: {fileList}): {error}',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileMissing'                  => 'Configuration file "{0}" does not exist',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileUnreadable'               => 'Insufficient permissions to read configuration file "{0}"',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileUncreatable'              => 'Insufficient permissions to write new configuration file "{0}"',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileUnwritable'               => 'Insufficient permissions to overwrite configuration file "{0}"',
    'Exception.JKingWeb/Arsse/Conf/Exception.fileCorrupt'                  => 'Configuration file "{0}" is corrupt or does not conform to expected format',
    'Exception.JKingWeb/Arsse/Conf/Exception.typeMismatch'                 => 
        'Configuration parameter "{param}" in file "{file}" must be {type, select,
            integer {an integral number}
            string {a character string}
            boolean {either true or false}
            float {a decimal number}
            interval {an ISO 8601 time interval}
            other {consistent with type "{type}"}
        }{nullable, select,
            0 {}
            other {, or null}
        }',
    'Exception.JKingWeb/Arsse/Conf/Exception.semanticMismatch'             => 'Configuration parameter "{param}" in file "{file}" is not a valid value. Consult the documentation for possible values',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Conf/Exception.ambiguousDefault'             => 'Preferred type of configuration parameter "{param}" could not be inferred from its default value. The parameter must be added to the Conf::EXPECTED_TYPES array',
    'Exception.JKingWeb/Arsse/Db/Exception.extMissing'                     => 'Required PHP extension for driver "{0}" not installed',
    'Exception.JKingWeb/Arsse/Db/Exception.fileMissing'                    => 'Database file "{0}" does not exist',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUnreadable'                 => 'Insufficient permissions to open database file "{0}" for reading',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUnwritable'                 => 'Insufficient permissions to open database file "{0}" for writing',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUnusable'                   => 'Insufficient permissions to open database file "{0}" for reading or writing',
    'Exception.JKingWeb/Arsse/Db/Exception.fileUncreatable'                => 'Insufficient permissions to create new database file "{0}"',
    'Exception.JKingWeb/Arsse/Db/Exception.fileCorrupt'                    => 'Database file "{0}" is corrupt or not a valid database',
    'Exception.JKingWeb/Arsse/Db/Exception.connectionFailure'              => 'Could not connect to {engine} database: {message}',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Db/Exception.paramTypeInvalid'               => 'Prepared statement parameter type "{0}" is invalid',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Db/Exception.paramTypeUnknown'               => 'Prepared statement parameter type "{0}" is valid, but not implemented',
    'Exception.JKingWeb/Arsse/Db/Exception.paramTypeMissing'               => 'Prepared statement parameter type for parameter #{0} was not specified',
    'Exception.JKingWeb/Arsse/Db/Exception.updateManual'                   =>
        '{from_version, select,
            0 {{driver_name} database is configured for manual updates and is not initialized; please populate the database with the base schema}
            other {{driver_name} database is configured for manual updates; please update from schema version {current} to version {target}}
        }',
    'Exception.JKingWeb/Arsse/Db/Exception.updateManualOnly'               =>
        '{from_version, select,
            0 {{driver_name} database must be updated manually and is not initialized; please populate the database with the base schema}
            other {{driver_name} database must be updated manually; please update from schema version {current} to version {target}}
        }',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileMissing'              => 'Automatic updating of the {driver_name} database failed due to instructions for updating from version {current} not being available',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileUnreadable'           => 'Automatic updating of the {driver_name} database failed due to insufficient permissions to read instructions for updating from version {current}',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileUnusable'             => 'Automatic updating of the {driver_name} database failed due to an error reading instructions for updating from version {current}',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileError'                => 'Automatic updating of the {driver_name} database failed updating from version {current} with the following error: "{message}"',
    'Exception.JKingWeb/Arsse/Db/Exception.updateFileIncomplete'           => 'Automatic updating of the {driver_name} database failed due to instructions for updating from version {current} being incomplete',
    'Exception.JKingWeb/Arsse/Db/Exception.updateTooNew'                   =>
        '{difference, select,
            0 {Automatic updating of the {driver_name} database failed because it is already up to date with the requested version, {target}}
            other {Automatic updating of the {driver_name} database failed because its version, {current}, is newer than the requested version, {target}}
        }',
    'Exception.JKingWeb/Arsse/Db/Exception.engineErrorGeneral'             => '{0}',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Db/Exception.savepointStatusUnknown'         => 'Savepoint status code {0} not implemented',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Db/Exception.savepointInvalid'               => 'Tried to {action} invalid savepoint {index}',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Db/Exception.savepointStale'                 => 'Tried to {action} stale savepoint {index}',
    // indicates programming error
    'Exception.JKingWeb/Arsse/Db/Exception.resultReused'                   => 'Result set already iterated',
    'Exception.JKingWeb/Arsse/Db/ExceptionRetry.schemaChange'              => '{0}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.invalidValue'              => 'Value of field "{field}" of action "{action}" is invalid',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.missing'                   => 'Required field "{field}" missing while performing action "{action}"',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.whitespace'                => 'Field "{field}" of action "{action}" may not contain only whitespace',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.tooLong'                   => 'Field "{field}" of action "{action}" has a maximum length of {max}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.tooShort'                  => 'Field "{field}" of action "{action}" has a minimum length of {min}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.typeViolation'             => 'Field "{field}" of action "{action}" expects a value of type "{type}"',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.subjectMissing'            => 'Referenced ID ({id}) in field "{field}" does not exist',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.idMissing'                 => 'Referenced ID ({id}) in field "{field}" does not exist',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.circularDependence'        => 'Referenced ID ({id}) in field "{field}" creates a circular dependence',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.constraintViolation'       => 'Specified value in field "{field}" already exists',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.engineConstraintViolation' => '{0}',
    'Exception.JKingWeb/Arsse/Db/ExceptionInput.engineTypeViolation'       => '{0}',
    'Exception.JKingWeb/Arsse/Db/ExceptionTimeout.general'                 => '{0}',
    'Exception.JKingWeb/Arsse/Db/ExceptionTimeout.logicalLock'             => 'Database is locked',
    'Exception.JKingWeb/Arsse/User/ExceptionConflict.alreadyExists'        => 'Could not perform action "{action}" because the user {user} already exists',
    'Exception.JKingWeb/Arsse/User/ExceptionConflict.doesNotExist'         => 'Could not perform action "{action}" because the user {user} does not exist',
    'Exception.JKingWeb/Arsse/User/Exception.authMissing'                  => 'Please log in to proceed',
    'Exception.JKingWeb/Arsse/User/Exception.authFailed'                   => 'Authentication failed',
    'Exception.JKingWeb/Arsse/User/ExceptionSession.invalid'               => 'Session with ID {0} does not exist',
    'Exception.JKingWeb/Arsse/User/ExceptionInput.invalidUsername'         => 'User names may not contain the Unicode character {0}',
    'Exception.JKingWeb/Arsse/User/ExceptionInput.invalidValue'            =>
        'User property "{field}" must be {type, select,
            1 {null}
            2 {true or false}
            3 {an integer}
            4 {a real number}
            5 {a DateTime object}
            6 {a string}
            7 {an array}
            8 {a DateInterval object}
            other {another type}
         }',
    'Exception.JKingWeb/Arsse/User/ExceptionInput.invalidTimezone'         => 'User property "{field}" must be a valid zoneinfo timezone',
    'Exception.JKingWeb/Arsse/User/ExceptionInput.invalidNonZeroInteger'   => 'User property "{field}" must be greater than zero',
    'Exception.JKingWeb/Arsse/Feed/Exception.internalError'                => 'Could not download feed "{url}" because of an internal error which is probably a bug',
    'Exception.JKingWeb/Arsse/Feed/Exception.invalidCertificate'           => 'Could not download feed "{url}" because its server is serving an invalid SSL certificate',
    'Exception.JKingWeb/Arsse/Feed/Exception.invalidUrl'                   => 'Feed URL "{url}" is invalid',
    'Exception.JKingWeb/Arsse/Feed/Exception.maxRedirect'                  => 'Could not download feed "{url}" because its server reached its maximum number of HTTP redirections',
    'Exception.JKingWeb/Arsse/Feed/Exception.maxSize'                      => 'Could not download feed "{url}" because its size exceeds the maximum allowed on its server',
    'Exception.JKingWeb/Arsse/Feed/Exception.timeout'                      => 'Could not download feed "{url}" because its server timed out',
    'Exception.JKingWeb/Arsse/Feed/Exception.forbidden'                    => 'Could not download feed "{url}" because you do not have permission to access it',
    'Exception.JKingWeb/Arsse/Feed/Exception.unauthorized'                 => 'Could not download feed "{url}" because you provided insufficient or invalid credentials',
    'Exception.JKingWeb/Arsse/Feed/Exception.transmissionError'            => 'Could not download feed "{url}" because of a network error',
    'Exception.JKingWeb/Arsse/Feed/Exception.connectionFailed'             => 'Could not download feed "{url}" because its server could not be reached',
    'Exception.JKingWeb/Arsse/Feed/Exception.malformedXml'                 => 'Could not parse feed "{url}" because it is malformed',
    'Exception.JKingWeb/Arsse/Feed/Exception.xmlEntity'                    => 'Refused to parse feed "{url}" because it contains an XXE attack',
    'Exception.JKingWeb/Arsse/Feed/Exception.subscriptionNotFound'         => 'Unable to find a feed at location "{url}"',
    'Exception.JKingWeb/Arsse/Feed/Exception.unsupportedFeedFormat'        => 'Feed "{url}" is of an unsupported format',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.fileMissing'          => 'Import {type} file "{file}" does not exist',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.fileUnreadable'       => 'Insufficient permissions to read {type} file "{file}" for import',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.fileUncreatable'      => 'Insufficient permissions to write {type} export to file "{file}"',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.fileUnwritable'       => 'Insufficient permissions to write {type} export to existing file "{file}"',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.invalidSyntax'        => 'Input data syntax error at line {line}, column {column}',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.invalidSemantics'     => 'Input data is not valid {type} data',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.invalidFolderName'    => 'Input data contains an invalid folder name',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.invalidFolderCopy'    => 'Input data contains multiple folders of the same name under the same parent',
    'Exception.JKingWeb/Arsse/ImportExport/Exception.invalidTagName'       => 'Input data contains an invalid tag name',
    'Exception.JKingWeb/Arsse/Rule/Exception.invalidPattern'               => 'Specified rule pattern is invalid',
    'Exception.JKingWeb/Arsse/CLI/Exception.pidNotFile'                    => 'Specified PID file location "{pidfile}" must be a regular file',
    'Exception.JKingWeb/Arsse/CLI/Exception.pidDirNotFound'                => 'Parent directory "{piddir}" of PID file does not exist',
    'Exception.JKingWeb/Arsse/CLI/Exception.pidUnwritable'                 => 'Insufficient permissions to open PID file "{pidfile}" for writing',
    'Exception.JKingWeb/Arsse/CLI/Exception.pidNotFile'                    => 'Specified PID file location "{pidfile}" must be a regular file',
];
