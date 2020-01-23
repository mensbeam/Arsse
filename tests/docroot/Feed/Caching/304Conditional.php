<?php
// this test returns 400 rather than 304 if the values of If-Modified-Since 
// and If-None-Match doesn't match $G_GET['t'] and $_GET['e'] respectively, or
// if the $_GET members are missing
if (
    !($_GET['t'] ?? "") || 
    !($_GET['e'] ?? "") ||
    ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? "") !== gmdate("D, d M Y H:i:s \G\M\T", (int) $_GET['t']) ||
    ($_SERVER['HTTP_IF_NONE_MATCH'] ?? "") !== $_GET['e']
) {
    return [
        'code' => 400,
    ];
} else {
    return [
        'code'    => 304,
        'lastMod' => random_int(0, 2^31),
        'fields' => [
            "ETag: ".bin2hex(random_bytes(8)),
        ],
    ];
}
    
