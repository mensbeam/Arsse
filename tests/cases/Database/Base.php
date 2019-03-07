<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Conf;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\Db\Result;
use JKingWeb\Arsse\Test\DatabaseInformation;
use Phake;

abstract class Base extends \JKingWeb\Arsse\Test\AbstractTest {
    use SeriesMiscellany;
    use SeriesMeta;
    use SeriesUser;
    use SeriesSession;
    use SeriesFolder;
    use SeriesFeed;
    use SeriesSubscription;
    use SeriesLabel;
    use SeriesTag;
    use SeriesArticle;
    use SeriesCleanup;

    /** @var \JKingWeb\Arsse\Db\Driver */
    protected static $drv;
    protected static $failureReason = "";
    protected $primed = false;

    abstract protected function nextID(string $table): int;

    protected function findTraitOfTest(string $test): string {
        $class = new \ReflectionClass(self::class);
        foreach ($class->getTraits() as $trait) {
            if ($trait->hasMethod($test)) {
                return $trait->getShortName();
            }
        }
        return $class->getShortName();
    }

    public static function setUpBeforeClass() {
        // establish a clean baseline
        static::clearData();
        // perform an initial connection to the database to reset its version to zero
        // in the case of SQLite this will always be the case (we use a memory database),
        // but other engines should clean up from potentially interrupted prior tests
        static::setConf();
        try {
            static::$drv = new static::$dbDriverClass;
        } catch (\JKingWeb\Arsse\Db\Exception $e) {
            static::$failureReason = $e->getMessage();
            return;
        }
        // wipe the database absolutely clean
        static::dbRaze(static::$drv);
        // create the database interface with the suitable driver and apply the latest schema
        Arsse::$db = new Database(static::$drv);
        Arsse::$db->driverSchemaUpdate();
    }

    public function setUp() {
        // get the name of the test's test series
        $this->series = $this->findTraitofTest($this->getName(false));
        static::clearData();
        static::setConf();
        if (strlen(static::$failureReason)) {
            $this->markTestSkipped(static::$failureReason);
        }
        Arsse::$db = new Database(static::$drv);
        Arsse::$db->driverSchemaUpdate();
        // create a mock user manager
        Arsse::$user = Phake::mock(User::class);
        Phake::when(Arsse::$user)->authorize->thenReturn(true);
        // call the series-specific setup method
        $setUp = "setUp".$this->series;
        $this->$setUp();
        // prime the database with series data if it hasn't already been done
        if (!$this->primed && isset($this->data)) {
            $this->primeDatabase($this->data);
        }
    }

    public function tearDown() {
        // call the series-specific teardown method
        $this->series = $this->findTraitofTest($this->getName(false));
        $tearDown = "tearDown".$this->series;
        $this->$tearDown();
        // clean up
        $this->primed = false;
        // call the database-specific table cleanup function
        static::dbTruncate(static::$drv);
        // clear state
        static::clearData();
    }

    public static function tearDownAfterClass() {
        // wipe the database absolutely clean
        static::dbRaze(static::$drv);
        // clean up
        static::$drv = null;
        static::$failureReason = "";
        static::clearData();
    }

    public function primeDatabase(array $data): bool {
        $drv = static::$drv;
        $tr = $drv->begin();
        foreach ($data as $table => $info) {
            $cols = array_map(function($v) {
                return '"'.str_replace('"', '""', $v).'"';
            }, array_keys($info['columns']));
            $cols = implode(",", $cols);
            $bindings = array_values($info['columns']);
            $params = implode(",", array_fill(0, sizeof($info['columns']), "?"));
            $s = $drv->prepareArray("INSERT INTO $table($cols) values($params)", $bindings);
            foreach ($info['rows'] as $row) {
                $s->runArray($row);
            }
        }
        $tr->commit();
        $this->primed = true;
        return true;
    }

    public function compareExpectations(array $expected): bool {
        foreach ($expected as $table => $info) {
            $cols = array_map(function($v) {
                return '"'.str_replace('"', '""', $v).'"';
            }, array_keys($info['columns']));
            $cols = implode(",", $cols);
            $types = $info['columns'];
            $data = static::$drv->prepare("SELECT $cols from $table")->run()->getAll();
            $cols = array_keys($info['columns']);
            foreach ($info['rows'] as $index => $row) {
                $this->assertCount(sizeof($cols), $row, "The number of values for array index $index does not match the number of fields");
                $row = array_combine($cols, $row);
                foreach ($data as $index => $test) {
                    foreach ($test as $col => $value) {
                        switch ($types[$col]) {
                            case "datetime":
                                $test[$col] = $this->approximateTime($row[$col], $value);
                                break;
                            case "int":
                                $test[$col] = ValueInfo::normalize($value, ValueInfo::T_INT | ValueInfo::M_DROP | valueInfo::M_NULL);
                                break;
                            case "float":
                                $test[$col] = ValueInfo::normalize($value, ValueInfo::T_FLOAT | ValueInfo::M_DROP | valueInfo::M_NULL);
                                break;
                            case "bool":
                                $test[$col] = (int) ValueInfo::normalize($value, ValueInfo::T_BOOL | ValueInfo::M_DROP | valueInfo::M_NULL);
                                break;
                        }
                    }
                    if ($row===$test) {
                        $data[$index] = $test;
                        break;
                    }
                }
                $this->assertContains($row, $data, "Table $table does not contain record at array index $index.");
                $found = array_search($row, $data, true);
                unset($data[$found]);
            }
            $this->assertSame([], $data);
        }
        return true;
    }

    public function primeExpectations(array $source, array $tableSpecs = null): array {
        $out = [];
        foreach ($tableSpecs as $table => $columns) {
            // make sure the source has the table we want
            $this->assertArrayHasKey($table, $source, "Source for expectations does not contain requested table $table.");
            $out[$table] = [
                'columns' => [],
                'rows'    => array_fill(0, sizeof($source[$table]['rows']), []),
            ];
            // make sure the source has all the columns we want for the table
            $cols = array_flip($columns);
            $cols = array_intersect_key($cols, $source[$table]['columns']);
            $this->assertSame(array_keys($cols), $columns, "Source for table $table does not contain all requested columns");
            // get a map of source value offsets and keys
            $targets = array_flip(array_keys($source[$table]['columns']));
            foreach ($cols as $key => $order) {
                // fill the column-spec
                $out[$table]['columns'][$key] = $source[$table]['columns'][$key];
                foreach ($source[$table]['rows'] as $index => $row) {
                    // fill each row column-wise with re-ordered values
                    $out[$table]['rows'][$index][$order] = $row[$targets[$key]];
                }
            }
        }
        return $out;
    }

    public function assertResult(array $expected, Result $data) {
        $data = $data->getAll();
        $this->assertCount(sizeof($expected), $data, "Number of result rows (".sizeof($data).") differs from number of expected rows (".sizeof($expected).")");
        if (sizeof($expected)) {
            // make sure the expectations are consistent
            foreach ($expected as $exp) {
                if (!isset($keys)) {
                    $keys = $exp;
                    continue;
                }
                $this->assertSame(array_keys($keys), array_keys($exp), "Result set expectations are irregular");
            }
            // filter the result set to contain just the desired keys (we don't care if the result has extra keys)
            $rows = [];
            foreach ($data as $row) {
                $rows[] = array_intersect_key($row, $keys);
            }
            // compare the result set to the expectations
            foreach ($rows as $row) {
                $this->assertContains($row, $expected, "Result set contains unexpected record.");
                $found = array_search($row, $expected);
                unset($expected[$found]);
            }
            $this->assertArraySubset($expected, [], false, "Expectations not in result set.");
        }
    }
}
