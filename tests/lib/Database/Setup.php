<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\User\Driver as UserDriver;
use JKingWeb\Arsse\Data;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Db\Result;
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
            foreach($info['rows'] as $index => $row) {
                $this->assertCount(sizeof($cols), $row, "The number of values for array index $index does not match the number of fields");
                $row = array_combine($cols, $row);
                $this->assertContains($row, $data, "Table $table does not contain record at array index $index.");
                $found = array_search($row, $data, true);
                unset($data[$found]);
            }
            $this->assertSame([], $data);
        }
        return true;
    }

    function primeExpectations(array $source, array $tableSpecs = null): array {
        $out = [];
        foreach($tableSpecs as $table => $columns) {
            // make sure the source has the table we want
            $this->assertArrayHasKey($table, $source, "Source for expectations does not contain requested table $table.");
            $out[$table] = [
                'columns' => [],
                'rows'    => array_fill(0,sizeof($source[$table]['rows']), []),
            ];
            // make sure the source has all the columns we want for the table
            $cols = array_flip($columns);
            $cols = array_intersect_key($cols, $source[$table]['columns']);
            $this->assertSame(array_keys($cols), $columns, "Source for table $table does not contain all requested columns");
            // get a map of source value offsets and keys
            $targets = array_flip(array_keys($source[$table]['columns']));
            foreach($cols as $key => $order) {
                // fill the column-spec
                $out[$table]['columns'][$key] = $source[$table]['columns'][$key];
                foreach($source[$table]['rows'] as $index => $row) {
                    // fill each row column-wise with re-ordered values
                    $out[$table]['rows'][$index][$order] = $row[$targets[$key]];
                }
            }
        }
        return $out;
    }

    function assertResult(array $expected, Result $data) {
        $data = $data->getAll();
        $this->assertCount(sizeof($expected), $data, "Number of result rows (".sizeof($data).") differs from number of expected rows (".sizeof($expected).")");
        if(sizeof($expected)) {
            // make sure the expectations are consistent
            foreach($expected as $exp) {
                if(!isset($keys)) {
                    $keys = $exp;
                    continue;
                }
                $this->assertSame(array_keys($keys), array_keys($exp), "Result set expectations are irregular");
            }
            // filter the result set to contain just the desired keys (we don't care if the result has extra keys)
            $rows = [];
            foreach($data as $row) {
                $rows[] = array_intersect_key($row, $keys);
            }
            // compare the result set to the expectations
            foreach($expected as $index => $exp) {
                $this->assertContains($exp, $rows, "Result set does not contain record at array index $index.");
                $found = array_search($exp, $rows, true);
                unset($rows[$found]);
            }
        }
    }
}