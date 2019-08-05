# The Advanced RSS Environment

The Arsse is a news aggregator server, written in PHP, which implements multiple synchronization protocols. Unlike most other aggregator servers, The Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients.

Information on how to install and use the software can be found in [the manual](https://thearsse.com/manual/), which is available online as well as with every copy of the software. This readme file instead focuses on how to set up a programming environment to modify the source code.

# Installing from source

The main repository for The Arsse can be found at [code.mensbeam.com](https://code.mensbeam.com/MensBeam/arsse/), with a mirror also available [at GitHub](https://github.com/meansbeam/arsse/). The main repository is preferred, as the GitHub mirror can sometimes be out of date.

[Composer](https://getcomposer.org/) is required to manage PHP dependencies. After cloning the repository or downloading a source code tarball, running `composer install` will download all the required dependencies, and will advise if any PHP extensions need to be installed.

# Common tasks

We use a tool called [Robo](https://robo.li/) to simplify the execution of common tasks. It is installed with The Arsse's other dependencies, and its configured tasks can be listed by executing `./robo` without arguments.

## Running tests

The Arsse has an extensive PHPUnit test suite; tests can be run by executing `./robo test`, which can be supplemented with any arguments understoof by PHPUnit. For example, to test only the Tiny Tiny RSS protocol, one could run `/robo test --testsuite TTRSS`.

There is also a `test:quick` Robo task which excludes slower tests, and a `test:full` task which includes redundant tests in addition to the standard test suite

### Testing PostgreSQL and MySQL

TODO

### Test coverage

Computing the coverage of tests can be done by running `./robo coverage`. Either [phpdbg](https://php.net/manual/en/book.phpdbg.php) or [Xdebug](https://xdebug.org) is required for this. An HTML-format coverage report will be written to `./tests/coverage/`.

## Enforcing coding style

The [php-cs-fixer](https://cs.symfony.com) tool, executed via `./robo clean`, can be used to rewrite code to adhere to The Arsse's coding style. The style largely follows [PSR-2](https://www.php-fig.org/psr/psr-2/) with some exceptions:

- Classes, methods, and functions should have their opening brace on the same line as the signature
- Anonymous functions should have no space before the parameter list

## Building the manual

The Arsse's user manual, made using [Daux](https://daux.io/), can be compiled by running `./robo manual`, which will output files to `./manual/`. It is also possible to serve the manual from a test HTTP server on port 8085 by running `./robo manual:live`.

### Rebuilding the manual theme

The manual employs a custom theme derived from the standard Daux theme. If the standard Daux theme receives improvements, the custom theme can be rebuilt by running `./robo manual:theme`. This requires that [NodeJS](https://nodejs,org) and [Yarn](https://yarnpkg.com/) be installed, but JavaScript tools are not required to modify The Arsse itself, nor the content of the manual.

The Robo task `manual:css` will recompile the theme's stylesheet without rebuilding the entire theme.

## Packaging a release

Producing a release package is done by running `./robo package`. This performs the following operations:

- Duplicates a working tree with the commit (usually a release tag) to package
- Generates the manual
- Installs runtime Composer dependencies with an optimized autoloader
- Deletes numerous unneeded files
- Exports the default configuration of The Arsse to a file
- Compresses the remaining files into a tarball

Due to the first step, [Git](https://git-scm.com/) is required to package a release.
