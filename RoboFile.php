<?php

use Robo\Result;

class RoboFile extends \Robo\Tasks {
    const BASE = __DIR__.\DIRECTORY_SEPARATOR;
    const BASE_TEST = self::BASE."tests".\DIRECTORY_SEPARATOR;

    /** Runs the typical test suite
     *
     * Arguments passed to the task are passed on to PHPUnit. Thus one may, for
     * example, run the following command and get the expected results:
     *
     * ./robo test --testsuite TTRSS --exclude-group slow --testdox
     *
     * Please see the PHPUnit documentation for available options.
    */
    public function test(array $args): Result {
        return $this->runTests(escapeshellarg(\PHP_BINARY), "typical", $args);
    }

    /** Runs the full test suite
     *
     * This includes pedantic tests which may help to identify problems.
     * See help for the "test" task for more details.
    */
    public function testFull(array $args): Result {
        return $this->runTests(escapeshellarg(\PHP_BINARY), "full", $args);
    }

    /**
     * Runs a quick subset of the test suite
     *
     * See help for the "test" task for more details.
    */
    public function testQuick(array $args): Result {
        return $this->runTests(escapeshellarg(\PHP_BINARY), "quick", $args);
    }

    /** Produces a code coverage report
     *
     * By default this task produces an HTML-format coverage report in
     * tests/coverage/. Additional reports may be produced by passing
     * arguments to this task as one would to PHPUnit.
     *
     * Robo first tries to use phpdbg and will fall back to Xdebug if available.
     * Because Xdebug slows down non-coverage tasks, however, phpdbg is highly
     * recommended if debugging facilities are not otherwise needed.
    */
    public function coverage(array $args): Result {
        // run tests with code coverage reporting enabled
        $exec = $this->findCoverageEngine();
        return $this->runTests($exec, "coverage", array_merge(["--coverage-html", self::BASE_TEST."coverage"], $args));
    }

    /** Produces a code coverage report, with redundant tests
     *
     * Depending on the environment, some tests that normally provide
     * coverage may be skipped, while working alternatives are normally
     * suppressed for reasons of time. This coverage report will try to
     * run all tests which may cover code.
     *
     * See also help for the "coverage" task for more details.
    */
    public function coverageFull(array $args): Result {
        // run tests with code coverage reporting enabled
        $exec = $this->findCoverageEngine();
        return $this->runTests($exec, "typical", array_merge(["--coverage-html", self::BASE_TEST."coverage"], $args));
    }

    /** Runs the coding standards fixer */
    public function clean($opts = ['demo|d' => false]): Result {
        $t = $this->taskExec(realpath(self::BASE."vendor/bin/php-cs-fixer"));
        $t->arg("fix");
        if ($opts['demo']) {
            $t->args("--dry-run", "--diff")->option("--diff-format", "udiff");
        }
        return $t->run();
    }

    protected function findCoverageEngine(): string {
        if ($this->isWindows()) {
            $dbg = dirname(\PHP_BINARY)."\\phpdbg.exe";
            $dbg = file_exists($dbg) ? $dbg : "";
        } else {
            $dbg = trim(`which phpdbg`);
        }
        if ($dbg) {
            return escapeshellarg($dbg)." -qrr";
        } else {
            return escapeshellarg(\PHP_BINARY);
        }
    }

    protected function isWindows(): bool {
        return defined("PHP_WINDOWS_VERSION_MAJOR");
    }

    protected function blackhole(bool $all = false): string {
        $hole = $this->isWindows() ? "nul" : "/dev/null";
        return $all ? ">$hole 2>&1" : "2>$hole";
    }

    protected function runTests(string $executor, string $set, array $args) : Result {
        switch ($set) {
            case "typical":
                $set = ["--exclude-group", "optional"];
                break;
            case "quick":
                $set = ["--exclude-group", "optional,slow"];
                break;
            case "coverage":
                $set = ["--exclude-group", "optional,coverageOptional"];
                break;
            case "full":
                $set = [];
                break;
            default:
                throw new \Exception;
        }
        $execpath = realpath(self::BASE."vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit");
        $confpath = realpath(self::BASE_TEST."phpunit.dist.xml") ?: realpath(self::BASE_TEST."phpunit.xml");
        $this->taskServer(8000)->host("localhost")->dir(self::BASE_TEST."docroot")->rawArg("-n")->arg(self::BASE_TEST."server.php")->rawArg($this->blackhole())->background()->run();
        return $this->taskExec($executor)->arg($execpath)->option("-c", $confpath)->args(array_merge($set, $args))->run();
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
        $version = $version ?? $this->askDefault("Commit to package:", "HEAD");
        $archive = self::BASE."arsse-$version.tar.gz";
        // start a collection
        $t = $this->collectionBuilder();
        // create a temporary directory
        $dir = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // create a Git worktree for the selected commit in the temp location
        $t->taskExec("git worktree add ".escapeshellarg($dir)." ".escapeshellarg($version));
        // perform Composer installation in the temp location with dev dependencies
        $t->taskComposerInstall()->dir($dir);
        // generate the manual
        $t->taskExec(escapeshellarg($dir."robo")." manual")->dir($dir);
        // perform Composer installation in the temp location for final output
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
            $dir."docs",
            $dir."tests",
            $dir."vendor-bin",
            $dir."vendor/bin",
            $dir."robo",
            $dir."robo.bat",
            $dir."package.json",
            $dir."yarn.lock",
            $dir."postcss.config.js",
        ]);
        // generate a sample configuration file
        $t->taskExec(escapeshellarg(\PHP_BINARY)." arsse.php conf save-defaults config.defaults.php")->dir($dir);
        // package it all up
        $t->taskPack($archive)->addDir("arsse", $dir);
        // execute the collection
        $out = $t->run();
        // clean the Git worktree list
        $this->_exec("git worktree prune");
        return $out;
    }

    /** Generates static manual pages in the "manual" directory
     * 
     * The resultant files are suitable for offline viewing and inclusion into release builds
     */
    public function manual(array $args): Result {
        $execpath = escapeshellarg(realpath(self::BASE."vendor/bin/daux"));
        $t = $this->collectionBuilder();
        $t->taskExec($execpath)->arg("generate")->option("-d", self::BASE."manual")->args($args);
        $t->taskDeleteDir(self::BASE."manual/theme");
        $t->taskDeleteDir(self::BASE."manual/themes/src");
        return $t->run();
    }

    /** Serves a live view of the manual using the built-in Web server */
    public function manualLive(array $args): Result {
        $execpath = escapeshellarg(realpath(self::BASE."vendor/bin/daux"));
        return $this->taskExec($execpath)->arg("serve")->args($args)->run();
    }

    /** Rebuilds the entire manual theme
     * 
     * This requires Node and Yarn to be installed, and only needs to be done when
     * Daux's theme changes
     */
    public function manualTheme(array $args): Result {
        $languages = ["php", "bash", "shell", "xml", "nginx", "apache"];
        $themeout = realpath(self::BASE."docs/theme/arsse/").\DIRECTORY_SEPARATOR;
        $dauxjs = realpath(self::BASE."vendor-bin/daux/vendor/daux/daux.io/themes/daux/js/").\DIRECTORY_SEPARATOR;
        // start a collection; this stops after the first failure
        $t = $this->collectionBuilder();
        $tmp = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // rebuild the stylesheet
        $t->addCode([$this, "manualCss"]);
        // copy JavaScript files from the Daux theme
        foreach(glob($dauxjs."daux*") as $file) {
            $t->taskFilesystemStack()->copy($file, $themeout.basename($file), true);
        }
        // download highlight.js
        $t->addCode(function() use ($languages, $tmp, $themeout) {
            // compile the list of desired language (enumerated above) into an application/x-www-form-urlencoded body
            $post = http_build_query((function($langs) {
                $out = [];
                foreach($langs as $l) {
                    $out[$l.".js"] = "on";
                }
                return $out;
            })($languages));
            // get the two cross-site request forgery tokens the Highlight.js Web site requires
            $conn = @fopen("https://highlightjs.org/download/", "r");
            if ($conn === false) {
                throw new Exception("Unable to download Highlight.js");
            }
            foreach (stream_get_meta_data($conn)['wrapper_data'] as $field) {
                if (preg_match("/^Set-Cookie: csrftoken=([^;]+)/i", $field, $cookie)) {
                    break;
                }
            }
            $token = stream_get_contents($conn);
            preg_match("/<input type='hidden' name='csrfmiddlewaretoken' value='([^']*)'/", $token, $token);
            // add the form CSRF token to the POST body
            $post = "csrfmiddlewaretoken={$token[1]}&$post";
            // download a copy of Highlight.js with the desired languages to a temporary file
            $hljs = @file_get_contents("https://highlightjs.org/download/", false, stream_context_create(['http' => [
                'method' => "POST",
                'content' => $post,
                'header' => [
                    "Referer: https://highlightjs.org/download/",
                    "Cookie: csrftoken={$cookie[1]}",
                    "Content-Type: application/x-www-form-urlencoded",
                ],
            ]]));
            if ($hljs === false) {
                throw new Exception("Unable to download Highlight.js");
            } else {
                file_put_contents($tmp."highlightjs.zip", $hljs);
            }
            // extract the downloaded zip file and keep only the JS file
            $this->taskExtract($tmp."highlightjs.zip")->to($tmp."hljs")->run();
            $this->taskFilesystemStack()->copy($tmp."hljs".\DIRECTORY_SEPARATOR."highlight.pack.js", $themeout."highlight.pack.js")->run();
        }, "downloadHighlightjs");
        // execute the collection
        return $t->run();
    }

    /** Rebuilds the manual theme's stylesheet only
     * 
     * This requires Node and Yarn to be installed.
     */
    public function manualCss(): Result {
        // start a collection; this stops after the first failure
        $t = $this->collectionBuilder();
        $tmp = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // install dependencies via Yarn
        $t->taskExec("yarn install");
        // compile the stylesheet
        $postcss = escapeshellarg(realpath(self::BASE."node_modules/.bin/postcss"));
        $themesrc = realpath(self::BASE."docs/theme/src/").\DIRECTORY_SEPARATOR;
        $themeout = realpath(self::BASE."docs/theme/arsse/").\DIRECTORY_SEPARATOR;
        $t->taskExec($postcss)->arg($themesrc."arsse.scss")->option("-o", $themeout."arsse.css");
        // execute the collection
        return $t->run();
    }
}
