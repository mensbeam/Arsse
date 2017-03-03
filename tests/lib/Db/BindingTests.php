<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Test\Db;
use JKingWeb\NewsSync\Db\Statement;

trait BindingTests {
	function testBindNull() {
		$input = null;
		$exp = [
			"null"      => null,
			"integer"   => null,
			"float"     => null,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => null,
			"string"    => null,
			"boolean"   => null,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindTrue() {
		$input = true;
		$exp = [
			"null"      => null,
			"integer"   => 1,
			"float"     => 1.0,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => "1",
			"string"    => "1",
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindFalse() {
		$input = false;
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => "",
			"string"    => "",
			"boolean"   => 0,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindInteger() {
		$input = 2112;
		$exp = [
			"null"      => null,
			"integer"   => 2112,
			"float"     => 2112.0,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), 2112),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), 2112),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), 2112),
			"binary"    => "2112",
			"string"    => "2112",
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindIntegerZero() {
		$input = 0;
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), 0),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), 0),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), 0),
			"binary"    => "0",
			"string"    => "0",
			"boolean"   => 0,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindFloat() {
		$input = 2112.0;
		$exp = [
			"null"      => null,
			"integer"   => 2112,
			"float"     => 2112.0,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), 2112),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), 2112),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), 2112),
			"binary"    => "2112",
			"string"    => "2112",
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindFloatZero() {
		$input = 0.0;
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), 0),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), 0),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), 0),
			"binary"    => "0",
			"string"    => "0",
			"boolean"   => 0,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindAsciiString() {
		$input = "Random string";
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => $input,
			"string"    => $input,
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindUtf8String() {
		$input = "é";
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => $input,
			"string"    => $input,
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindBinaryString() {
		// FIXME: This test may be unreliable; SQLite happily stores invalid UTF-8 text as bytes untouched, but other engines probably don't do this
		$input = chr(233);
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => null,
			"time"      => null,
			"datetime"  => null,
			"binary"    => $input,
			"string"    => $input,
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindIso8601DateString() {
		$input = "2017-01-09T13:11:17";
		$time = strtotime($input);
		$exp = [
			"null"      => null,
			"integer"   => 2017,
			"float"     => 2017.0,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), $time),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), $time),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), $time),
			"binary"    => $input,
			"string"    => $input,
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindArbitraryDateString() {
		$input = "Today";
		$time = strtotime($input);
		$exp = [
			"null"      => null,
			"integer"   => 0,
			"float"     => 0.0,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), $time),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), $time),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), $time),
			"binary"    => $input,
			"string"    => $input,
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindMutableDateObject($class = '\DateTime') {
		$input = new $class("Noon Today");
		$time = $input->getTimestamp();
		$exp = [
			"null"      => null,
			"integer"   => $time,
			"float"     => (float) $time,
			"date"      => date(self::$imp::dateFormat(Statement::TS_DATE), $time),
			"time"      => date(self::$imp::dateFormat(Statement::TS_TIME), $time),
			"datetime"  => date(self::$imp::dateFormat(Statement::TS_BOTH), $time),
			"binary"    => date(self::$imp::dateFormat(Statement::TS_BOTH), $time),
			"string"    => date(self::$imp::dateFormat(Statement::TS_BOTH), $time),
			"boolean"   => 1,
		];
		$this->checkBinding($input, $exp);
	}

	function testBindImmutableDateObject() {
		$this->testBindMutableDateObject('\DateTimeImmutable');
	}
}