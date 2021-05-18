#! /usr/bin/php
<?php
if (posix_geteuid() == 0) {
    $info = posix_getpwnam("arsse");
    if ($info) { 
        posix_setgid($info['gid']);
        posix_setuid($info['uid']);
    }
}
require "/usr/share/webapps/arsse/arsse.php";
