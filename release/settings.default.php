<?php
return [
    'arch' => [
        'type' => "arch",
        'repos' => ["http://mirror.csclub.uwaterloo.ca/archlinux/core/os/x86_64/", "http://mirror.csclub.uwaterloo.ca/archlinux/extra/os/x86_64/"],
        'keys' => [],
        'dist' => "arch",
        'recipe' => "PKGBUILD",
        'output' => "/usr/src/packages/ARCHPKGS/*.pkg.tar.zst",
    ],
    'debian' => [
        'type' => "debian",
        'repos' => ["http://ftp.ca.debian.org/debian/?dist=buster&component=main"],
        'keys' => [],
        'dist' => "debian10",
        'recipe' => "*.dsc",
        'output' => "/usr/src/packages/DEBS/*.deb",
    ],
];
