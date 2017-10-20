<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;

use JKingWeb\Arsse\Misc\ValueInfo as I;
use JKingWeb\Arsse\Test\Misc\StrClass;

/** @covers \JKingWeb\Arsse\Misc\ValueInfo */
class TestValueInfo extends Test\AbstractTest {
    public function setUp() {
        $this->clearData();
    }
    
    public function testGetIntegerInfo() {
        $tests = [
            [null,          I::NULL],
            ["",            I::NULL],
            [1,             I::VALID],
            [PHP_INT_MAX,   I::VALID],
            [1.0,           I::VALID],
            ["1.0",         I::VALID],
            ["001.0",       I::VALID],
            ["1.0e2",       I::VALID],
            ["1",           I::VALID],
            ["001",         I::VALID],
            ["1e2",         I::VALID],
            ["+1.0",        I::VALID],
            ["+001.0",      I::VALID],
            ["+1.0e2",      I::VALID],
            ["+1",          I::VALID],
            ["+001",        I::VALID],
            ["+1e2",        I::VALID],
            [0,             I::VALID | I::ZERO],
            ["0",           I::VALID | I::ZERO],
            ["000",         I::VALID | I::ZERO],
            [0.0,           I::VALID | I::ZERO],
            ["0.0",         I::VALID | I::ZERO],
            ["000.000",     I::VALID | I::ZERO],
            ["+0",          I::VALID | I::ZERO],
            ["+000",        I::VALID | I::ZERO],
            ["+0.0",        I::VALID | I::ZERO],
            ["+000.000",    I::VALID | I::ZERO],
            [-1,            I::VALID | I::NEG],
            [-1.0,          I::VALID | I::NEG],
            ["-1.0",        I::VALID | I::NEG],
            ["-001.0",      I::VALID | I::NEG],
            ["-1.0e2",      I::VALID | I::NEG],
            ["-1",          I::VALID | I::NEG],
            ["-001",        I::VALID | I::NEG],
            ["-1e2",        I::VALID | I::NEG],
            [-0,            I::VALID | I::ZERO],
            ["-0",          I::VALID | I::ZERO],
            ["-000",        I::VALID | I::ZERO],
            [-0.0,          I::VALID | I::ZERO],
            ["-0.0",        I::VALID | I::ZERO],
            ["-000.000",    I::VALID | I::ZERO],
            [false,         0],
            [true,          0],
            ["on",          0],
            ["off",         0],
            ["yes",         0],
            ["no",          0],
            ["true",        0],
            ["false",       0],
            [INF,           I::FLOAT],
            [-INF,          I::FLOAT | I::NEG],
            [NAN,           I::FLOAT],
            [[],            0],
            ["some string", 0],
            ["           ", 0],
            [new \StdClass, 0],
            [new StrClass(""),    I::NULL],
            [new StrClass("1"),   I::VALID],
            [new StrClass("0"),   I::VALID | I::ZERO],
            [new StrClass("-1"),  I::VALID | I::NEG],
            [new StrClass("Msg"), 0],
            [new StrClass("   "), 0],
            [2.5,           I::FLOAT],
            [0.5,           I::FLOAT],
            ["2.5",         I::FLOAT],
            ["0.5",         I::FLOAT],
        ];
        foreach ($tests as $test) {
            list($value, $exp) = $test;
            $this->assertSame($exp, I::int($value), "Test returned ".decbin(I::int($value))." for value: ".var_export($value, true));
        }
    }
    public function testGetStringInfo() {
        $tests = [
            [null,          I::NULL],
            ["",            I::VALID | I::EMPTY],
            [1,             I::VALID],
            [PHP_INT_MAX,   I::VALID],
            [1.0,           I::VALID],
            ["1.0",         I::VALID],
            ["001.0",       I::VALID],
            ["1.0e2",       I::VALID],
            ["1",           I::VALID],
            ["001",         I::VALID],
            ["1e2",         I::VALID],
            ["+1.0",        I::VALID],
            ["+001.0",      I::VALID],
            ["+1.0e2",      I::VALID],
            ["+1",          I::VALID],
            ["+001",        I::VALID],
            ["+1e2",        I::VALID],
            [0,             I::VALID],
            ["0",           I::VALID],
            ["000",         I::VALID],
            [0.0,           I::VALID],
            ["0.0",         I::VALID],
            ["000.000",     I::VALID],
            ["+0",          I::VALID],
            ["+000",        I::VALID],
            ["+0.0",        I::VALID],
            ["+000.000",    I::VALID],
            [-1,            I::VALID],
            [-1.0,          I::VALID],
            ["-1.0",        I::VALID],
            ["-001.0",      I::VALID],
            ["-1.0e2",      I::VALID],
            ["-1",          I::VALID],
            ["-001",        I::VALID],
            ["-1e2",        I::VALID],
            [-0,            I::VALID],
            ["-0",          I::VALID],
            ["-000",        I::VALID],
            [-0.0,          I::VALID],
            ["-0.0",        I::VALID],
            ["-000.000",    I::VALID],
            [false,         0],
            [true,          0],
            ["on",          I::VALID],
            ["off",         I::VALID],
            ["yes",         I::VALID],
            ["no",          I::VALID],
            ["true",        I::VALID],
            ["false",       I::VALID],
            [INF,           0],
            [-INF,          0],
            [NAN,           0],
            [[],            0],
            ["some string", I::VALID],
            ["           ", I::VALID | I::WHITE],
            [new \StdClass, 0],
            [new StrClass(""),    I::VALID | I::EMPTY],
            [new StrClass("1"),   I::VALID],
            [new StrClass("0"),   I::VALID],
            [new StrClass("-1"),  I::VALID],
            [new StrClass("Msg"), I::VALID],
            [new StrClass("   "), I::VALID | I::WHITE],
        ];
        foreach ($tests as $test) {
            list($value, $exp) = $test;
            $this->assertSame($exp, I::str($value), "Test returned ".decbin(I::str($value))." for value: ".var_export($value, true));
        }
    }

    public function testValidateDatabaseIdentifier() {
        $tests = [
            [null,          false, true],
            ["",            false, true],
            [1,             true,  true],
            [PHP_INT_MAX,   true,  true],
            [1.0,           true,  true],
            ["1.0",         true,  true],
            ["001.0",       true,  true],
            ["1.0e2",       true,  true],
            ["1",           true,  true],
            ["001",         true,  true],
            ["1e2",         true,  true],
            ["+1.0",        true,  true],
            ["+001.0",      true,  true],
            ["+1.0e2",      true,  true],
            ["+1",          true,  true],
            ["+001",        true,  true],
            ["+1e2",        true,  true],
            [0,             false, true],
            ["0",           false, true],
            ["000",         false, true],
            [0.0,           false, true],
            ["0.0",         false, true],
            ["000.000",     false, true],
            ["+0",          false, true],
            ["+000",        false, true],
            ["+0.0",        false, true],
            ["+000.000",    false, true],
            [-1,            false, false],
            [-1.0,          false, false],
            ["-1.0",        false, false],
            ["-001.0",      false, false],
            ["-1.0e2",      false, false],
            ["-1",          false, false],
            ["-001",        false, false],
            ["-1e2",        false, false],
            [-0,            false, true],
            ["-0",          false, true],
            ["-000",        false, true],
            [-0.0,          false, true],
            ["-0.0",        false, true],
            ["-000.000",    false, true],
            [false,         false, false],
            [true,          false, false],
            ["on",          false, false],
            ["off",         false, false],
            ["yes",         false, false],
            ["no",          false, false],
            ["true",        false, false],
            ["false",       false, false],
            [INF,           false, false],
            [-INF,          false, false],
            [NAN,           false, false],
            [[],            false, false],
            ["some string", false, false],
            ["           ", false, false],
            [new \StdClass, false, false],
            [new StrClass(""),    false, true],
            [new StrClass("1"),   true,  true],
            [new StrClass("0"),   false, true],
            [new StrClass("-1"),  false, false],
            [new StrClass("Msg"), false, false],
            [new StrClass("   "), false, false],
        ];
        foreach ($tests as $test) {
            list($value, $exp, $expNull) = $test;
            $this->assertSame($exp, I::id($value), "Non-null test failed for value: ".var_export($value, true));
            $this->assertSame($expNull, I::id($value, true), "Null test failed for value: ".var_export($value, true));
        }
    }

    public function testValidateBoolean() {
        $tests = [
            [null,          null],
            ["",            false],
            [1,             true],
            [PHP_INT_MAX,   null],
            [1.0,           true],
            ["1.0",         true],
            ["001.0",       true],
            ["1.0e2",       null],
            ["1",           true],
            ["001",         true],
            ["1e2",         null],
            ["+1.0",        true],
            ["+001.0",      true],
            ["+1.0e2",      null],
            ["+1",          true],
            ["+001",        true],
            ["+1e2",        null],
            [0,             false],
            ["0",           false],
            ["000",         false],
            [0.0,           false],
            ["0.0",         false],
            ["000.000",     false],
            ["+0",          false],
            ["+000",        false],
            ["+0.0",        false],
            ["+000.000",    false],
            [-1,            null],
            [-1.0,          null],
            ["-1.0",        null],
            ["-001.0",      null],
            ["-1.0e2",      null],
            ["-1",          null],
            ["-001",        null],
            ["-1e2",        null],
            [-0,            false],
            ["-0",          false],
            ["-000",        false],
            [-0.0,          false],
            ["-0.0",        false],
            ["-000.000",    false],
            [false,         false],
            [true,          true],
            ["on",          true],
            ["off",         false],
            ["yes",         true],
            ["no",          false],
            ["true",        true],
            ["false",       false],
            [INF,           null],
            [-INF,          null],
            [NAN,           null],
            [[],            null],
            ["some string", null],
            ["           ", null],
            [new \StdClass, null],
            [new StrClass(""),    false],
            [new StrClass("1"),   true],
            [new StrClass("0"),   false],
            [new StrClass("-1"),  null],
            [new StrClass("Msg"), null],
            [new StrClass("   "), null],
        ];
        foreach ($tests as $test) {
            list($value, $exp) = $test;
            $this->assertSame($exp, I::bool($value), "Null Test failed for value: ".var_export($value, true));
            if (is_null($exp)) {
                $this->assertTrue(I::bool($value, true), "True Test failed for value: ".var_export($value, true));
                $this->assertFalse(I::bool($value, false), "False Test failed for value: ".var_export($value, true));
            }
        }
    }

    public function testNormalizeValues() {
        $tests = [
            /* The test data are very dense for this set. Each value is normalized to each of the following types:

                - mixed (no normalization performed)
                - null
                - boolean
                - integer
                - float
                - string
                - array

               For each of these types, there is an expected output value, as well as a boolean indicating whether
               the value should pass or fail a strict normalization. Conversion to DateTime is covered below by a different data set
            */
            /* Input value                        null         bool           int                      float                        string                          array                                         */
            [null,                                  [null,true], [false,false], [0,              false], [0.0,                false], ["",                    false], [[],                                     false]],
            ["",                                    [null,true], [false,true],  [0,              false], [0.0,                false], ["",                    true],  [[""],                                   false]],
            [1,                                     [null,true], [true, true],  [1,              true],  [1.0,                true],  ["1",                   true],  [[1],                                    false]],
            [PHP_INT_MAX,                           [null,true], [true, false], [PHP_INT_MAX,    true],  [(float) PHP_INT_MAX,true],  [(string) PHP_INT_MAX,  true],  [[PHP_INT_MAX],                          false]],
            [1.0,                                   [null,true], [true, true],  [1,              true],  [1.0,                true],  ["1",                   true],  [[1.0],                                  false]],
            ["1.0",                                 [null,true], [true, true],  [1,              true],  [1.0,                true],  ["1.0",                 true],  [["1.0"],                                false]],
            ["001.0",                               [null,true], [true, true],  [1,              true],  [1.0,                true],  ["001.0",               true],  [["001.0"],                              false]],
            ["1.0e2",                               [null,true], [true, false], [100,            true],  [100.0,              true],  ["1.0e2",               true],  [["1.0e2"],                              false]],
            ["1",                                   [null,true], [true, true],  [1,              true],  [1.0,                true],  ["1",                   true],  [["1"],                                  false]],
            ["001",                                 [null,true], [true, true],  [1,              true],  [1.0,                true],  ["001",                 true],  [["001"],                                false]],
            ["1e2",                                 [null,true], [true, false], [100,            true],  [100.0,              true],  ["1e2",                 true],  [["1e2"],                                false]],
            ["+1.0",                                [null,true], [true, true],  [1,              true],  [1.0,                true],  ["+1.0",                true],  [["+1.0"],                               false]],
            ["+001.0",                              [null,true], [true, true],  [1,              true],  [1.0,                true],  ["+001.0",              true],  [["+001.0"],                             false]],
            ["+1.0e2",                              [null,true], [true, false], [100,            true],  [100.0,              true],  ["+1.0e2",              true],  [["+1.0e2"],                             false]],
            ["+1",                                  [null,true], [true, true],  [1,              true],  [1.0,                true],  ["+1",                  true],  [["+1"],                                 false]],
            ["+001",                                [null,true], [true, true],  [1,              true],  [1.0,                true],  ["+001",                true],  [["+001"],                               false]],
            ["+1e2",                                [null,true], [true, false], [100,            true],  [100.0,              true],  ["+1e2",                true],  [["+1e2"],                               false]],
            [0,                                     [null,true], [false,true],  [0,              true],  [0.0,                true],  ["0",                   true],  [[0],                                    false]],
            ["0",                                   [null,true], [false,true],  [0,              true],  [0.0,                true],  ["0",                   true],  [["0"],                                  false]],
            ["000",                                 [null,true], [false,true],  [0,              true],  [0.0,                true],  ["000",                 true],  [["000"],                                false]],
            [0.0,                                   [null,true], [false,true],  [0,              true],  [0.0,                true],  ["0",                   true],  [[0.0],                                  false]],
            ["0.0",                                 [null,true], [false,true],  [0,              true],  [0.0,                true],  ["0.0",                 true],  [["0.0"],                                false]],
            ["000.000",                             [null,true], [false,true],  [0,              true],  [0.0,                true],  ["000.000",             true],  [["000.000"],                            false]],
            ["+0",                                  [null,true], [false,true],  [0,              true],  [0.0,                true],  ["+0",                  true],  [["+0"],                                 false]],
            ["+000",                                [null,true], [false,true],  [0,              true],  [0.0,                true],  ["+000",                true],  [["+000"],                               false]],
            ["+0.0",                                [null,true], [false,true],  [0,              true],  [0.0,                true],  ["+0.0",                true],  [["+0.0"],                               false]],
            ["+000.000",                            [null,true], [false,true],  [0,              true],  [0.0,                true],  ["+000.000",            true],  [["+000.000"],                           false]],
            [-1,                                    [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-1",                  true],  [[-1],                                   false]],
            [-1.0,                                  [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-1",                  true],  [[-1.0],                                 false]],
            ["-1.0",                                [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-1.0",                true],  [["-1.0"],                               false]],
            ["-001.0",                              [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-001.0",              true],  [["-001.0"],                             false]],
            ["-1.0e2",                              [null,true], [true, false], [-100,           true],  [-100.0,             true],  ["-1.0e2",              true],  [["-1.0e2"],                             false]],
            ["-1",                                  [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-1",                  true],  [["-1"],                                 false]],
            ["-001",                                [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-001",                true],  [["-001"],                               false]],
            ["-1e2",                                [null,true], [true, false], [-100,           true],  [-100.0,             true],  ["-1e2",                true],  [["-1e2"],                               false]],
            [-0,                                    [null,true], [false,true],  [0,              true],  [0.0,                true],  ["0",                   true],  [[-0],                                   false]],
            ["-0",                                  [null,true], [false,true],  [0,              true],  [-0.0,               true],  ["-0",                  true],  [["-0"],                                 false]],
            ["-000",                                [null,true], [false,true],  [0,              true],  [-0.0,               true],  ["-000",                true],  [["-000"],                               false]],
            [-0.0,                                  [null,true], [false,true],  [0,              true],  [-0.0,               true],  ["-0",                  true],  [[-0.0],                                 false]],
            ["-0.0",                                [null,true], [false,true],  [0,              true],  [-0.0,               true],  ["-0.0",                true],  [["-0.0"],                               false]],
            ["-000.000",                            [null,true], [false,true],  [0,              true],  [-0.0,               true],  ["-000.000",            true],  [["-000.000"],                           false]],
            [false,                                 [null,true], [false,true],  [0,              false], [0.0,                false], ["",                    false], [[false],                                false]],
            [true,                                  [null,true], [true, true],  [1,              false], [1.0,                false], ["1",                   false], [[true],                                 false]],
            ["on",                                  [null,true], [true, true],  [0,              false], [0.0,                false], ["on",                  true],  [["on"],                                 false]],
            ["off",                                 [null,true], [false,true],  [0,              false], [0.0,                false], ["off",                 true],  [["off"],                                false]],
            ["yes",                                 [null,true], [true, true],  [0,              false], [0.0,                false], ["yes",                 true],  [["yes"],                                false]],
            ["no",                                  [null,true], [false,true],  [0,              false], [0.0,                false], ["no",                  true],  [["no"],                                 false]],
            ["true",                                [null,true], [true, true],  [0,              false], [0.0,                false], ["true",                true],  [["true"],                               false]],
            ["false",                               [null,true], [false,true],  [0,              false], [0.0,                false], ["false",               true],  [["false"],                              false]],
            [INF,                                   [null,true], [true, false], [0,              false], [INF,                true],  ["INF",                 false], [[INF],                                  false]],
            [-INF,                                  [null,true], [true, false], [0,              false], [-INF,               true],  ["-INF",                false], [[-INF],                                 false]],
            [NAN,                                   [null,true], [false,false], [0,              false], [NAN,                true],  ["NAN",                 false], [[],                                     false]],
            [[],                                    [null,true], [false,false], [0,              false], [0.0,                false], ["",                    false], [[],                                     true] ],
            ["some string",                         [null,true], [true, false], [0,              false], [0.0,                false], ["some string",         true],  [["some string"],                        false]],
            ["           ",                         [null,true], [true, false], [0,              false], [0.0,                false], ["           ",         true],  [["           "],                        false]],
            [new \StdClass,                         [null,true], [true, false], [0,              false], [0.0,                false], ["",                    false], [[new \StdClass],                        false]],
            [new StrClass(""),                      [null,true], [false,true],  [0,              false], [0.0,                false], ["",                    true],  [[new StrClass("")],                     false]],
            [new StrClass("1"),                     [null,true], [true, true],  [1,              true],  [1.0,                true],  ["1",                   true],  [[new StrClass("1")],                    false]],
            [new StrClass("0"),                     [null,true], [false,true],  [0,              true],  [0.0,                true],  ["0",                   true],  [[new StrClass("0")],                    false]],
            [new StrClass("-1"),                    [null,true], [true, false], [-1,             true],  [-1.0,               true],  ["-1",                  true],  [[new StrClass("-1")],                   false]],
            [new StrClass("Msg"),                   [null,true], [true, false], [0,              false], [0.0,                false], ["Msg",                 true],  [[new StrClass("Msg")],                  false]],
            [new StrClass("   "),                   [null,true], [true, false], [0,              false], [0.0,                false], ["   ",                 true],  [[new StrClass("   ")],                  false]],
            [2.5,                                   [null,true], [true, false], [2,              false], [2.5,                true],  ["2.5",                 true],  [[2.5],                                  false]],
            [0.5,                                   [null,true], [true, false], [0,              false], [0.5,                true],  ["0.5",                 true],  [[0.5],                                  false]],
            ["2.5",                                 [null,true], [true, false], [2,              false], [2.5,                true],  ["2.5",                 true],  [["2.5"],                                false]],
            ["0.5",                                 [null,true], [true, false], [0,              false], [0.5,                true],  ["0.5",                 true],  [["0.5"],                                false]],
            [$this->d("2010-01-01T00:00:00", 0, 0), [null,true], [true, false], [1262304000,     false], [1262304000.0,       false], ["2010-01-01T00:00:00Z",true],  [[$this->d("2010-01-01T00:00:00", 0, 0)],false]],
            [$this->d("2010-01-01T00:00:00", 0, 1), [null,true], [true, false], [1262304000,     false], [1262304000.0,       false], ["2010-01-01T00:00:00Z",true],  [[$this->d("2010-01-01T00:00:00", 0, 1)],false]],
            [$this->d("2010-01-01T00:00:00", 1, 0), [null,true], [true, false], [1262322000,     false], [1262322000.0,       false], ["2010-01-01T05:00:00Z",true],  [[$this->d("2010-01-01T00:00:00", 1, 0)],false]],
            [$this->d("2010-01-01T00:00:00", 1, 1), [null,true], [true, false], [1262322000,     false], [1262322000.0,       false], ["2010-01-01T05:00:00Z",true],  [[$this->d("2010-01-01T00:00:00", 1, 1)],false]],
            [1e14,                                  [null,true], [true, false], [100000000000000,true],  [1e14,               true],  ["100000000000000",     true],  [[1e14],                                 false]],
            [1e-6,                                  [null,true], [true, false], [0,              false], [1e-6,               true],  ["0.000001",            true],  [[1e-6],                                 false]],
            [[1,2,3],                               [null,true], [true, false], [0,              false], [0.0,                false], ["",                    false], [[1,2,3],                                true] ],
            [['a'=>1,'b'=>2],                       [null,true], [true, false], [0,              false], [0.0,                false], ["",                    false], [['a'=>1,'b'=>2],                        true] ],
            [new Test\Result([['a'=>1,'b'=>2]]),    [null,true], [true, false], [0,              false], [0.0,                false], ["",                    false], [[['a'=>1,'b'=>2]],                      true] ],
        ];
        $params = [
            [I::T_MIXED,  "Mixed"         ],
            [I::T_NULL,   "Null",         ],
            [I::T_BOOL,   "Boolean",      ],
            [I::T_INT,    "Integer",      ],
            [I::T_FLOAT,  "Floating point"],
            [I::T_STRING, "String",       ],
            [I::T_ARRAY,  "Array",        ],
        ];
        foreach ($params as $index => $param) {
            list($type, $name) = $param;
            $this->assertNull(I::normalize(null, $type | I::M_STRICT | I::M_NULL), $name." null-passthrough test failed");
            foreach ($tests as $test) {
                list($exp, $pass) = $index ? $test[$index] : [$test[$index], true];
                $value = $test[0];
                $assert = (is_float($exp) && is_nan($exp) ? "assertNan" : (is_scalar($exp) ? "assertSame" : "assertEquals"));
                $this->$assert($exp, I::normalize($value, $type), $name." test failed for value: ".var_export($value, true));
                if ($pass) {
                    $this->$assert($exp, I::normalize($value, $type | I::M_DROP), $name." drop test failed for value: ".var_export($value, true));
                    $this->$assert($exp, I::normalize($value, $type | I::M_STRICT), $name." error test failed for value: ".var_export($value, true));
                } else {
                    $this->assertNull(I::normalize($value, $type | I::M_DROP), $name." drop test failed for value: ".var_export($value, true));
                    $exc = new ExceptionType("strictFailure", $type);
                    try {
                        $act = I::normalize($value, $type | I::M_STRICT);
                    } catch (ExceptionType $e) {
                        $act = $e;
                    } finally {
                        $this->assertEquals($exc, $act, $name." error test failed for value: ".var_export($value, true));
                    }
                }
            }
        }
        // DateTimeInterface tests
        $tests = [
            /* Input value                        microtime                    iso8601                      iso8601m                     http                         sql                          date                         time                         unix                         float                        '!M j, Y (D)'                *strtotime* (null)                  */
            [null,                                  null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                               ],
            [$this->d("2010-01-01T00:00:00", 0, 0), $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),               ],
            [$this->d("2010-01-01T00:00:00", 0, 1), $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),               ],
            [$this->d("2010-01-01T00:00:00", 1, 0), $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),               ],
            [$this->d("2010-01-01T00:00:00", 1, 1), $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),        $this->t(1262322000),               ],
            [1262304000,                            $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),        $this->t(1262304000),               ],
            [1262304000.123456,                     $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456), $this->t(1262304000.123456),        ],
            [1262304000.42,                         $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),     $this->t(1262304000.42),            ],
            ["0.12345600 1262304000",               $this->t(1262304000.123456), null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                               ],
            ["0.42 1262304000",                     null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                               ],
            ["2010-01-01T00:00:00",                 null,                        $this->t(1262304000),        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01T00:00:00Z",                null,                        $this->t(1262304000),        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01T00:00:00+0000",            null,                        $this->t(1262304000),        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01T00:00:00-0000",            null,                        $this->t(1262304000),        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01T00:00:00+00:00",           null,                        $this->t(1262304000),        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01T00:00:00-05:00",           null,                        $this->t(1262322000),        $this->t(1262322000),        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262322000),               ],
            ["2010-01-01T00:00:00.123456Z",         null,                        null,                        $this->t(1262304000.123456), null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000.123456),        ],
            ["Fri, 01 Jan 2010 00:00:00 GMT",       null,                        null,                        null,                        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01 00:00:00",                 null,                        null,                        null,                        null,                        $this->t(1262304000),        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["2010-01-01",                          null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
            ["12:34:56",                            null,                        null,                        null,                        null,                        null,                        null,                        $this->t(45296),             null,                        null,                        null,                        $this->t(strtotime("today")+45296), ],
            ["1262304000",                          null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),        null,                        null,                        null,                               ],
            ["1262304000.123456",                   null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000.123456), null,                        null,                               ],
            ["1262304000.42",                       null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000.42),     null,                        null,                               ],
            ["Jan 1, 2010 (Fri)",                   null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),        null,                               ],
            ["First day of Jan 2010 12AM",          null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        $this->t(1262304000),               ],
        ];
        $formats = [
            "microtime",
            "iso8601",
            "iso8601m",
            "http",
            "sql",
            "date",
            "time",
            "unix",
            "float",
            "!M j, Y (D)",
            null,
        ];
        $exc = new ExceptionType("strictFailure", I::T_DATE);
        foreach ($formats as $index => $format) {
            foreach ($tests as $test) {
                $value = $test[0];
                $exp = $test[$index+1];
                $this->assertEquals($exp, I::normalize($value, I::T_DATE, $format), "Test failed for format ".var_export($format, true)." using value ".var_export($value, true));
                $this->assertEquals($exp, I::normalize($value, I::T_DATE | I::M_DROP, $format), "Drop test failed for format ".var_export($format, true)." using value ".var_export($value, true));
                // test for exception in case of errors
                $exp = $exp ?? $exc;
                try {
                    $act = I::normalize($value, I::T_DATE | I::M_STRICT, $format);
                } catch (ExceptionType $e) {
                    $act = $e;
                } finally {
                    $this->assertEquals($exp, $act, "Error test failed for format ".var_export($format, true)." using value ".var_export($value, true));
                }
            }
        }
        // Array-mode tests
        $tests = [
            [I::T_INT    | I::M_DROP,   new Test\Result([1, 2, 2.2, 3]), [1,2,null,3]   ],
            [I::T_INT,                  new Test\Result([1, 2, 2.2, 3]), [1,2,2,3]      ],
            [I::T_STRING | I::M_STRICT, "Bare string",                   ["Bare string"]],
        ];
        foreach ($tests as $index => $test) {
            list($type, $value, $exp) = $test;
            $this->assertEquals($exp, I::normalize($value, $type | I::M_ARRAY, "iso8601"), "Failed test #$index");
        }
    }

    protected function d($spec, $local, $immutable): \DateTimeInterface {
        $tz = $local ? new \DateTimeZone("America/Toronto") : new \DateTimeZone("UTC");
        if ($immutable) {
            return \DateTimeImmutable::createFromFormat("!Y-m-d\TH:i:s", $spec, $tz);
        } else {
            return \DateTime::createFromFormat("!Y-m-d\TH:i:s", $spec, $tz);
        }
    }

    protected function t(float $spec): \DateTime {
        return \DateTime::createFromFormat("U.u", sprintf("%F", $spec), new \DateTimeZone("UTC"));
    }
}
