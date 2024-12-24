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
            'dir'  => [],
            'file' => "this file can be fully accessed",
        ],
        'pid' => [
            'current'    => "2112",
            'stale'      => "42",
            "empty"      => "",
            'malformed1' => "02112",
            'malformed2' => "2112 ",
            'malformed3' => "2112\n",
            'bogus1'     => "bogus",
            'bogus2'     => " ",
            'bogus3'     => "\n",
            'overlong'   => "123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890",
            'locked'     => "", // this file will be locked by the test
            'unreadable' => "", // this file will be chmodded by the test
            'unwritable' => "", // this file will be chmodded by the test
        ],
    ];
    protected $daemon;

    public function setUp(): void {
        parent::setUp();
        $this->daemon = \Phake::partialMock(Daemon::class);
    }

    /** @dataProvider providePathResolutions */
    public function testResolveRelativePaths(string $path, $cwd, $exp): void {
        // set up mock daemon class
        \Phake::when($this->daemon)->cwd->thenReturn($cwd);
        $daemon = $this->daemon->get();
        // perform the test
        $this->AssertSame($exp, $daemon->resolveRelativePath($path));
    }

    public function providePathResolutions(): iterable {
        return [
            ["/",           "/home/me", "/"],
            ["/.",          "/home/me", "/"],
            ["/..",         "/home/me", "/"],
            ["/run",        "/home/me", "/run"],
            ["/./run",      "/home/me", "/run"],
            ["/../run",     "/home/me", "/run"],
            ["/run/../run", "/home/me", "/run"],
            ["/run/./run",  "/home/me", "/run/run"],
            ["run",         "/home/me", "/home/me/run"],
            ["run/..",      "/home/me", "/home/me"],
            [".",           "/",        "/"],
            [".",           false,      false],
        ];
    }

    /** @dataProvider providePidFileChecks */
    public function testCheckPidFiles(string $file, bool $accessible, $exp): void {
        $vfs = vfsStream::setup("pidtest", 0777, $this->pidfiles);
        $path = $vfs->url()."/";
        // set up access blocks
        chmod($path."errors/create", 0555);
        chmod($path."errors/read", 0333);
        chmod($path."errors/write", 0555);
        chmod($path."errors/readwrite", 0111);
        // set up mock daemon class
        \Phake::when($this->daemon)->resolveRelativePath->thenReturn($accessible ? dirname($path.$file) : false);
        $daemon = $this->daemon->get();
        // perform the test
        if ($exp instanceof \Exception) {
            $this->assertException($exp);
            $daemon->checkPIDFilePath($file);
        } else {
            $this->assertSame($path.$exp, $daemon->checkPIDFilePath($file));
        }
    }

    public function providePidFileChecks(): iterable {
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

    /** @dataProvider providePidReadChecks */
    public function testCheckPidReads(string $file, $exp) {
        $vfs = vfsStream::setup("pidtest", 0777, $this->pidfiles);
        $path = $vfs->url()."/pid/";
        // set up access blocks
        $f = fopen($path."locked", "r+");
        flock($f, \LOCK_EX | \LOCK_NB);
        chmod($path."unreadable", 0333);
        chmod($path."unwritable", 0555);
        // set up mock daemon class
        \Phake::when($this->daemon)->processExists(2112)->thenReturn(true);
        \Phake::when($this->daemon)->processExists(42)->thenReturn(false);
        $daemon = $this->daemon->get();
        // perform the test
        try {
            if ($exp instanceof \Exception) {
                $this->assertException($exp);
                $daemon->checkPID($path.$file);
            } else {
                $this->assertSame($exp, $daemon->checkPID($path.$file));
            }
        } finally {
            flock($f, \LOCK_UN);
            fclose($f);
        }
    }

    public function providePidReadChecks(): iterable {
        return [
            ["current",    new Exception("pidDuplicate")],
            ["malformed1", new Exception("pidCorrupt")],
            ["malformed2", new Exception("pidCorrupt")],
            ["malformed3", new Exception("pidCorrupt")],
            ["bogus1",     new Exception("pidCorrupt")],
            ["bogus2",     new Exception("pidCorrupt")],
            ["bogus3",     new Exception("pidCorrupt")],
            ["overlong",   new Exception("pidCorrupt")],
            ["unreadable", new Exception("pidInaccessible")],
            ["unwritable", null],
            ["locked",     null],
            ["missing",    null],
            ["stale",      null],
            ["empty",      null],
        ];
    }

    /**
     * @dataProvider providePidWriteChecks
     * @requires extension posix
     */
    public function testCheckPidWrites(string $file, $exp) {
        $pid = (string) posix_getpid();
        $vfs = vfsStream::setup("pidtest", 0777, $this->pidfiles);
        $path = $vfs->url()."/pid/";
        // set up access blocks
        $f = fopen($path."locked", "r+");
        flock($f, \LOCK_EX | \LOCK_NB);
        chmod($path."unreadable", 0333);
        chmod($path."unwritable", 0555);
        // set up mock daemon class
        \Phake::when($this->daemon)->processExists(2112)->thenReturn(true);
        \Phake::when($this->daemon)->processExists(42)->thenReturn(false);
        $daemon = $this->daemon->get();
        // perform the test
        try {
            if ($exp instanceof \Exception) {
                $this->assertException($exp);
                $exp = $this->pidfiles['pid'][$file] ?? false;
                $daemon->writePID($path.$file);
            } else {
                $this->assertSame($exp, $daemon->writePID($path.$file));
                $exp = $pid;
            }
        } finally {
            flock($f, \LOCK_UN);
            fclose($f);
            chmod($path."unreadable", 0777);
            $this->assertSame($exp, @file_get_contents($path.$file));
        }
    }

    public function providePidWriteChecks(): iterable {
        return [
            ["current",    new Exception("pidDuplicate")],
            ["malformed1", new Exception("pidCorrupt")],
            ["malformed2", new Exception("pidCorrupt")],
            ["malformed3", new Exception("pidCorrupt")],
            ["bogus1",     new Exception("pidCorrupt")],
            ["bogus2",     new Exception("pidCorrupt")],
            ["bogus3",     new Exception("pidCorrupt")],
            ["overlong",   new Exception("pidCorrupt")],
            ["unreadable", new Exception("pidInaccessible")],
            ["unwritable", new Exception("pidInaccessible")],
            ["locked",     new Exception("pidLocked")],
            ["missing",    null],
            ["stale",      null],
            ["empty",      null],
        ];
    }
}
