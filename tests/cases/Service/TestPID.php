<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Service;

use JKingWeb\Arsse\Service\Daemon;
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

    public function setUp(): void {
        parent::setUp();
        $this->daemon = $this->partialMock(Daemon::class);
    }

    /** @dataProvider providePidResolutions */
    public function testResolvePidFiles(string $file, bool $realpath, $exp): void {
        $vfs = vfsStream::setup("pidtest", 0777, $this->pidfiles);
        $path = $vfs->url()."/";
        // set up access blocks
        chmod($path."errors/create", 0555);
        chmod($path."errors/read", 0333);
        chmod($path."errors/write", 0555);
        chmod($path."errors/readwrite", 0111);
        // set up mock daemon class
        $this->daemon->realPath->returns($realpath ? $path.$file : false);
        $daemon = $this->daemon->get();
        // perform the test
        if ($exp instanceof \Exception) {
            $this->assertException($exp);
            $daemon->resolvePID($file);
        } else {
            $this->assertSame($exp, $daemon->resolvePID($file));
        }
    }

    public function providePidResolutions(): iterable {
        return [
            ["errors/create", true, new Exception("pidUncreatable")],
        ];
    }
}
