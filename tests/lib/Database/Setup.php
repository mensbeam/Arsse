<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Database;
use Phake;

trait Setup {
	protected $drv;
	protected $data = [
        'arsse_users' => [
            'columns' => [
                'id'       => 'str',
                'password' => 'str',
                'name'     => 'str',
                'rights'   => 'int',
            ],
            'rows' => [
                ["admin@example.net", '$2y$10$PbcG2ZR3Z8TuPzM7aHTF8.v61dtCjzjK78gdZJcp4UePE8T9jEgBW', "Hard Lip Herbert", UserDriver::RIGHTS_GLOBAL_ADMIN], // password is hash of "secret"
                ["jane.doe@example.com", "", "Jane Doe", UserDriver::RIGHTS_NONE],
                ["john.doe@example.com", "", "John Doe", UserDriver::RIGHTS_NONE],
            ],
        ],
    ];

    function setUp() {
        // establish a clean baseline
        $this->clearData();
        // create a default configuration
        Data::$conf = new Conf();
        // configure and create the relevant database driver
		$this->setUpDriver();
        // create the database interface with the suitable driver
        Data::$db = new Database($this->drv);
        Data::$db->schemaUpdate();
        // create a mock user manager
        Data::$user = Phake::mock(User::class);
        Phake::when(Data::$user)->authorize->thenReturn(true);
        // call the additional setup method if it exists
        if(method_exists($this, "setUpSeries")) $this->setUpSeries();
    }

	function tearDown() {
        // call the additional teardiwn method if it exists
        if(method_exists($this, "tearDownSeries")) $this->tearDownSeries();
        // clean up
		$this->drv = null;
        $this->clearData();
	}

	function primeDatabase(array $data): bool {
		$this->drv->begin();
		foreach($data as $table => $info) {
			$cols = implode(",", array_keys($info['columns']));
			$bindings = array_values($info['columns']);
			$params = implode(",", array_fill(0, sizeof($info['columns']), "?"));
			$s = $this->drv->prepareArray("INSERT INTO $table($cols) values($params)", $bindings);
			foreach($info['rows'] as $row) {
				$this->assertEquals(1, $s->runArray($row)->changes());
			}
		}
		$this->drv->commit();
		return true;
	}

	function compareExpectations(array $expected): bool {
		foreach($expected as $table => $info) {
			$cols = implode(",", array_keys($info['columns']));
			foreach($this->drv->prepare("SELECT $cols from $table")->run() as $num => $row) {
				$row = array_values($row);
				$this->assertGreaterThan(0, sizeof($info['rows']), "Expectations contain fewer rows than the database table $table");
				$exp = array_shift($info['rows']);
				$this->assertSame($exp, $row, "Row ".($num+1)." of table $table does not match expectations at array index $num.");
			}
		}
		return true;
	}

	function primeExpectations(array $source, array $tableSpecs = null): array {
		$out = [];
		foreach($tableSpecs as $table => $columns) {
			if(!isset($source[$table])) {
				$this->assertTrue(false, "Source for expectations does not contain requested table $table.");
				return [];
			}
			$out[$table] = [
				'columns' => [],
				'rows'    => [],
			];
			$transformations = [];
			foreach($columns as $target => $col) {
				if(!isset($source[$table]['columns'][$col])) {
					$this->assertTrue(false, "Source for expectations does not contain requested column $col of table $table."); 
					return [];
				}
				$found = array_search($col, array_keys($source[$table]['columns']));
				$transformations[$found] = $target;
				$out[$table]['columns'][$col] = $source[$table]['columns'][$col];
			}
			foreach($source[$table]['rows'] as $sourceRow) {
				$newRow = [];
				foreach($transformations as $from => $to) {
					$newRow[$to] = $sourceRow[$from];
				}
				$out[$table]['rows'][] = $newRow;
			}
		}
		return $out;
	}
}