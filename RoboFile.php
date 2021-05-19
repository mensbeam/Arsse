<?php

use Robo\Result;

const BASE = __DIR__.\DIRECTORY_SEPARATOR;
const BASE_TEST = BASE."tests".\DIRECTORY_SEPARATOR;
define("IS_WIN", defined("PHP_WINDOWS_VERSION_MAJOR"));
define("IS_MAC", php_uname("s") === "Darwin");
error_reporting(0);

function norm(string $path): string {
    $out = realpath($path);
    if (!$out) {
        $out = str_replace(["/", "\\"], \DIRECTORY_SEPARATOR, $path);
    }
    return $out;
}

class RoboFile extends \Robo\Tasks {
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
     * Robo first tries to use pcov and will fall back first to xdebug then
     * phpdbg. Neither pcov nor xdebug need to be enabled to be used; they
     * only need to be present in the extension load path to be used.
     */
    public function coverage(array $args): Result {
        // run tests with code coverage reporting enabled
        $exec = $this->findCoverageEngine();
        return $this->runTests($exec, "coverage", array_merge(["--coverage-html", BASE_TEST."coverage"], $args));
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
        return $this->runTests($exec, "typical", array_merge(["--coverage-html", BASE_TEST."coverage"], $args));
    }

    /** Runs the coding standards fixer */
    public function clean($opts = ['demo|d' => false]): Result {
        $t = $this->taskExec(norm(BASE."vendor/bin/php-cs-fixer"));
        $t->arg("fix");
        if ($opts['demo']) {
            $t->args("--dry-run", "--diff")->option("--diff-format", "udiff");
        }
        return $t->run();
    }

    protected function findCoverageEngine(): string {
        $dir = rtrim(ini_get("extension_dir"), "/").\DIRECTORY_SEPARATOR;
        $ext = IS_WIN ? "dll" : (IS_MAC ? "dylib" : "so");
        $php = escapeshellarg(\PHP_BINARY);
        $code = escapeshellarg(BASE."lib");
        if (extension_loaded("pcov")) {
            return "$php -d pcov.enabled=1 -d pcov.directory=$code";
        } elseif (extension_loaded("xdebug")) {
            return "$php -d xdebug.mode=coverage";
        } elseif (file_exists($dir."pcov.$ext")) {
            return "$php -d extension=pcov.$ext -d pcov.enabled=1 -d pcov.directory=$code";
        } elseif (file_exists($dir."xdebug.$ext")) {
            return "$php -d zend_extension=xdebug.$ext -d xdebug.mode=coverage";
        } else {
            if (IS_WIN) {
                $dbg = dirname(\PHP_BINARY)."\\phpdbg.exe";
                $dbg = file_exists($dbg) ? $dbg : "";
            } else {
                $dbg = trim(`which phpdbg 2>/dev/null`);
            }
            if ($dbg) {
                return escapeshellarg($dbg)." -qrr";
            } else {
                return $php;
            }
        }
    }

    protected function blackhole(bool $all = false): string {
        $hole = IS_WIN ? "nul" : "/dev/null";
        return $all ? ">$hole 2>&1" : "2>$hole";
    }

    protected function runTests(string $executor, string $set, array $args): Result {
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
        $execpath = norm(BASE."vendor-bin/phpunit/vendor/phpunit/phpunit/phpunit");
        $confpath = realpath(BASE_TEST."phpunit.dist.xml") ?: norm(BASE_TEST."phpunit.xml");
        $this->taskServer(8000)->host("localhost")->dir(BASE_TEST."docroot")->rawArg("-n")->arg(BASE_TEST."server.php")->rawArg($this->blackhole())->background()->run();
        return $this->taskExec($executor)->option("-d", "zend.assertions=1")->arg($execpath)->option("-c", $confpath)->args(array_merge($set, $args))->run();
    }

    /** Packages a given commit of the software into a release tarball
     *
     * The version to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     *
     * Note that while it is possible to re-package old versions, the resultant tarball
     * may not be equivalent due to subsequent changes in the exclude list, or because
     * of new tooling.
     */
    public function package(string $version = null): Result {
        // establish which commit to package
        $version = $version ?? $this->askDefault("Commit to package:", "HEAD");
        $archive = BASE."arsse-$version.tar.gz";
        // start a collection
        $t = $this->collectionBuilder();
        // create a temporary directory
        $dir = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // create a Git worktree for the selected commit in the temp location
        $t->taskExec("git worktree add ".escapeshellarg($dir)." ".escapeshellarg($version))
            ->completion($this->taskFilesystemStack()->remove($dir))
            ->completion($this->taskExec("git worktree prune"));
        // patch the Arch PKGBUILD file with the correct version string
        $t->addCode(function () use ($dir) {
            $ver = trim(preg_replace('/^([^-]+)-(\d+)-(\w+)$/', "$1.r$2.$3", `git -C "$dir" describe --tags`));
            return $this->taskReplaceInFile($dir."dist/arch/PKGBUILD")->regex('/^pkgver=.*$/m')->to("pkgver=$ver")->run();
        });
        // patch the Arch PKGBUILD file with the correct source file
        $t->addCode(function () use ($dir, $archive) {
            $tar = basename($archive);
            return $this->taskReplaceInFile($dir."dist/arch/PKGBUILD")->regex('/^source=\("arsse-[^"]+"\)$/m')->to("source=(\"$tar\")")->run();
        });
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
        // remove any existing archive
        $t->taskFilesystemStack()->remove($archive);
        // package it all up
        $t->taskPack($archive)->addDir("arsse", $dir);
        // execute the collection
        return $t->run();
    }

    /** Packages a given commit of the software into an Arch package
     *
     * The version to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     */
    public function packageArch(string $version = null): Result {
        // establish which commit to package
        $version = $version ?? $this->askDefault("Commit to package:", "HEAD");
        $archive = BASE."arsse-$version.tar.gz";
        // start a collection
        $t = $this->collectionBuilder();
        // create a tarball
        $t->addCode(function() use ($version) {
            return $this->package($version);
        });
        // extract the PKGBUILD from the just-created archive and build it
        $t->addCode(function() use ($archive) {
            // because Robo doesn't support extracting a single file we have to do it ourselves
            (new \Archive_Tar($archive))->extractList("arsse/dist/arch/PKGBUILD", BASE, "arsse/dist/arch/", false);
            // perform a do-nothing filesystem operation since we need a Robo task result
            return $this->taskFilesystemStack()->chmod(BASE."PKGBUILD", 0644)->run();
        })->completion($this->taskFilesystemStack()->remove(BASE."PKGBUILD"));
        $t->taskExec("makepkg -Ccf")->dir(BASE);
        return $t->run();
    }

    /** Generates static manual pages in the "manual" directory
     *
     * The resultant files are suitable for offline viewing and inclusion into release builds
     */
    public function manual(array $args): Result {
        $execpath = escapeshellarg(norm(BASE."vendor/bin/daux"));
        $t = $this->collectionBuilder();
        $t->taskExec($execpath)->arg("generate")->option("-d", BASE."manual")->args($args);
        $t->taskDeleteDir(BASE."manual/daux_libraries");
        $t->taskDeleteDir(BASE."manual/theme");
        $t->taskDeleteDir(BASE."manual/themes/src");
        return $t->run();
    }

    /** Serves a live view of the manual using the built-in Web server */
    public function manualLive(array $args): Result {
        $execpath = escapeshellarg(norm(BASE."vendor/bin/daux"));
        return $this->taskExec($execpath)->arg("serve")->args($args)->run();
    }

    /** Rebuilds the entire manual theme
     *
     * This requires Node and Yarn to be installed, and only needs to be done when
     * Daux's theme changes
     */
    public function manualTheme(array $args): Result {
        $postcss = escapeshellarg(norm(BASE."node_modules/.bin/postcss"));
        $themesrc = norm(BASE."docs/theme/src/").\DIRECTORY_SEPARATOR;
        $themeout = norm(BASE."docs/theme/arsse/").\DIRECTORY_SEPARATOR;
        $dauxjs = norm(BASE."vendor-bin/daux/vendor/daux/daux.io/themes/daux/js/").\DIRECTORY_SEPARATOR;
        // start a collection; this stops after the first failure
        $t = $this->collectionBuilder();
        // install dependencies via Yarn
        $t->taskExec("yarn install");
        // compile the stylesheet
        $t->taskExec($postcss)->arg($themesrc."arsse.scss")->option("-o", $themeout."arsse.css");
        // copy JavaScript files from the Daux theme
        foreach (glob($dauxjs."daux*.js") as $file) {
            $t->taskFilesystemStack()->copy($file, $themeout.basename($file), true);
        }
        // execute the collection
        return $t->run();
    }
}
