<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Service;

use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Service\Exception;
use org\bovigo\vfs\vfsStream;

/** @covers \JKingWeb\Arsse\Service */
class TestPID extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $pidfiles = [
        'errors' => [
            'create'    => [],
            'read'      => "",
            'write'     => "",
            'readwrite' => "",
        ],
        'ok' => [
            'dir' => [],
            'file' => "",
        ],
    ];

    /** @dataProvider providePidResolutions */
    public function testResolvePidFiles(string $file, bool $realpath, $exp): void {
        $vfs = vfsStream::setup("pidtest", 0777, $this->pidfiles);
        $path = $vfs->url()."/";
        // set up access blocks
        chmod($path."errors/create", 0555);
        chmod($path."errors/read", 0333);
        chmod($path."errors/write", 0555);
        chmod($path."errors/readwrite", 0111);
        // set up mock CLI
        $this->cli->realPath->returns($realpath ? $path.$file : false);
        $cli = $this->cli->get();
        // perform the test
        if ($exp instanceof \Exception) {
            $this->assertException($exp);
            $cli->resolvePID($file);
        } else {
            $this->assertSame($exp, $cli->resolvePID($file));
        }
    }

    public function providePidResolutions(): iterable {
        return [
            ["errors/create", true, new Exception("pidUncreatable")],
        ];
    }
}
