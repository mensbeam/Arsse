<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Service;

use JKingWeb\Arsse\Service\Daemon;
use JKingWeb\Arsse\Service\Exception;
use org\bovigo\vfs\vfsStream;

/** @covers \JKingWeb\Arsse\Service\Daemon */
class TestDaemon extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $pidfiles = [
        'errors' => [
            'create'    => [],
            'read'      => "cannot be read",
            'write'     => "cannot be written to",
            'readwrite' => "can neither be read nor written to",
        ],
        'ok' => [
            'dir' => [],
            'file' => "this file can be fully accessed",
        ],
    ];

    public function setUp(): void {
        parent::setUp();
        $this->daemon = $this->partialMock(Daemon::class);
    }

    /** @dataProvider providePidChecks */
    public function testCheckPidFiles(string $file, bool $accessible, $exp): void {
        $vfs = vfsStream::setup("pidtest", 0777, $this->pidfiles);
        $path = $vfs->url()."/";
        // set up access blocks
        chmod($path."errors/create", 0555);
        chmod($path."errors/read", 0333);
        chmod($path."errors/write", 0555);
        chmod($path."errors/readwrite", 0111);
        // set up mock daemon class
        $this->daemon->resolveRelativePath->returns($accessible ? dirname($path.$file) : false);
        $daemon = $this->daemon->get();
        // perform the test
        if ($exp instanceof \Exception) {
            $this->assertException($exp);
            $daemon->checkPIDFilePath($file);
        } else {
            $this->assertSame($path.$exp, $daemon->checkPIDFilePath($file));
        }
    }

    public function providePidChecks(): iterable {
        return [
            ["ok/file",           false, new Exception("pidDirUnresolvable")],
            ["not/found",         true,  new Exception("pidDirMissing")],
            ["errors/create/pid", true,  new Exception("pidUncreatable")],
            ["errors/read",       true,  new Exception("pidUnreadable")],
            ["errors/write",      true,  new Exception("pidUnwritable")],
            ["errors/readwrite",  true,  new Exception("pidUnusable")],
            ["",                  true,  new Exception("pidNotFile")],
            ["ok/dir",            true,  new Exception("pidNotFile")],
            ["ok/file",           true,  "ok/file"],
            ["ok/dir/file",       true,  "ok/dir/file"],
        ];
    }
}
