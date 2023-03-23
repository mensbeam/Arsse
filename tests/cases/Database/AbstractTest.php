<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;

abstract class AbstractTest extends \JKingWeb\Arsse\Test\AbstractTest {
    use SeriesMiscellany;
    use SeriesMeta;
    use SeriesUser;
    use SeriesSession;
    use SeriesToken;
    use SeriesFolder;
    use SeriesFeed;
    use SeriesIcon;
    use SeriesSubscription;
    use SeriesLabel;
    use SeriesTag;
    use SeriesArticle;
    use SeriesCleanup;

    /** @var \JKingWeb\Arsse\Db\Driver */
    protected static $drv;
    protected static $failureReason = "";
    protected $primed = false;
    protected $data;
    protected $user;
    protected $checkTables;
    protected $series;

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

    public static function setUpBeforeClass(): void {
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

    public function setUp(): void {
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
        $this->userMock = $this->mock(User::class);
        Arsse::$user = $this->userMock->get();
        // call the series-specific setup method
        $setUp = "setUp".$this->series;
        $this->$setUp();
        // prime the database with series data if it hasn't already been done
        if (!$this->primed && isset($this->data)) {
            $this->primeDatabase(static::$drv, $this->data);
        }
    }

    public function tearDown(): void {
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

    public static function tearDownAfterClass(): void {
        if (static::$drv) {
            // wipe the database absolutely clean
            static::dbRaze(static::$drv);
            // clean up
            static::$drv = null;
        }
        static::$failureReason = "";
        static::clearData();
    }
}
