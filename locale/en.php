<?php
return [
	"Exception.JKingWeb/NewsSync/Lang/Exception.defaultFileMissing"		=> "Default language file \"{0}\" missing",
	"Exception.JKingWeb/NewsSync/Lang/Exception.fileMissing"			=> "Language file \"{0}\" is not available",
	"Exception.JKingWeb/NewsSync/Lang/Exception.fileUnreadable"			=> "Insufficient permissions to read language file \"{0}\"",
	"Exception.JKingWeb/NewsSync/Lang/Exception.fileCorrupt"			=> "Language file \"{0}\" is corrupt or does not conform to expected format",
	"Exception.JKingWeb/NewsSync/Lang/Exception.stringMissing" 			=> "Message string \"{msgID}\" missing from all loaded language files ({fileList})",
	"Exception.JKingWeb/NewsSync/Lang/Exception.stringInvalid" 			=> "Message string \"{msgID}\" is not a valid ICU message string (language files loaded: {fileList})",

	"Exception.JKingWeb/NewsSync/Conf/Exception.fileMissing"			=> "Configuration file \"{0}\" does not exist",
	"Exception.JKingWeb/NewsSync/Conf/Exception.fileUnreadable"			=> "Insufficient permissions to read configuration file \"{0}\"",
	"Exception.JKingWeb/NewsSync/Conf/Exception.fileUncreatable"		=> "Insufficient permissions to write new configuration file \"{0}\"",
	"Exception.JKingWeb/NewsSync/Conf/Exception.fileUnwritable"			=> "Insufficient permissions to overwrite configuration file \"{0}\"",
	"Exception.JKingWeb/NewsSync/Conf/Exception.fileCorrupt"			=> "Configuration file \"{0}\" is corrupt or does not conform to expected format",
	
	"Exception.JKingWeb/NewsSync/Db/Exception.extMissing"				=> "Required PHP extension for driver \"{0}\" not installed",
	"Exception.JKingWeb/NewsSync/Db/Exception.fileMissing"				=> "Database file \"{0}\" does not exist",
	"Exception.JKingWeb/NewsSync/Db/Exception.fileUnreadable"			=> "Insufficient permissions to open database file \"{0}\" for reading",
	"Exception.JKingWeb/NewsSync/Db/Exception.fileUnwritable"			=> "Insufficient permissions to open database file \"{0}\" for writing",
	"Exception.JKingWeb/NewsSync/Db/Exception.fileUnusable"				=> "Insufficient permissions to open database file \"{0}\" for reading or writing",
	"Exception.JKingWeb/NewsSync/Db/Exception.fileUncreatable"			=> "Insufficient permissions to create new database file \"{0}\"",
	"Exception.JKingWeb/NewsSync/Db/Exception.fileCorrupt"				=> "Database file \"{0}\" is corrupt or not a valid database",
	"Exception.JKingWeb/NewsSync/Db/ExceptionUpdate.manual"				=> 
		"{from_version, select, 
			0 {{driver_name} database is configured for manual updates and is not initialized; please populate the database with the base schema}	
			other {{driver_name} database is configured for manual updates; please update from schema version {from_version} to version {to_version}}
		}",
	"Exception.JKingWeb/NewsSync/Db/ExceptionUpdate.failed"				=>
		"{reason select,
			missing {Automatic updating of the {driver_name} database failed because instructions for updating from version {from_version} are not available}
		}",
	"Exception.JKingWeb/NewsSync/Db/ExceptionUpdate.tooNew"				=> "Automatic updating of the {driver_name} database failed because its version, {current}, is newer than the requested version, {target}"
];