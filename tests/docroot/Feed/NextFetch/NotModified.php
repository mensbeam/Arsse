<?php
if (array_key_exists("t", $_GET)) {
    return [
        'code'    => 304,
        'lastMod' => (int) $_GET['t'],
    ];
} else {
    return [
        'code'    => 304,
        'cache'   => false,
    ];
}
