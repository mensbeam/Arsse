<?php return [
    'code'    => 304,
    'cache'   => false,
    'fields'  => [
        "ETag: ".$_SERVER['HTTP_IF_NONE_MATCH'],
    ],
];