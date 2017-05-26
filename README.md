Arsse: Advanced RSS Environment
===============================

TODO: Fill in stuff

License
-------

Arsse is made available under the permissive MIT license.  See the LICENSE file included with the distribution or source code for exact legal text. Dependencies included in the distribution may be governed by other licenses.

Running tests
-------------

To run the test suite, you must have [Composer](https://getcomposer.org/) installed as well as the command-line PHP interpreter (this is normally required to use Composer). Port 8000 must also be available for use by the built-in PHP Web server.

``` sh
# first install dependencies
composer install
# run the tests
./tests/test
```

The example uses Unix syntax, but the test suite also runs in Windows. By default all tests are run; you can pass the same arguments to the test runner [as you would to PHPUnit](https://phpunit.de/manual/current/en/textui.html#textui.clioptions):

``` sh
./tests/test --testsuite "Configuration"
```
