<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\Date;

/** @covers \JKingWeb\Arsse\Misc\Date */
class TestDate extends \JKingWeb\Arsse\Test\AbstractTest {
    public function setUp() {
        $this->clearData();
    }

    public function testNormalizeADate() {
        $exp = new \DateTimeImmutable("2018-01-01T00:00:00Z");
        $this->assertEquals($exp, Date::normalize(1514764800));
        $this->assertEquals($exp, Date::normalize("2018-01-01T00:00:00"));
        $this->assertEquals($exp, Date::normalize("2018-01-01 00:00:00"));
        $this->assertEquals($exp, Date::normalize("Mon, 01 Jan 2018 00:00:00 GMT", "http"));
        $this->assertEquals($exp, Date::normalize(new \DateTime("2017-12-31 19:00:00-0500")));
        $this->assertNull(Date::normalize(null));
        $this->assertNull(Date::normalize("ook"));
        $this->assertNull(Date::normalize("2018-01-01T00:00:00Z", "http"));
    }

    public function testFormatADate() {
        $test = new \DateTimeImmutable("2018-01-01T00:00:00Z");
        $this->assertNull(Date::transform(null, "http"));
        $this->assertNull(Date::transform("ook", "http"));
        $this->assertNull(Date::transform("2018-01-01T00:00:00Z", "iso8601", "http"));
        $this->assertSame("2018-01-01T00:00:00Z", Date::transform($test));
        $this->assertSame("2018-01-01T00:00:00Z", Date::transform($test, "iso8601"));
        $this->assertSame("Mon, 01 Jan 2018 00:00:00 GMT", Date::transform($test, "http"));
        $this->assertSame(1514764800, Date::transform($test, "unix"));
        $this->assertSame(1514764800.0, Date::transform($test, "float"));
        $this->assertSame(1514764800.265579, Date::transform("0.26557900 1514764800", "float", "microtime"));
        $this->assertSame(1514764800.265579, Date::transform("2018-01-01T00:00:00.265579Z", "float", "iso8601m"));
    }

    public function testMoveDateForward() {
        $test = new \DateTimeImmutable("2018-01-01T00:00:00Z");
        $this->assertNull(Date::add("P1D", null));
        $this->assertNull(Date::add("P1D", "ook"));
        $this->assertEquals($test->add(new \DateInterval("P1D")), Date::add("P1D", $test));
        $this->assertException();
        $this->assertNull(Date::add("ook", $test));
    }

    public function testMoveDateBack() {
        $test = new \DateTimeImmutable("2018-01-01T00:00:00Z");
        $this->assertNull(Date::sub("P1D", null));
        $this->assertNull(Date::sub("P1D", "ook"));
        $this->assertEquals($test->sub(new \DateInterval("P1D")), Date::sub("P1D", $test));
        $this->assertException();
        $this->assertNull(Date::sub("ook", $test));
    }
}
