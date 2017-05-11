<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Test\Database;
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
        'arsse_folders' => [
            'columns' => [
                'id'     => "int",
                'owner'  => "str",
                'parent' => "int",
                'name'   => "str",
            ],
            /* Layout translates to:
            Jane
                Politics
            John
                Technology
                    Software
                        Politics
                    Rocketry
                Politics
            */
            'rows' => [
                [1, "john.doe@example.com", null, "Technology"],
                [2, "john.doe@example.com",    1, "Software"],
                [3, "john.doe@example.com",    1, "Rocketry"],
                [4, "jane.doe@example.com", null, "Politics"],        
                [5, "john.doe@example.com", null, "Politics"],
                [6, "john.doe@example.com",    2, "Politics"],
            ]
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
        $tr = $this->drv->begin();
        foreach($data as $table => $info) {
            $cols = implode(",", array_keys($info['columns']));
            $bindings = array_values($info['columns']);
            $params = implode(",", array_fill(0, sizeof($info['columns']), "?"));
            $s = $this->drv->prepareArray("INSERT INTO $table($cols) values($params)", $bindings);
            foreach($info['rows'] as $row) {
                $this->assertEquals(1, $s->runArray($row)->changes());
            }
        }
        $tr->commit();
        return true;
    }

    function compareExpectations(array $expected): bool {
        foreach($expected as $table => $info) {
            $cols = implode(",", array_keys($info['columns']));
            $data = $this->drv->prepare("SELECT $cols from $table")->run()->getAll();
            $cols = array_keys($info['columns']);
            foreach($info['rows'] as $index => $values) {
                $row = [];
                foreach($values as $key => $value) {
                    $row[$cols[$key]] = $value;
                }
                $found = array_search($row, $data);
                $this->assertNotSame(false, $found, "Table $table does not contain record at array index $index.");
                unset($data[$found]);
            }
            $this->assertSame([], $data);
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