<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync;


class TestDbStatementSQLite3 extends \PHPUnit\Framework\TestCase {
    use Test\Tools, Test\Db\BindingTests;

    protected $c;
	protected $s;
	static protected $imp = Db\StatementSQLite3::class;

    function setUp() {
		date_default_timezone_set("UTC");
        $c = new \SQLite3(":memory:");
        $c->enableExceptions(true);
		$s = $c->prepare("SELECT ? as value");
        $this->c = $c;
		$this->s = $s;
    }

    function tearDown() {
		try {$this->s->close();} catch(\Exception $e) {}
        $this->c->close();
		unset($this->s);
        unset($this->c);
    }

	function testConstructStatement() {
		$this->assertInstanceOf(Db\StatementSQLite3::class, new Db\StatementSQLite3($this->c, $this->s));
	}
}