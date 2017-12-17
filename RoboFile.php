<?php

use Robo\Result;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {
    const BASE = __DIR__.\DIRECTORY_SEPARATOR;
    const BASE_TEST = self::BASE."tests".\DIRECTORY_SEPARATOR;

    /** Runs the full test suite */
    public function test(array $args): Result {
        // start the built-in PHP server, which is required for some of the tests
        $this->taskServer(8000)->host("localhost")->dir(self::BASE_TEST."docroot")->rawArg("-n")->arg(self::BASE_TEST."server.php")->background()->run();
        // run tests
        $execpath = realpath(self::BASE."vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit");
        $confpath = realpath(self::BASE_TEST."phpunit.xml");
        return $this->taskExec("php")->arg($execpath)->option("-c", $confpath)->args($args)->run();
    }

    /** Runs a quick subset of the test suite */
    public function testQuick(array $args): Result {
        return $this->test(array_merge(["--exclude-group","slow"], $args));
    }

    public function coverage(array $args): Result {
        // start the built-in PHP server, which is required for some of the tests
        $this->taskServer(8000)->host("localhost")->dir(self::BASE_TEST."docroot")->rawArg("-n")->arg(self::BASE_TEST."server.php")->background()->run();
        // run tests with code coverage reporting enabled
        $exec = $this->findCoverageEngine();
        $execpath = realpath(self::BASE."vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit");
        $confpath = realpath(self::BASE_TEST."phpunit.xml");
        return $this->taskExec($exec)->arg($execpath)->option("-c", $confpath)->option("--coverage-html", self::BASE_TEST."coverage")->args($args)->run();
        // run the test suite with code coverage reporting enabled
        return $this->test(array_merge(["--coverage-html",self::BASE_TEST."coverage"], $args));
    }

    protected function findCoverageEngine(): string {
        $null = null;
        $code = 0;
        exec("phpdbg --version", $null, $code);
        if (!$code) {
            return "phpdbg -qrr";
        } else {
            return "php";
        }
    }

    public function package(array $args): Result {
        // establish which commit to package
        $version = $args ? $args[0] : $this->askDefault("Commit to package:", "head");
        $archive = self::BASE."arsse-$version.tar.gz";
        // start a collection
        $t = $this->collectionBuilder();
        // create a temporary directory
        $dir = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // create a Git worktree for the selected commit in the temp location
        $t->taskExec("git worktree add ".escapeshellarg($dir)." ".escapeshellarg($version));
        // perform Composer installation in the temp location
        $t->taskComposerInstall()->dir($dir)->noDev()->optimizeAutoloader()->arg("--no-scripts");
        // delete unwanted files
        $t->taskFilesystemStack()->remove([
            $dir.".git",
            $dir.".gitignore",
            $dir.".gitattributes",
            $dir."composer.json",
            $dir."composer.lock",
            $dir.".php_cs.dist",
            $dir."phpdoc.dist.xml",
            $dir."build.xml",
            $dir."RoboFile.php",
            $dir."CONTRIBUTING.md",
            $dir."tests",
            $dir."vendor-bin",
            $dir."robo",
            $dir."robo.bat",
        ]);
        // generate a sample configuration file
        $t->taskExec("php arsse.php conf save-defaults config.defaults.php")->dir($dir);
        // package it all up
        $t->taskPack($archive)->addDir("arsse", $dir);
        // execute the collection
        $out = $t->run();
        // clean the Git worktree list
        $this->_exec("git worktree prune");
        return $out;
    }
}