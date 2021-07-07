<?php
/***
    Please refer to config.defaults.php or the manual at /usr/share/doc/arsse/
    for possible configuration parameters.

    The last line includes database auto-configuration information which
    Debian may have created during installation; any database-related 
    configuration defined in this file will override anything defined in the 
    included file.
***/

return [
    'dbAutoUpdate' => true,
]
+ (@include "/usr/share/arsse/dbconfig-common.php");
