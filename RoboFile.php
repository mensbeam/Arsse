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

    /**
     * Runs the full test suite
     *
     * Arguments passed to the task are passed on to PHPUnit. Thus one may, for
     * example, run the following command and get the expected results:
     *
     * ./robo test --testsuite TTRSS --exclude-group slow --testdox
     *
     * Please see the PHPUnit documentation for available options.
    */
    public function test(array $args): Result {
        // start the built-in PHP server, which is required for some of the tests
        $this->taskServer(8000)->host("localhost")->dir(self::BASE_TEST."docroot")->rawArg("-n")->arg(self::BASE_TEST."server.php")->background()->run();
        // run tests
        $execpath = realpath(self::BASE."vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit");
        $confpath = realpath(self::BASE_TEST."phpunit.xml");
        return $this->taskExec("php")->arg($execpath)->option("-c", $confpath)->args($args)->run();
    }

    /**
     * Runs the full test suite
     *
     * This is an alias of the "test" task.
    */
    public function testFull(array $args): Result {
        return $this->test($args);
    }

    /**
     * Runs a quick subset of the test suite
     *
     * See help for the "test" task for more details.
    */
    public function testQuick(array $args): Result {
        return $this->test(array_merge(["--exclude-group", "slow,optional"], $args));
    }

    /** Produces a code coverage report
     *
     * By default this task produces an HTML-format coverage report in
     * arsse/tests/coverage/. Additional reports may be produced by passing
     * arguments to this task as one would to PHPUnit.
     *
     * Robo first tries to use phpdbg and will fall back to Xdebug if available.
     * Because Xdebug slows down non-coverage tasks, however, phpdbg is highly
     * recommanded is debugging facilities are not otherwise needed.
    */
    public function coverage(array $args): Result {
        // start the built-in PHP server, which is required for some of the tests
        $this->taskServer(8000)->host("localhost")->dir(self::BASE_TEST."docroot")->rawArg("-n")->arg(self::BASE_TEST."server.php")->background()->run();
        // run tests with code coverage reporting enabled
        $exec = $this->findCoverageEngine();
        $execpath = realpath(self::BASE."vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit");
        $confpath = realpath(self::BASE_TEST."phpunit.xml");
        return $this->taskExec($exec)->arg($execpath)->option("-c", $confpath)->option("--coverage-html", self::BASE_TEST."coverage")->args($args)->run();
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

    /** Packages a given commit of the software into a release tarball
     *
     * The version to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "head".
     *
     * Note that while it is possible to re-package old versions, the resultant tarball
     * may not be equivalent due to subsequent changes in the exclude list, or because
     * of new tooling.
    */
    public function package(string $version = null): Result {
        // establish which commit to package
        $version = $version ?? $this->askDefault("Commit to package:", "head");
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

    public function clean($opts = ['demo|d' => false]): Result {
        $t = $this->taskExec(realpath(self::BASE."vendor/bin/php-cs-fixer"));
        $t->arg("fix");
        if ($opts['demo']) {
            $t->args("--dry-run", "--diff")->option("--diff-format", "udiff");
        }
        return $t->run();
    }
}
