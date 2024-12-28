<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\ValueInfo as I;
use JKingWeb\Arsse\Test\Misc\StrClass;
use JKingWeb\Arsse\Test\Result;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(\JKingWeb\Arsse\Misc\ValueInfo::class)]
class TestValueInfo extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testGetIntegerInfo(): void {
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
            [new \StdClass(), 0],
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
            [" 1 ",         I::VALID],
        ];
        foreach ($tests as $test) {
            [$value, $exp] = $test;
            $this->assertSame($exp, I::int($value), "Test returned ".decbin(I::int($value))." for value: ".var_export($value, true));
        }
    }
    public function testGetStringInfo(): void {
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
            [new \StdClass(), 0],
            [new StrClass(""),    I::VALID | I::EMPTY],
            [new StrClass("1"),   I::VALID],
            [new StrClass("0"),   I::VALID],
            [new StrClass("-1"),  I::VALID],
            [new StrClass("Msg"), I::VALID],
            [new StrClass("   "), I::VALID | I::WHITE],
        ];
        foreach ($tests as $test) {
            [$value, $exp] = $test;
            $this->assertSame($exp, I::str($value), "Test returned ".decbin(I::str($value))." for value: ".var_export($value, true));
        }
    }

    public function testValidateDatabaseIdentifier(): void {
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
            [new \StdClass(), false, false],
            [new StrClass(""),    false, true],
            [new StrClass("1"),   true,  true],
            [new StrClass("0"),   false, true],
            [new StrClass("-1"),  false, false],
            [new StrClass("Msg"), false, false],
            [new StrClass("   "), false, false],
        ];
        foreach ($tests as $test) {
            [$value, $exp, $expNull] = $test;
            $this->assertSame($exp, I::id($value), "Non-null test failed for value: ".var_export($value, true));
            $this->assertSame($expNull, I::id($value, true), "Null test failed for value: ".var_export($value, true));
        }
    }

    public function testValidateBoolean(): void {
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
            [new \StdClass(), null],
            [new StrClass(""),    false],
            [new StrClass("1"),   true],
            [new StrClass("0"),   false],
            [new StrClass("-1"),  null],
            [new StrClass("Msg"), null],
            [new StrClass("   "), null],
        ];
        foreach ($tests as $test) {
            [$value, $exp] = $test;
            $this->assertSame($exp, I::bool($value), "Null Test failed for value: ".var_export($value, true));
            if (is_null($exp)) {
                $this->assertTrue(I::bool($value, true), "True Test failed for value: ".var_export($value, true));
                $this->assertFalse(I::bool($value, false), "False Test failed for value: ".var_export($value, true));
            }
        }
    }


    #[DataProvider('provideSimpleNormalizationValues')]
    public function testNormalizeSimpleValues($input, string $typeName, $exp, bool $pass, bool $strict, bool $drop): void {
        $assert = function($exp, $act, string $msg) {
            if (is_null($exp)) {
                $this->assertNull($act, $msg);
            } elseif (is_float($exp) && is_nan($exp)) {
                $this->assertNan($act, $msg);
            } elseif (is_scalar($exp)) {
                $this->assertSame($exp, $act, $msg);
            } elseif ($exp instanceof \DateInterval && $act instanceof \DateInterval) {
                $format = "\Py\Ym\Md\D\Th\HiMs\S";
                $this->assertSame($exp->format($format), $act->format($format), $msg);
            } else {
                $this->assertEquals($exp, $act, $msg);
            }
        };
        $typeConst = [
            'Mixed'          => I::T_MIXED,
            'Null'           => I::T_NULL,
            'Boolean'        => I::T_BOOL,
            'Integer'        => I::T_INT,
            'Floating point' => I::T_FLOAT,
            'String'         => I::T_STRING,
            'Array'          => I::T_ARRAY,
            'Date interval'  => I::T_INTERVAL,
        ][$typeName];
        if ($strict && $drop) {
            $modeName = "strict drop";
            $modeConst = I::M_STRICT | I::M_DROP;
        } elseif ($strict) {
            $modeName = "strict conversion";
            $modeConst = I::M_STRICT;
        } elseif ($drop) {
            $modeName = "drop";
            $modeConst = I::M_DROP;
        } else {
            $modeName = "loose conversion";
            $modeConst = 0;
        }
        if (is_null($input)) {
            // if the input value is null, perform a null passthrough test in addition to the test itself
            $this->assertNull(I::normalize($input, $typeConst | $modeConst | I::M_NULL), "$typeName null passthrough test failed.");
        }
        if (!$drop && $strict && !$pass) {
            // if we're performing a strict comparison and the value is supposed to fail, we should be getting an exception
            $this->assertException("strictFailure", "", "ExceptionType");
            I::normalize($input, $typeConst | $modeConst);
            $this->assertTrue(false, "$typeName $modeName test expected exception");
        } elseif ($drop && !$pass) {
            // if we're performing a drop comparison and the value is supposed to fail, change the expectation to null
            $exp = null;
        }
        $assert($exp, I::normalize($input, $typeConst | $modeConst), "$typeName $modeName test failed.");
        // check that the result is the same even in null mode
        if (!is_null($input)) {
            $assert($exp, I::normalize($input, $typeConst | $modeConst | I::M_NULL), "$typeName $modeName (null pass-through) test failed.");
        }
    }


    #[DataProvider('provideDateNormalizationValues')]
    public function testNormalizeDateValues($input, $format, $exp, bool $strict, bool $drop): void {
        if ($strict && $drop) {
            $modeName = "strict drop";
            $modeConst = I::M_STRICT | I::M_DROP;
        } elseif ($strict) {
            $modeName = "strict conversion";
            $modeConst = I::M_STRICT;
        } elseif ($drop) {
            $modeName = "drop";
            $modeConst = I::M_DROP;
        } else {
            $modeName = "loose conversion";
            $modeConst = 0;
        }
        if (is_null($exp)) {
            if (is_null($input)) {
                // if the input value is null, perform a null passthrough test before the test itself
                $this->assertNull(I::normalize($input, I::T_DATE | $modeConst | I::M_NULL, $format, $format), "Date input format ".var_export($input, true)." failed $modeName (null passthrough) test.");
            }
            if (!$drop && $strict && is_null($exp)) {
                // if we're performing a strict comparison and the value is supposed to fail, we should be getting an exception
                $this->assertException("strictFailure", "", "ExceptionType");
            }
            $this->assertNull(I::normalize($input, I::T_DATE | $modeConst, $format, $format), "Date input format ".var_export($input, true)." failed $modeName test.");
            $this->assertNull(I::normalize($input, I::T_DATE | $modeConst | I::M_NULL, $format, $format), "Date input format ".var_export($input, true)." failed $modeName (null passthrough) test.");
        } else {
            $this->assertEquals($exp, I::normalize($input, I::T_DATE | $modeConst | I::M_NULL, $format, $format), "Date input format ".var_export($input, true)." failed $modeName (null passthrough) test.");
            $this->assertEquals($exp, I::normalize($input, I::T_DATE | $modeConst, $format, $format), "Date input format ".var_export($input, true)." failed $modeName test.");
        }
    }

    public function testNormalizeComplexValues(): void {
        // Array-mode tests
        $tests = [
            [I::T_INT | I::M_DROP,      [1, 2, 2.2, 3],             [1,2,null,3]   ],
            [I::T_INT,                  [1, 2, 2.2, 3],             [1,2,2,3]      ],
            [I::T_INT | I::M_DROP,      new Result([1, 2, 2.2, 3]), [1,2,null,3]   ],
            [I::T_INT,                  new Result([1, 2, 2.2, 3]), [1,2,2,3]      ],
            [I::T_STRING | I::M_STRICT, "Bare string",              ["Bare string"]],
        ];
        foreach ($tests as $index => $test) {
            [$type, $value, $exp] = $test;
            $this->assertEquals($exp, I::normalize($value, $type | I::M_ARRAY, "iso8601"), "Failed test #$index");
        }
        // Date-to-string format tests
        $dateFormats = (new \ReflectionClassConstant(I::class, "DATE_FORMATS"))->getValue();
        $test = new \DateTimeImmutable("now", new \DateTimezone("UTC"));
        $exp = $test->format($dateFormats['iso8601'][1]);
        $this->assertSame($exp, I::normalize($test, I::T_STRING, null), "Failed test for null output date format");
        foreach ($dateFormats as $name => $formats) {
            $exp = $test->format($formats[1]);
            $this->assertSame($exp, I::normalize($test, I::T_STRING, null, $name), "Failed test for output date format '$name'");
        }
        foreach (["U", "M j, Y (D)", "r", "c"] as $format) {
            $exp = $test->format($format);
            $this->assertSame($exp, I::normalize($test, I::T_STRING, null, $format), "Failed test for output date format '$format'");
        }
    }

    public static function provideSimpleNormalizationValues(): iterable {
        $types = [
            "Mixed",
            "Null",
            "Boolean",
            "Integer",
            "Floating point",
            "String",
            "Array",
            "Date interval",
        ];
        $dateDiff = (new \DateTime("2017-01-01T00:00:00Z"))->diff((new \DateTime("2016-01-01T00:00:00Z"))); // 2016 was a leap year
        $dateNorm = clone $dateDiff;
        $dateNorm->f = 0.0;
        $dateNorm->invert = 0;
        foreach ([
            /* The test data are very dense for this set. Each value is normalized to each of the following types:

                - mixed (no normalization performed)
                - null
                - boolean
                - integer
                - float
                - string
                - array
                - interval

               For each of these types, there is an expected output value, as well as a boolean indicating whether
               the value should pass or fail a strict normalization. Conversion to DateTime is covered below by a different data set
            */
            /* Input value                          null         bool           int                          float                              string                          array                                            interval                   */
            [null,                                  [null,true], [false,false], [0,                  false], [0.0,                      false], ["",                    false], [[],                                     false], [null, false]],
            ["",                                    [null,true], [false,true],  [0,                  false], [0.0,                      false], ["",                    true],  [[""],                                   false], [null, false]],
            [1,                                     [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["1",                   true],  [[1],                                    false], [self::i("PT1S"), false]],
            [PHP_INT_MAX,                           [null,true], [true, false], [PHP_INT_MAX,        true],  [(float) PHP_INT_MAX,      true],  [(string) PHP_INT_MAX,  true],  [[PHP_INT_MAX],                          false], [self::i("P292471208677Y195DT15H30M7S"), false]],
            [1.0,                                   [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["1",                   true],  [[1.0],                                  false], [self::i("PT1S"), false]],
            ["1.0",                                 [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["1.0",                 true],  [["1.0"],                                false], [null, false]],
            ["001.0",                               [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["001.0",               true],  [["001.0"],                              false], [null, false]],
            ["1.0e2",                               [null,true], [true, false], [100,                true],  [100.0,                    true],  ["1.0e2",               true],  [["1.0e2"],                              false], [null, false]],
            ["1",                                   [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["1",                   true],  [["1"],                                  false], [null, false]],
            ["001",                                 [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["001",                 true],  [["001"],                                false], [null, false]],
            ["1e2",                                 [null,true], [true, false], [100,                true],  [100.0,                    true],  ["1e2",                 true],  [["1e2"],                                false], [null, false]],
            ["+1.0",                                [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["+1.0",                true],  [["+1.0"],                               false], [null, false]],
            ["+001.0",                              [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["+001.0",              true],  [["+001.0"],                             false], [null, false]],
            ["+1.0e2",                              [null,true], [true, false], [100,                true],  [100.0,                    true],  ["+1.0e2",              true],  [["+1.0e2"],                             false], [null, false]],
            ["+1",                                  [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["+1",                  true],  [["+1"],                                 false], [null, false]],
            ["+001",                                [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["+001",                true],  [["+001"],                               false], [null, false]],
            ["+1e2",                                [null,true], [true, false], [100,                true],  [100.0,                    true],  ["+1e2",                true],  [["+1e2"],                               false], [null, false]],
            [0,                                     [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["0",                   true],  [[0],                                    false], [self::i("PT0S"), false]],
            ["0",                                   [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["0",                   true],  [["0"],                                  false], [null, false]],
            ["000",                                 [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["000",                 true],  [["000"],                                false], [null, false]],
            [0.0,                                   [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["0",                   true],  [[0.0],                                  false], [self::i("PT0S"), false]],
            ["0.0",                                 [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["0.0",                 true],  [["0.0"],                                false], [null, false]],
            ["000.000",                             [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["000.000",             true],  [["000.000"],                            false], [null, false]],
            ["+0",                                  [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["+0",                  true],  [["+0"],                                 false], [null, false]],
            ["+000",                                [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["+000",                true],  [["+000"],                               false], [null, false]],
            ["+0.0",                                [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["+0.0",                true],  [["+0.0"],                               false], [null, false]],
            ["+000.000",                            [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["+000.000",            true],  [["+000.000"],                           false], [null, false]],
            [-1,                                    [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-1",                  true],  [[-1],                                   false], [self::i("PT1S"), false]],
            [-1.0,                                  [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-1",                  true],  [[-1.0],                                 false], [self::i("PT1S"), false]],
            ["-1.0",                                [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-1.0",                true],  [["-1.0"],                               false], [null, false]],
            ["-001.0",                              [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-001.0",              true],  [["-001.0"],                             false], [null, false]],
            ["-1.0e2",                              [null,true], [true, false], [-100,               true],  [-100.0,                   true],  ["-1.0e2",              true],  [["-1.0e2"],                             false], [null, false]],
            ["-1",                                  [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-1",                  true],  [["-1"],                                 false], [null, false]],
            ["-001",                                [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-001",                true],  [["-001"],                               false], [null, false]],
            ["-1e2",                                [null,true], [true, false], [-100,               true],  [-100.0,                   true],  ["-1e2",                true],  [["-1e2"],                               false], [null, false]],
            [-0,                                    [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["0",                   true],  [[-0],                                   false], [self::i("PT0S"), false]],
            ["-0",                                  [null,true], [false,true],  [0,                  true],  [-0.0,                     true],  ["-0",                  true],  [["-0"],                                 false], [null, false]],
            ["-000",                                [null,true], [false,true],  [0,                  true],  [-0.0,                     true],  ["-000",                true],  [["-000"],                               false], [null, false]],
            [-0.0,                                  [null,true], [false,true],  [0,                  true],  [-0.0,                     true],  ["-0",                  true],  [[-0.0],                                 false], [self::i("PT0S"), false]],
            ["-0.0",                                [null,true], [false,true],  [0,                  true],  [-0.0,                     true],  ["-0.0",                true],  [["-0.0"],                               false], [null, false]],
            ["-000.000",                            [null,true], [false,true],  [0,                  true],  [-0.0,                     true],  ["-000.000",            true],  [["-000.000"],                           false], [null, false]],
            [false,                                 [null,true], [false,true],  [0,                  false], [0.0,                      false], ["",                    false], [[false],                                false], [null, false]],
            [true,                                  [null,true], [true, true],  [1,                  false], [1.0,                      false], ["1",                   false], [[true],                                 false], [null, false]],
            ["on",                                  [null,true], [true, true],  [0,                  false], [0.0,                      false], ["on",                  true],  [["on"],                                 false], [null, false]],
            ["off",                                 [null,true], [false,true],  [0,                  false], [0.0,                      false], ["off",                 true],  [["off"],                                false], [null, false]],
            ["yes",                                 [null,true], [true, true],  [0,                  false], [0.0,                      false], ["yes",                 true],  [["yes"],                                false], [null, false]],
            ["no",                                  [null,true], [false,true],  [0,                  false], [0.0,                      false], ["no",                  true],  [["no"],                                 false], [null, false]],
            ["true",                                [null,true], [true, true],  [0,                  false], [0.0,                      false], ["true",                true],  [["true"],                               false], [null, false]],
            ["false",                               [null,true], [false,true],  [0,                  false], [0.0,                      false], ["false",               true],  [["false"],                              false], [null, false]],
            [INF,                                   [null,true], [true, false], [0,                  false], [INF,                      true],  ["INF",                 false], [[INF],                                  false], [null, false]],
            [-INF,                                  [null,true], [true, false], [0,                  false], [-INF,                     true],  ["-INF",                false], [[-INF],                                 false], [null, false]],
            [NAN,                                   [null,true], [false,false], [0,                  false], [NAN,                      true],  ["NAN",                 false], [[],                                     false], [null, false]],
            [[],                                    [null,true], [false,false], [0,                  false], [0.0,                      false], ["",                    false], [[],                                     true],  [null, false]],
            ["some string",                         [null,true], [true, false], [0,                  false], [0.0,                      false], ["some string",         true],  [["some string"],                        false], [null, false]],
            ["           ",                         [null,true], [true, false], [0,                  false], [0.0,                      false], ["           ",         true],  [["           "],                        false], [null, false]],
            [new \StdClass(),                         [null,true], [true, false], [0,                  false], [0.0,                      false], ["",                    false], [[new \StdClass()],                        false], [null, false]],
            [new StrClass(""),                      [null,true], [false,true],  [0,                  false], [0.0,                      false], ["",                    true],  [[new StrClass("")],                     false], [null, false]],
            [new StrClass("1"),                     [null,true], [true, true],  [1,                  true],  [1.0,                      true],  ["1",                   true],  [[new StrClass("1")],                    false], [null, false]],
            [new StrClass("0"),                     [null,true], [false,true],  [0,                  true],  [0.0,                      true],  ["0",                   true],  [[new StrClass("0")],                    false], [null, false]],
            [new StrClass("-1"),                    [null,true], [true, false], [-1,                 true],  [-1.0,                     true],  ["-1",                  true],  [[new StrClass("-1")],                   false], [null, false]],
            [new StrClass("Msg"),                   [null,true], [true, false], [0,                  false], [0.0,                      false], ["Msg",                 true],  [[new StrClass("Msg")],                  false], [null, false]],
            [new StrClass("   "),                   [null,true], [true, false], [0,                  false], [0.0,                      false], ["   ",                 true],  [[new StrClass("   ")],                  false], [null, false]],
            [2.5,                                   [null,true], [true, false], [2,                  false], [2.5,                      true],  ["2.5",                 true],  [[2.5],                                  false], [self::i("PT2S", 0.5), false]],
            [0.5,                                   [null,true], [true, false], [0,                  false], [0.5,                      true],  ["0.5",                 true],  [[0.5],                                  false], [self::i("PT0S", 0.5), false]],
            ["2.5",                                 [null,true], [true, false], [2,                  false], [2.5,                      true],  ["2.5",                 true],  [["2.5"],                                false], [null, false]],
            ["0.5",                                 [null,true], [true, false], [0,                  false], [0.5,                      true],  ["0.5",                 true],  [["0.5"],                                false], [null, false]],
            [self::d("2010-01-01T00:00:00", 0, 0), [null,true], [true, false], [1262304000,         false], [1262304000.0,             false], ["2010-01-01T00:00:00Z",true],  [[self::d("2010-01-01T00:00:00", 0, 0)],false], [null, false]],
            [self::d("2010-01-01T00:00:00", 0, 1), [null,true], [true, false], [1262304000,         false], [1262304000.0,             false], ["2010-01-01T00:00:00Z",true],  [[self::d("2010-01-01T00:00:00", 0, 1)],false], [null, false]],
            [self::d("2010-01-01T00:00:00", 1, 0), [null,true], [true, false], [1262322000,         false], [1262322000.0,             false], ["2010-01-01T05:00:00Z",true],  [[self::d("2010-01-01T00:00:00", 1, 0)],false], [null, false]],
            [self::d("2010-01-01T00:00:00", 1, 1), [null,true], [true, false], [1262322000,         false], [1262322000.0,             false], ["2010-01-01T05:00:00Z",true],  [[self::d("2010-01-01T00:00:00", 1, 1)],false], [null, false]],
            [1e14,                                  [null,true], [true, false], [10 ** 14,           true],  [1e14,                     true],  ["100000000000000",     true],  [[1e14],                                 false], [self::i("P1157407407DT9H46M40S"), false]],
            [1e-6,                                  [null,true], [true, false], [0,                  false], [1e-6,                     true],  ["0.000001",            true],  [[1e-6],                                 false], [self::i("PT0S", 1e-6), false]],
            [[1,2,3],                               [null,true], [true, false], [0,                  false], [0.0,                      false], ["",                    false], [[1,2,3],                                true],  [null, false]],
            [['a' => 1,'b' => 2],                   [null,true], [true, false], [0,                  false], [0.0,                      false], ["",                    false], [['a' => 1,'b' => 2],                    true],  [null, false]],
            [new Result([['a' => 1,'b' => 2]]),     [null,true], [true, false], [0,                  false], [0.0,                      false], ["",                    false], [[['a' => 1,'b' => 2]],                  true],  [null, false]],
            [self::i("PT1H"),                      [null,true], [true, false], [60 * 60,            false], [60.0 * 60.0,              false], ["PT1H",                true],  [[self::i("PT1H")],                     false], [self::i("PT1H"), true]],
            [self::i("P2DT1H"),                    [null,true], [true, false], [(48 + 1) * 60 * 60, false], [1.0 * (48 + 1) * 60 * 60, false], ["P2DT1H",              true],  [[self::i("P2DT1H")],                   false], [self::i("P2DT1H"), true]],
            [self::i("PT0H"),                      [null,true], [true, false], [0,                  false], [0.0,                      false], ["PT0S",                true],  [[self::i("PT0H")],                     false], [self::i("PT0H"), true]],
            [$dateDiff,                             [null,true], [true, false], [366 * 24 * 60 * 60, false], [1.0 * 366 * 24 * 60 * 60, false], ["P366D",               true],  [[$dateDiff],                            false], [$dateNorm, true]],
            ["1 year, 2 days",                      [null,true], [true, false], [0,                  false], [0.0,                      false], ["1 year, 2 days",      true],  [["1 year, 2 days"],                     false], [self::i("P1Y2D"), false]],
            ["P1Y2D",                               [null,true], [true, false], [0,                  false], [0.0,                      false], ["P1Y2D",               true],  [["P1Y2D"],                              false], [self::i("P1Y2D"), true]],
        ] as $set) {
            // shift the input value off the set
            $input = array_shift($set);
            // shift a mixed-type passthrough test onto the set
            array_unshift($set, [$input, true]);
            // generate a set of tests for each target data type
            foreach ($set as $type => [$exp, $pass]) {
                // emit one test each for loose mode, strict mode, drop mode, and strict+drop mode
                foreach ([
                    [false, false],
                    [true,  false],
                    [false, true],
                    [true,  true],
                ] as [$strict, $drop]) {
                    yield [$input, $types[$type], $exp, $pass, $strict, $drop];
                }
            }
        }
    }

    public static function provideDateNormalizationValues(): iterable {
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
        foreach ([
            /* Input value                          microtime                    iso8601                      iso8601m                     http                         sql                          date                         time                         unix                         float                        '!M j, Y (D)'                *strtotime* (null) */
            [null,                                  null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            [INF,                                   null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            [NAN,                                   null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            [self::d("2010-01-01T00:00:00", 0, 0),  self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),        self::t(1262304000),          self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000)],
            [self::d("2010-01-01T00:00:00", 0, 1),  self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),        self::t(1262304000),          self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000)],
            [self::d("2010-01-01T00:00:00", 1, 0),  self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000),        self::t(1262322000),          self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000)],
            [self::d("2010-01-01T00:00:00", 1, 1),  self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000),        self::t(1262322000),          self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000),         self::t(1262322000)],
            [1262304000,                            self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),        self::t(1262304000),          self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000),         self::t(1262304000)],
            [1262304000.123456,                     self::t(1262304000.123456),  self::t(1262304000.123456),  self::t(1262304000.123456),  self::t(1262304000.123456),  self::t(1262304000.123456), self::t(1262304000.123456),   self::t(1262304000.123456),  self::t(1262304000.123456),  self::t(1262304000.123456),  self::t(1262304000.123456),  self::t(1262304000.123456)],
            [1262304000.42,                         self::t(1262304000.42),      self::t(1262304000.42),      self::t(1262304000.42),      self::t(1262304000.42),      self::t(1262304000.42),     self::t(1262304000.42),       self::t(1262304000.42),      self::t(1262304000.42),      self::t(1262304000.42),      self::t(1262304000.42),      self::t(1262304000.42)],
            ["0.12345600 1262304000",               self::t(1262304000.123456),  null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            ["0.42 1262304000",                     null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            ["2010-01-01T00:00:00",                 null,                        self::t(1262304000),         self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01T00:00:00Z",                null,                        self::t(1262304000),         self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01T00:00:00+0000",            null,                        self::t(1262304000),         self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01T00:00:00-0000",            null,                        self::t(1262304000),         self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01T00:00:00+00:00",           null,                        self::t(1262304000),         self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01T00:00:00-05:00",           null,                        self::t(1262322000),         self::t(1262322000),         null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262322000)],
            ["2010-01-01T00:00:00.123456Z",         null,                        null,                        self::t(1262304000.123456),  null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000.123456)],
            ["Fri, 01 Jan 2010 00:00:00 GMT",       null,                        null,                        null,                        self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01 00:00:00",                 null,                        null,                        null,                        null,                        self::t(1262304000),         null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["2010-01-01",                          null,                        null,                        null,                        null,                        null,                        self::t(1262304000),         null,                        null,                        null,                        null,                        self::t(1262304000)],
            ["12:34:56",                            null,                        null,                        null,                        null,                        null,                        null,                        self::t(45296),              null,                        null,                        null,                        self::t(date_create("today", new \DateTimezone("UTC"))->getTimestamp() + 45296)],
            ["1262304000",                          null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000),         null,                        null,                        null],
            ["1262304000.123456",                   null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000.123456),  null,                        null],
            ["1262304000.42",                       null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000.42),      null,                        null],
            ["Jan 1, 2010 (Fri)",                   null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000),         null],
            ["First day of Jan 2010 12AM",          null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        self::t(1262304000)],
            [[],                                    null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            [self::i("P1Y2D"),                      null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
            ["P1Y2D",                               null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null,                        null],
        ] as $k => $set) {
            // shift the input value off the set
            $input = array_shift($set);
            // generate a set of tests for each target date formats
            foreach ($set as $format => $exp) {
                // emit one test each for loose mode, strict mode, drop mode, and strict+drop mode
                foreach ([
                    [false, false],
                    [true,  false],
                    [false, true],
                    [true,  true],
                ] as [$strict, $drop]) {
                    yield "Index #$k format \"$format\" strict:$strict drop:$drop" => [$input, $formats[$format], $exp, $strict, $drop];
                }
            }
        }
    }

    protected static function d($spec, $local, $immutable): \DateTimeInterface {
        $tz = $local ? new \DateTimeZone("America/Toronto") : new \DateTimeZone("UTC");
        if ($immutable) {
            return \DateTimeImmutable::createFromFormat("!Y-m-d\TH:i:s", $spec, $tz);
        } else {
            return \DateTime::createFromFormat("!Y-m-d\TH:i:s", $spec, $tz);
        }
    }

    protected static function t(float $spec): \DateTimeImmutable {
        return \DateTimeImmutable::createFromFormat("U.u", sprintf("%F", $spec), new \DateTimeZone("UTC"));
    }

    protected static function i(string $spec, float $msec = 0.0): \DateInterval {
        $out = new \DateInterval($spec);
        $out->f = $msec;
        return $out;
    }

    public function testFlattenArray(): void {
        $arr = [1, [2, 3, [4, 5]], 6, [[7, 8], 9, 10]];
        $exp = range(1, 10);
        $this->assertSame($exp, I::flatten($arr));
    }
}
