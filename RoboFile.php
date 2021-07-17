<?php

use Robo\Collection\CollectionBuilder;
use Robo\Result;

const BASE = __DIR__.\DIRECTORY_SEPARATOR;
const BASE_TEST = BASE."tests".\DIRECTORY_SEPARATOR;
define("IS_WIN", defined("PHP_WINDOWS_VERSION_MAJOR"));
define("IS_MAC", php_uname("s") === "Darwin");
define("IS_LINUX", !IS_WIN && !IS_MAC);
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

    protected function commitVersion(?string $commit): array {
        $target = $commit ?? $this->askDefault("Reference commit:", "HEAD");
        $base = escapeshellarg(BASE);
        $blackhole = $this->blackhole();
        // get useable version strings from Git
        $version = trim(`git -C $base describe --tags $target $blackhole`);
        if (!$version) {
            throw new \Exception("Commit reference invalid");
        }
        return [$target, $version];
    }

    protected function toolExists(string ...$binary): bool {
        $blackhole = $this->blackhole(IS_WIN);
        foreach ($binary as $bin) {
            if (
                (IS_WIN && (!exec(escapeshellarg($bin)." --help $blackhole", $junk, $status) || $status))
                || (!IS_WIN && (!exec("which ".escapeshellarg($bin)." $blackhole", $junk, $status) || $status))
             ) {
                    return false;
            }
        }
        return true;
    }

    /** Packages a given commit of the software into a release tarball
     *
     * The commit to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     *
     * Note that while it is possible to re-package old versions, the resultant tarball
     * may not be equivalent due to subsequent changes in the exclude list, or because
     * of new tooling.
     */
    public function packageGeneric(string $commit = null): Result {
        if (!$this->toolExists("git", "pandoc")) {
            throw new \Exception("Git and Pandoc are required in PATH to produce generic release tarballs");
        }
        // establish which commit to package
        [$commit, $version] = $this->commitVersion($commit);
        $archVersion = preg_replace('/^([^-]+)-(\d+)-(\w+)$/', "$1.r$2.$3", $version);
        // name the generic release tarball
        $tarball = BASE."release/$version/arsse-$version.tar.gz";
        // start a collection
        $t = $this->collectionBuilder();
        // create a temporary directory
        $dir = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // create a Git worktree for the selected commit in the temp location
        $result = $this->taskExec("git worktree add ".escapeshellarg($dir)." ".escapeshellarg($version))->dir(BASE)->run();
        if ($result->getExitCode() > 0) {
            return $result;
        }
        try {
            // generate the Debian changelog; this also validates our original changelog
            $debianChangelog = $this->changelogDebian($this->changelogParse(file_get_contents($dir."CHANGELOG"), $version), $version);
            // save commit description to VERSION file for reference
            $t->addTask($this->taskWriteToFile($dir."VERSION")->text($version));
            // patch the Arch PKGBUILD file with the correct version string
            $t->addTask($this->taskReplaceInFile($dir."dist/arch/PKGBUILD")->regex('/^pkgver=.*$/m')->to("pkgver=$archVersion"));
            // patch the Arch PKGBUILD file with the correct source file
            $t->addTask($this->taskReplaceInFile($dir."dist/arch/PKGBUILD")->regex('/^source=\("arsse-[^"]+"\)$/m')->to('source=("'.basename($tarball).'")'));
            // save the Debian-format changelog
            $t->addTask($this->taskWriteToFile($dir."dist/debian/changelog")->text($debianChangelog));
            // perform Composer installation in the temp location with dev dependencies
            $t->addTask($this->taskComposerInstall()->arg("-q")->dir($dir));
            // generate manpages
            $t->addTask($this->taskExec("./robo manpage")->dir($dir));
            // generate the HTML manual
            $t->addTask($this->taskExec("./robo manual -q")->dir($dir));
            // perform Composer installation in the temp location for final output
            $t->addTask($this->taskComposerInstall()->dir($dir)->noDev()->optimizeAutoloader()->arg("--no-scripts")->arg("-q"));
            // delete unwanted files
            $t->addTask($this->taskFilesystemStack()->remove([
                $dir.".git",
                $dir.".gitignore",
                $dir.".gitattributes",
                $dir."dist/debian/.gitignore",
                $dir."composer.json",
                $dir."composer.lock",
                $dir.".php_cs.dist",
                $dir."phpdoc.dist.xml",
                $dir."build.xml",
                $dir."RoboFile.php",
                $dir."CONTRIBUTING.md",
                $dir."docs",
                $dir."manpages",
                $dir."tests",
                $dir."vendor-bin",
                $dir."vendor/bin",
                $dir."robo",
                $dir."robo.bat",
                $dir."package.json",
                $dir."yarn.lock",
                $dir."postcss.config.js",
            ]));
            $t->addCode(function() use ($dir) {
                // Remove files which lintian complains about; they're otherwise harmless
                $files = [];
                foreach (new \CallbackFilterIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir."vendor", \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS)), function($v, $k, $i) {
                    return preg_match('/\/\.git(?:ignore|attributes|modules)$/D', $v);
                }) as $f) {
                    $files[] = $f;
                }
                return $this->taskFilesystemStack()->remove($files)->run();
            });
            // generate a sample configuration file
            $t->addTask($this->taskExec(escapeshellarg(\PHP_BINARY)." arsse.php conf save-defaults config.defaults.php")->dir($dir));
            // remove any existing archive
            $t->addTask($this->taskFilesystemStack()->remove($tarball));
            // package it all up
            $t->addTask($this->taskFilesystemStack()->mkdir(dirname($tarball)));
            $t->addTask($this->taskPack($tarball)->addDir("arsse", $dir));
            // execute the collection
            $result = $t->run();
        } finally {
            // remove the Git worktree
            $this->taskFilesystemStack()->remove($dir)->run();
            $this->taskExec("git worktree prune")->dir(BASE)->run();
        }
        return $result;
    }

    /** Packages a given commit of the software into an Arch package
     *
     * The commit to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     * 
     * The Arch base-devel group should be installed for this.
     */
    public function packageArch(string $commit = null): Result {
        if (!$this->toolExists("git", "makepkg", "updpkgsums")) {
            throw new \Exception("Git, makepkg, and updpkgsums are required in PATH to produce Arch packages");
        }
        // establish which commit to package
        [$commit, $version] = $this->commitVersion($commit);
        $tarball = BASE."release/$version/arsse-$version.tar.gz";
        $dir = dirname($tarball).\DIRECTORY_SEPARATOR;
        // start a collection
        $t = $this->collectionBuilder();
        // build the generic release tarball if it doesn't exist
        if (!file_exists($tarball)) {
            $t->addTask($this->taskExec(BASE."robo package:generic $commit"));
        }
        // extract the PKGBUILD from the tarball
        $t->addCode(function() use ($tarball, $dir) {
            // because Robo doesn't support extracting a single file we have to do it ourselves
            (new \Archive_Tar($tarball))->extractList("arsse/dist/arch/PKGBUILD", $dir, "arsse/dist/arch/", false);
            // perform a do-nothing filesystem operation since we need a Robo task result
            return $this->taskFilesystemStack()->chmod($dir."PKGBUILD", 0644)->run();
        })->completion($this->taskFilesystemStack()->remove($dir."PKGBUILD"));
        // build the package
        $t->addTask($this->taskExec("makepkg -Ccf")->dir($dir));
        return $t->run();
    }

    /** Packages a given commit of the software a binary Debian package
     *
     * The commit to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     * 
     * The pbuilder tool should be installed for this.
     */
    public function packageDeb(string $commit = null): Result {
        if (!$this->toolExists("git", "sudo", "pbuilder")) {
            throw new \Exception("Git, sudo, and pbuilder are required in PATH to produce Debian packages");
        }
        // establish which commit to package
        [$commit, $version] = $this->commitVersion($commit);
        $tarball = BASE."release/$version/arsse-$version.tar.gz";
        // define some more variables
        $tgz = BASE."release/pbuilder-arsse.tgz";
        $bind = dirname($tarball);
        $script = BASE."dist/debian/pbuilder.sh";
        $user = trim(`id -un`);
        $group = trim(`id -gn`);
        // start a task collection
        $t = $this->collectionBuilder();
        // check that the pbuilder base exists and create it if it does not
        if (!file_exists($tgz)) {
            $t->addTask($this->taskFilesystemStack()->mkdir(BASE."release"));
            $t->addTask($this->taskExec('sudo pbuilder create --basetgz '.escapeshellarg($tgz).' --mirror http://ftp.ca.debian.org/debian/ --extrapackages "debhelper devscripts lintian"'));
        }
        // build the generic release tarball if it doesn't exist
        if (!file_exists($tarball)) {
            $t->addTask($this->taskExec(BASE."robo package:generic $commit"));
        }
        // build the packages
        $t->addTask($this->taskExec('sudo pbuilder execute --basetgz '.escapeshellarg($tgz).' --bindmounts '.escapeshellarg($bind).' -- '.escapeshellarg($script).' '.escapeshellarg("$bind/".basename($tarball))));
        // take ownership of the output files
        $t->addTask($this->taskExec("sudo chown -R $user:$group ".escapeshellarg($bind)));
        return $t->run();
    }

    /** Packages a release tarball into a Debian source package
     *
     * The commit to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     */
    public function packageDebsrc(string $commit = null): Result {
        // establish which commit to package
        [$commit, $version] = $this->commitVersion($commit);
        $tarball = BASE."release/$version/arsse-$version.tar.gz";
        // determine the base version (i.e. x.y.z) and the Debian version (i.e. x.y.z-a)
        preg_match('/^(\d+(?:\.\d+)+)(?:-(\d+))?/', $version, $m);
        $baseVersion = $m[1];
        $debVersion = $m[1]."-".($version === $baseVersion ? "1" : $m[2]);
        // start a task collection and create a temporary directory
        $t = $this->collectionBuilder();
        $dir = $t->tmpDir().\DIRECTORY_SEPARATOR;
        // build the generic release tarball if it doesn't exist
        if (!file_exists($tarball)) {
            $t->addTask($this->taskExec(BASE."robo package:generic $commit"));
        }
        $base = $dir."arsse-$version".\DIRECTORY_SEPARATOR;
        // start by extracting the tarball
        $t->addCode(function() use ($tarball, $dir, $base) {
            // Robo's extract task is broken, so we do it manually
            (new \Archive_Tar($tarball))->extract($dir, false);
            return $this->taskFilesystemStack()->rename($dir."arsse", $base)->run();
        });
        // re-pack the tarball using a specific name special to Debian
        $t->addTask($this->taskPack($dir."arsse_$baseVersion.orig.tar.gz")->addDir("arsse-$baseVersion", $base));
        // pack the debian tarball
        $t->addTask($this->taskPack($dir."arsse_$debVersion.debian.tar.gz")->addDir("debian", $base."dist"));
        // generate the DSC file
        $t->addCode(function() use ($t, $debVersion, $baseVersion, $dir, $base) {
            try {
                $dsc = $this->generateDebianSourceControl($base."dist/debian/", $debVersion, [$dir."arsse_$baseVersion.orig.tar.gz", $dir."arsse_$debVersion.debian.tar.gz"]);
            } catch (\Exception $e) {
                return new Result($t, 1, $e->getMessage());
            }
            // write the DSC file
            return $this->taskWriteToFile($dir."arsse_$debVersion.dsc")->text($dsc)->run();
        });
        // delete any existing files
        $t->AddTask($this->taskFilesystemStack()->remove(BASE."release/$version/arsse_$baseVersion.orig.tar.gz")->remove(BASE."release/$version/arsse_$debVersion.debian.tar.gz")->remove(BASE."release/$version/arsse_$debVersion.dsc"));
        $t->addTask($this->taskFilesystemStack()->copy($dir."arsse_$baseVersion.orig.tar.gz", BASE."release/$version/arsse_$baseVersion.orig.tar.gz")->copy($dir."arsse_$debVersion.debian.tar.gz", BASE."release/$version/arsse_$debVersion.debian.tar.gz")->copy($dir."arsse_$debVersion.dsc", BASE."release/$version/arsse_$debVersion.dsc"));
        return $t->run();
    }

    /** Generates all possible package types for a given commit of the software
     *
     * The commit to package may be any Git tree-ish identifier: a tag, a branch,
     * or any commit hash. If none is provided on the command line, Robo will prompt
     * for a commit to package; the default is "HEAD".
     * 
     * Generic release tarballs will always be generated, but distribution-specific
     * packages are skipped when the required tools are not available
     */
    public function package(string $commit = null): Result {
        if (!$this->toolExists("git")) {
            throw new \Exception("Git is required in PATH to produce packages");
        }
        [$commit,] = $this->commitVersion($commit);
        // determine whether the distribution-specific packages can be built
        $dist = [
            'Arch'   => $this->toolExists("git", "makepkg", "updpkgsums"),
            'Deb' => $this->toolExists("git", "sudo", "pbuilder"),
        ];
        // start a collection
        $t = $this->collectionBuilder();
        // build the generic release tarball
        $t->addTask($this->taskExec(BASE."robo package:generic $commit"));
        // build other packages
        foreach ($dist as $distro => $run) {
            if ($run) {
                $subcmd = strtolower($distro);
                $t->addTask($this->taskExec(BASE."robo package:$subcmd $commit"));
            }
        }
        $out = $t->run();
        // note any packages which were not built
        foreach ($dist as $distro => $run) {
            if (!$run) {
                $this->say("Packages for $distro skipped");
            }
        }
        return $out;
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
        if (!$this->toolExists("yarn")) {
            throw new \Exception("Yarn is required in PATH to update the Daux theme");
        }
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

    /** Generates the "arsse" command's manual page (UNIX man page)
     *
     * This requires that the Pandoc document converter be installed and
     * available in $PATH.
     */
    public function manpage(): Result {
        if (!$this->toolExists("pandoc")) {
            throw new \Exception("Pandoc is required in PATH to generate manual pages");
        }
        $t = $this->collectionBuilder();
        $man = [
            'en' => "man1/arsse.1",
        ];
        foreach ($man as $src => $out) {
            $src = BASE."manpages/$src.md";
            $out = BASE."dist/man/$out";
            $t->addTask($this->taskFilesystemStack()->mkdir(dirname($out), 0755));
            $t->addTask($this->taskExec("pandoc -s -f markdown-smart -t man -o ".escapeshellarg($out)." ".escapeshellarg($src)));
            $t->addTask($this->taskReplaceInFile($out)->regex('/\.\n(?!\.)/s')->to(". "));
        }
        return $t->run();
    }

    protected function changelogParse(string $text, string $targetVersion): array {
        $lines = preg_split('/\r?\n/', $text);
        $version = "";
        $section = "";
        $out = [];
        $entry = [];
        $expected = ["version"];
        for ($a = 0; $a < sizeof($lines);) {
            $l = rtrim($lines[$a++]);
            if (in_array("version", $expected) && preg_match('/^Version (\d+(?:\.\d+)*) \(([\d\?]{4}-[\d\?]{2}-[\d\?]{2})\)\s*$/D', $l, $m)) {
                $version = $m[1];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $m[2])) {
                    // uncertain dates are allowed only for the top version, and only if it does not match the target version (otherwise we have forgotten to set the correct date before tagging)
                    if (!$out && $targetVersion !== $version) {
                        // use today's date; local time is fine
                        $date = date("Y-m-d");
                    } else {
                        throw new \Exception("CHANGELOG: Date at line $a is incomplete");
                    }
                } else {
                    $date = $m[2];
                }
                if ($entry) {
                    $out[] = $entry;
                }
                $entry = ['version' => $version, 'date' => $date, 'features' => [], 'fixes' => [], 'changes' => []];
                $expected = ["separator"];
            } elseif (in_array("separator", $expected) && preg_match('/^=+/', $l)) {
                $length = strlen($lines[$a - 2]);
                if (strlen($l) !== $length) {
                    throw new \Exception("CHANGELOG: Separator at line $a is of incorrect length");
                }
                $expected = ["blank line"];
                $section = "";
            } elseif (in_array("blank line", $expected) && $l === "") {
                $expected = [
                    ''         => ["features section", "fixes section", "changes section"],
                    'features' => ["fixes section", "changes section", "version"],
                    'fixes'    => ["changes section", "version"],
                    'changes'  => ["version"],
                ][$section];
                $expected[] = "end-of-file";
            } elseif (in_array("features section", $expected) && $l === "New features:") {
                $section = "features";
                $expected = ["item"];
            } elseif (in_array("fixes section", $expected) && $l === "Bug fixes:") {
                $section = "fixes";
                $expected = ["item"];
            } elseif (in_array("changes section", $expected) && $l === "Changes:") {
                $section = "changes";
                $expected = ["item"];
            } elseif (in_array("item", $expected) && preg_match('/^- (\w.*)$/D', $l, $m)) {
                $entry[$section][] = $m[1];
                $expected = ["item", "continuation", "blank line"];
            } elseif (in_array("continuation", $expected) && preg_match('/^  (\w.*)$/D', $l, $m)) {
                $last = sizeof($entry[$section]) - 1;
                $entry[$section][$last] .= "\n".$m[1];
            } else {
                if (sizeof($expected) > 1) {
                    throw new \Exception("CHANGELOG: Expected one of [".implode(", ", $expected)."] at line $a");
                } else {
                    throw new \Exception("CHANGELOG: Expected ".$expected[0]." at line $a");
                }
            }
        }
        if (!in_array("end-of-file", $expected)) {
            if (sizeof($expected) > 1) {
                throw new \Exception("CHANGELOG: Expected one of [".implode(", ", $expected)."] at end of file");
            } else {
                throw new \Exception("CHANGELOG: Expected ".$expected[0]." at end of file");
            }
        }
        $out[] = $entry;
        return $out;
    }

    protected function changelogDebian(array $log, string $targetVersion): string {
        $latest = $log[0]['version'];
        $baseVersion = preg_replace('/^(\d+(?:\.\d+)*).*/', "$1", $targetVersion);
        if ($baseVersion !== $targetVersion && version_compare($latest, $baseVersion, ">")) {
            // if the changelog contains an entry for a future version, change its version number to match the target version instead of using the future version
            $log[0]['version'] = $targetVersion;
            $log[0]['distribution'] = "UNRELEASED";
        } elseif ($baseVersion !== $targetVersion) {
            // otherwise synthesize a changelog entry for the changes since the last tag
            array_unshift($log, ['version' => $targetVersion, 'date' => date("Y-m-d"), 'features' => [], 'fixes' => [], 'changes' => ["Unspecified changes"], 'distribution' => "UNRELEASED"]);
        }
        $out = "";
        foreach ($log as $entry) {
            // normalize the version string
            preg_match('/^(\d+(?:\.\d+)*)(?:-(\d+)-.+)?$/D', $entry['version'], $m);
            $version = $m[1]."-".($m[2] ?: "1");
            // output the entry
            $out .= "arsse ($version) ".($entry['distribution'] ?? "unstable")."; urgency=low\n";
            if ($entry['features']) {
                $out .= "\n";
                foreach ($entry['features'] as $item) {
                    $out .= "  * ".trim(preg_replace("/^/m", "    ", $item))."\n";
                }
            }
            if ($entry['fixes']) {
                $out .= "\n";
                foreach ($entry['fixes'] as $item) {
                    $out .= "  * ".trim(preg_replace("/^/m", "    ", $item))."\n";
                }
            }
            if ($entry['changes']) {
                $out .= "\n";
                foreach ($entry['changes'] as $item) {
                    $out .= "  * ".trim(preg_replace("/^/m", "    ", $item))."\n";
                }
            }
            $out .= "\n -- J. King <jking@jkingweb.ca>  ".\DateTimeImmutable::createFromFormat("Y-m-d", $entry['date'], new \DateTimeZone("UTC"))->format("D, d M Y")." 00:00:00 +0000\n\n";
        }
        return $out;
    }

    protected function generateDebianSourceControl(string $dir, string $version, array $tarballs): string {
        // read in control file
        if (!$control = @file_get_contents($dir."control")) {
            throw new \Exception("Unable to read Debian control file");
        }
        // read the format
        if (!$format = @file_get_contents($dir."source/format")) {
            throw new \Exception("Unable to read source format in Debian files");
        }
        // read the binary packages from the control file
        if (preg_match_all('/^Package:\s*(\S+)/m', $control, $m)) {
            $binary = [];
            foreach ($m[1] as $pkg) {
                $binary[] = $pkg;
            }
        } else {
            throw new \Exception("No packages defined in Debian control file");
        }
        // read the package architectures from the control file
        if (preg_match_all('/^Architecture:\s*(\S+)/m', $control, $m) || sizeof($m[1]) != sizeof($binary)) {
            $architecture = [];
            foreach ($m[1] as $pkg) {
                $architecture[] = preg_replace('/\s/', "", $pkg);
            }
        } else {
            throw new \Exception("Number of architectures defined in Debian control file does not match number of packages");
        }
        // read the package sections from the control file
        if (preg_match_all('/^Section:\s*(\S+)/m', $control, $m) || sizeof($m[1]) != sizeof($binary)) {
            $section = [];
            foreach ($m[1] as $pkg) {
                $section[] = $pkg;
            }
        } else {
            throw new \Exception("Number of sections defined in Debian control file does not match number of packages");
        }
        // read the package priorities from the control file
        if (preg_match_all('/^Priority:\s*(\S+)/m', $control, $m) || sizeof($m[1]) != sizeof($binary)) {
            $priority = [];
            foreach ($m[1] as $pkg) {
                $priority[] = $pkg;
            }
        } else {
            throw new \Exception("Number of priorities defined in Debian control file does not match number of packages");
        }
        // read the package priorities from the control file
        if (preg_match_all('/^Priority:\s*(\S+)/m', $control, $m) || sizeof($m[1]) != sizeof($binary)) {
            $priority = [];
            foreach ($m[1] as $pkg) {
                $priority[] = $pkg;
            }
        } else {
            throw new \Exception("Number of priorities defined in Debian control file does not match number of packages");
        }
        // read simple metadata from the control file
        $metadata = [];
        foreach (["Source", "Maintainer", "Homepage", "Standards-Version", "Vcs-Browser", "Vcs-Git"] as $meta) {
            if (preg_match('/^'.$meta.':\s*(.+)/m', $control, $m)) {
                $metadata[$meta] = $m[1];
            } else {
                throw new \Exception("$meta is not defined in Debian control file");
            }
        }
        // read build dependencies from control file
        if (preg_match('/(?:^|\n)Build-Depends:\s*((?:[^\n]|\n(?= ))+)/s', $control, $m)) {
            $this->say(var_export($m));
            $buildDepends = preg_replace('/\s/', "", $m[1]);
        } else {
            $buildDepends = "";
        }
        // trim format
        $format = trim($format);
        // consolidate binaries and package list
        $packageList = [];
        for ($a = 0; $a < sizeof($binary); $a++) {
            $packageList[] = "$binary[$a] deb $section[$a] $priority[$a] arch=$architecture[$a]";
        }
        $packageList = implode("\n ", $packageList);
        // consolidate package names
        $binary = implode(",", $binary);
        // consolidate architectures
        $architecture = implode(",", array_unique($architecture));
        // calculate checksums for files
        $fMeta = [];
        foreach ($tarballs as $f) {
            $fMeta[$f] = [
                'name'   => basename($f),
                'size'   => filesize($f),
                'sha1'   => hash_file("sha1", $f),
                'sha256' => hash_file("sha256", $f),
                'md5'    => hash_file("md5", $f),
            ];
        }
        // consolidate SHA-1 checksums
        $sums = [];
        foreach ($fMeta as $data) {
            $sums[] = $data['sha1']." ".$data['size']." ".$data['name'];
        }
        $sumsSha1 = implode("\n ", $sums);
        // consolidate SHA-256 checksums
        $sums = [];
        foreach ($fMeta as $data) {
            $sums[] = $data['sha256']." ".$data['size']." ".$data['name'];
        }
        $sumsSha256 = implode("\n ", $sums);
        // consolidate MD5 checksums
        $sums = [];
        foreach ($fMeta as $data) {
            $sums[] = $data['md5']." ".$data['size']." ".$data['name'];
        }
        $sumsMd5 = implode("\n ", $sums);
        // return complete file
        return <<< DSC_FILE
Format: $format
Source: {$metadata['Source']}
Binary: $binary
Architecture: $architecture
Version: $version
Maintainer: {$metadata['Maintainer']}
Homepage: {$metadata['Homepage']}
Standards-Version: {$metadata['Standards-Version']}
Vcs-Browser: {$metadata['Vcs-Browser']}
Vcs-Git: {$metadata['Vcs-Git']}
Build-Depends: $buildDepends
Package-List:
 $packageList
Checksums-Sha1:
 $sumsSha1
Checksums-Sha256:
 $sumsSha256
Files:
 $sumsMd5

DSC_FILE;
    }
}
