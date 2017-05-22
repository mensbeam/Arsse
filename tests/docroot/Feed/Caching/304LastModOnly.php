<?php return [
    'code'    => 304,
    'cache'   => false,
    'fields'  => [
        'Last-Modified: '.$_SERVER['HTTP_IF_MODIFIED_SINCE'],
    ],
];