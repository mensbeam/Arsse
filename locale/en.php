<?php
return [
    'Driver.User.Internal.Name'                                        => 'Internal',

    'Exception.JKingWeb/NewsSync/Exception.uncoded'                     => 'The specified exception symbol {0} has no code specified in Exception.php',
    //this should not usually be encountered
    'Exception.JKingWeb/NewsSync/Exception.unknown'                     => 'An unknown error has occurred',

    'Exception.JKingWeb/NewsSync/Lang/Exception.defaultFileMissing'     => 'Default language file "{0}" missing',
    'Exception.JKingWeb/NewsSync/Lang/Exception.fileMissing'            => 'Language file "{0}" is not available',
    'Exception.JKingWeb/NewsSync/Lang/Exception.fileUnreadable'         => 'Insufficient permissions to read language file "{0}"',
    'Exception.JKingWeb/NewsSync/Lang/Exception.fileCorrupt'            => 'Language file "{0}" is corrupt or does not conform to expected format',
    'Exception.JKingWeb/NewsSync/Lang/Exception.stringMissing'          => 'Message string "{msgID}" missing from all loaded language files ({fileList})',
    'Exception.JKingWeb/NewsSync/Lang/Exception.stringInvalid'          => 'Message string "{msgID}" is not a valid ICU message string (language files loaded: {fileList})',

    'Exception.JKingWeb/NewsSync/Conf/Exception.fileMissing'            => 'Configuration file "{0}" does not exist',
    'Exception.JKingWeb/NewsSync/Conf/Exception.fileUnreadable'         => 'Insufficient permissions to read configuration file "{0}"',
    'Exception.JKingWeb/NewsSync/Conf/Exception.fileUncreatable'        => 'Insufficient permissions to write new configuration file "{0}"',
    'Exception.JKingWeb/NewsSync/Conf/Exception.fileUnwritable'         => 'Insufficient permissions to overwrite configuration file "{0}"',
    'Exception.JKingWeb/NewsSync/Conf/Exception.fileCorrupt'            => 'Configuration file "{0}" is corrupt or does not conform to expected format',

    'Exception.JKingWeb/NewsSync/Db/Exception.extMissing'               => 'Required PHP extension for driver "{0}" not installed',
    'Exception.JKingWeb/NewsSync/Db/Exception.fileMissing'              => 'Database file "{0}" does not exist',
    'Exception.JKingWeb/NewsSync/Db/Exception.fileUnreadable'           => 'Insufficient permissions to open database file "{0}" for reading',
    'Exception.JKingWeb/NewsSync/Db/Exception.fileUnwritable'           => 'Insufficient permissions to open database file "{0}" for writing',
    'Exception.JKingWeb/NewsSync/Db/Exception.fileUnusable'             => 'Insufficient permissions to open database file "{0}" for reading or writing',
    'Exception.JKingWeb/NewsSync/Db/Exception.fileUncreatable'          => 'Insufficient permissions to create new database file "{0}"',
    'Exception.JKingWeb/NewsSync/Db/Exception.fileCorrupt'              => 'Database file "{0}" is corrupt or not a valid database',

    'Exception.JKingWeb/NewsSync/Db/Update/Exception.manual'            =>
        '{from_version, select,
            0 {{driver_name} database is configured for manual updates and is not initialized; please populate the database with the base schema}
            other {{driver_name} database is configured for manual updates; please update from schema version {current} to version {target}}
        }',
    'Exception.JKingWeb/NewsSync/Db/Update/Exception.manualOnly'        =>
        '{from_version, select,
            0 {{driver_name} database must be updated manually and is not initialized; please populate the database with the base schema}
            other {{driver_name} database must be updated manually; please update from schema version {current} to version {target}}
        }',
    'Exception.JKingWeb/NewsSync/Db/Update/Exception.fileMissing'       => 'Automatic updating of the {driver_name} database failed due to instructions for updating from version {current} not being available',
    'Exception.JKingWeb/NewsSync/Db/Update/Exception.fileUnreadable'    => 'Automatic updating of the {driver_name} database failed due to insufficient permissions to read instructions for updating from version {current}',
    'Exception.JKingWeb/NewsSync/Db/Update/Exception.fileUnusable'      => 'Automatic updating of the {driver_name} database failed due to an error reading instructions for updating from version {current}',
    'Exception.JKingWeb/NewsSync/Db/Update/Exception.tooNew'            =>
        '{difference, select,
            0 {Automatic updating of the {driver_name} database failed because it is already up to date with the requested version, {target}}
            other {Automatic updating of the {driver_name} database failed because its version, {current}, is newer than the requested version, {target}}
        }',

    'Exception.JKingWeb/NewsSync/User/Exception.alreadyExists'          => 'Could not perform action "{action}" because the user {user} already exists',
    'Exception.JKingWeb/NewsSync/User/Exception.doesNotExist'           => 'Could not perform action "{action}" because the user {user} does not exist',
    'Exception.JKingWeb/NewsSync/User/Exception.authMissing'            => 'Please log in to proceed',
    'Exception.JKingWeb/NewsSync/User/Exception.authFailed'             => 'Authentication failed',
    'Exception.JKingWeb/NewsSync/User/ExceptionAuthz.notAuthorized'     => 
        /*'{action, select,
            userList {{user, select,
                "*" {Authenticated user is not authorized to view the global user list}
                other {Authenticated user is not authorized to view the user list for domain {user}}
            }}
            other {Authenticated user is not authorized to perform the action "{action}" on behalf of {user}}
        }',*/
        '{action, select,
            userList {{user, select,
                global {Authenticated user is not authorized to view the global user list}
                other {Authenticated user is not authorized to view the user list for domain {user}}
            }}
            other {Authenticated user is not authorized to perform the action "{action}" on behalf of {user}}
        }',

    'Exception.JKingWeb/NewsSync/Feed/Exception.invalidCertificate'     => 'Could not download feed "{url}" because its server is serving an invalid SSL certificate',
    'Exception.JKingWeb/NewsSync/Feed/Exception.invalidURL'             => 'Feed URL "{url}" is invalid',
    'Exception.JKingWeb/NewsSync/Feed/Exception.maxRedirect'            => 'Could not download feed "{url}" because its server reached its maximum number of HTTP redirections',
    'Exception.JKingWeb/NewsSync/Feed/Exception.maxSize'                => 'Could not download feed "{url}" because its size exceeds the maximum allowed on its server',
    'Exception.JKingWeb/NewsSync/Feed/Exception.timeout'                => 'Could not download feed "{url}" because its server timed out',
    'Exception.JKingWeb/NewsSync/Feed/Exception.forbidden'              => 'Could not download feed "{url}" because you do not have permission to access it',
    'Exception.JKingWeb/NewsSync/Feed/Exception.unauthorized'           => 'Could not download feed "{url}" because you provided insufficient or invalid credentials',
    'Exception.JKingWeb/NewsSync/Feed/Exception.malformed'              => 'Could not parse feed "{url}" because it is malformed',
    'Exception.JKingWeb/NewsSync/Feed/Exception.xmlEntity'              => 'Refused to parse feed "{url}" because it contains an XXE attack',
    'Exception.JKingWeb/NewsSync/Feed/Exception.subscriptionNotFound'   => 'Unable to find a feed at location "{url}"',
    'Exception.JKingWeb/NewsSync/Feed/Exception.unsupportedFormat'      => 'Feed "{url}" is of an unsupported format'
];