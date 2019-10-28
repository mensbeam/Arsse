# The Advanced RSS Environment

The Arsse is a news aggregator server, written in PHP, which implements multiple synchronization protocols. Unlike most other aggregator servers, The Arsse does not include a Web front-end (though one is planned as a separate project), and it relies on existing protocols to maximize compatibility with existing clients.

Information on how to install and use the software can be found in [the manual](https://thearsse.com/manual/), which is available online as well as with every copy of the software. This readme file instead focuses on how to set up a programming environment to modify the source code.

# Installing from source

The main repository for The Arsse can be found at [code.mensbeam.com](https://code.mensbeam.com/MensBeam/arsse/), with a mirror also available [at GitHub](https://github.com/mensbeam/arsse/). The main repository is preferred, as the GitHub mirror can sometimes be out of date.

[Composer](https://getcomposer.org/) is required to manage PHP dependencies. After cloning the repository or downloading a source code tarball, running `composer install` will download all the required dependencies, and will advise if any PHP extensions need to be installed. If not installing as a programming environment, running `composer install --no-dev` is recommended.

# Repository structure

## Library code

The code which runs The Arsse, contained in `/arsse.php`, is only a short stub: the application itself is composed of the classes found under `/lib/`, with the main ones being:

| Path           | Description                                             |
|----------------|---------------------------------------------------------|
| `CLI.php`      | The command-line interface, including its documentation |
| `Conf.php`     | Configuration handling                                  |
| `Database.php` | High-level database interface                           |
| `Db/`          | Low-level database abstraction layer                    |
| `REST/`        | Protocol implementations                                |
| `REST.php`     | General protocol handler for CORS, HTTP auth, etc.      |
| `Arsse.php`    | Singleton glueing the parts together                    |

The `/lib/Database.php` file is the heart of the application, performing queries on behalf of protocol implementations or the command-line interface.

Also necessary to the functioning of the application is the `/vendor/` directory, which contains PHP libraries which The Arsse depends upon. These are managed by Composer.

## Supporting data files

The `/locale/` and `/sql/` directories contain human-language files and database schemata, both of which are occasionally used by the application in the course of execution. The `/www/` directory serves as a document root for a few static files to be made available to users by a Web server.

The `/dist/` directory, on the other hand, contains samples of configuration for Web servers and init systems. These are not used by The Arsse itself, but are merely distributed with it for reference.

## Documentation

The source text for The Arsse's manual can be found in `/docs/`, with pages written in [Markdown](https://spec.commonmark.org/current/) and converted to HTML [with Daux](#building-the-manual). If a static manual is generated its files will appear under `/manual/`.

In addition to the manual the files `/README.md` (this file), `/CHANGELOG`, `/UPGRADING`, `/LICENSE`, and `/AUTHORS` also document various things about the software, rather than the software itself.

## Tests

The `/tests/` directory contains everything related to automated testing. It is itself organized as follows:

| Path               | Description                                                                        |
|--------------------|------------------------------------------------------------------------------------|
| `cases/`           | The test cases themselves, organized in roughly the same structure as the code     |
| `coverage/`        | (optional) Generated code coverage reports                                         |
| `docroot/`         | Sample documents used in some tests, to be returned by the PHP's basic HTTP server |
| `lib/`             | Supporting classes which do not contain test cases                                 |
| `bootstrap.php`    | Bootstrap script, equivalent to `/arsse.php`, but for tests                        |
| `phpunit.dist.xml` | PHPUnit configuration file                                                         |
| `server.php`       | Simple driver for the PHP HTTP server used during testing                          |

PHPUnit's configuration can be customized by copying its configuration file to `/tests/phpunit.xml` and modifying the copy accordingly.

## Tooling

The `/vendor-bin/` directory houses the files needed for the tools used in The Arsse's programming environment. These are managed by the Composer ["bin" plugin](https://github.com/bamarni/composer-bin-plugin) and are not used by The Arsse itself. The following files are also related to various programming tools:

| Path              | Description                                              |
|-------------------|----------------------------------------------------------|
| `/.gitattributes` | Git settings for handling files                          |
| `/.gitignore`     | Git file exclusion patterns                              |
| `/.php_cs.dist`   | Configuration for [php-cs-fixer](https://cs.symfony.com) |
| `/.php_cs.cache`  | Cache for php-cs-fixer                                   |
| `/composer.json`  | Configuration for Composer                               |
| `/composer.lock`  | Version synchronization data for Composer                |
| `/RoboFile.php`   | Task definitions for [Robo](https://robo.li/)            |
| `/robo`           | Simple wrapper for executing Robo on POSIX systems       |
| `/robo.bat`       | Simple wrapper for executing Robo on Windows             |

In addition the files `/package.json`, `/yarn.lock`, and `/postcss.config.js` as well as the `/node_modules/` directory are used by [Yarn](https://yarnpkg.com/) and [PostCSS](https://postcss.org/) when modifying the stylesheet for The Arsse's manual.

# Common tasks

We use a tool called [Robo](https://robo.li/) to simplify the execution of common tasks. It is installed with The Arsse's other dependencies, and its configured tasks can be listed by executing `./robo` without arguments.

## Running tests

The Arsse has an extensive [PHPUnit](https://phpunit.de/) test suite; tests can be run by executing `./robo test`, which can be supplemented with any arguments understoof by PHPUnit. For example, to test only the Tiny Tiny RSS protocol, one could run `./robo test --testsuite TTRSS`.

There is also a `test:quick` Robo task which excludes slower tests, and a `test:full` task which includes redundant tests in addition to the standard test suite

### Test coverage

Computing the coverage of tests can be done by running `./robo coverage`. Either [phpdbg](https://php.net/manual/en/book.phpdbg.php) or [Xdebug](https://xdebug.org) is required for this. An HTML-format coverage report will be written to `/tests/coverage/`.

## Enforcing coding style

The [php-cs-fixer](https://cs.symfony.com) tool, executed via `./robo clean`, can be used to rewrite code to adhere to The Arsse's coding style. The style largely follows [PSR-2](https://www.php-fig.org/psr/psr-2/) with some exceptions:

- Classes, methods, and functions should have their opening brace on the same line as the signature
- Anonymous functions should have no space before the parameter list

## Building the manual

The Arsse's user manual, made using [Daux](https://daux.io/), can be compiled by running `./robo manual`, which will output files to `/manual/`. It is also possible to serve the manual from a test HTTP server on port 8085 by running `./robo manual:live`.

### Rebuilding the manual theme

The manual employs a custom theme derived from the standard Daux theme. If the standard Daux theme receives improvements, the custom theme can be rebuilt by running `./robo manual:theme`. This requires that [NodeJS](https://nodejs.org) and [Yarn](https://yarnpkg.com/) be installed, but JavaScript tools are not required to modify The Arsse itself, nor the content of the manual.

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
