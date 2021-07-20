<?php
return [
    'arch' => [
        'type'   => "arch",
        'repos'  => ["http://mirror.csclub.uwaterloo.ca/archlinux/core/os/x86_64/", "http://mirror.csclub.uwaterloo.ca/archlinux/extra/os/x86_64/"],
        'keys'   => [],
        'dist'   => "arch",
        'recipe' => "PKGBUILD",
        'output' => "/usr/src/packages/ARCHPKGS/*.pkg.tar.zst",
    ],
    'debian' => [
        'type'   => "debian",
        'repos'  => ["http://ftp.ca.debian.org/debian/?dist=buster&component=main"],
        'keys'   => [],
        'dist'   => "debian10",
        'recipe' => "*.dsc",
        'output' => "/usr/src/packages/DEBS/*.deb",
    ],
    'suse' => [
        'type'  => "rpm",
        'repos' => ["http://mirror.csclub.uwaterloo.ca/opensuse/distribution/leap/15.3/repo/oss/"],
        'keys'  => ["gpg-pubkey-39db7c82-5f68629b", "gpg-pubkey-65176565-5d94a381", "gpg-pubkey-307e3d54-5aaa90a5", "gpg-pubkey-3dbdc284-53674dd4"],
        'dist'  => "sl15.3",
        'recipe' => "arsse.spec",
        'output' => "/home/abuild/rpmbuild/RPMS/noarch/*.rpm",
    ],
];
