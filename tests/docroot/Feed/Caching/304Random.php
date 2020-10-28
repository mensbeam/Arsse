<?php return [
    'code'    => 304,
    'lastMod' => random_int(0, 2 ^ 31),
    'fields'  => [
        "ETag: ".bin2hex(random_bytes(8)),
    ],
];
