<?php

/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Test\Database;
use JKingWeb\Arsse\Arsse;

trait SeriesMeta {
    protected function setUpSeriesMeta(): void {
        $dataBare = [
            'arsse_meta' => [
                'columns' => [
                    'key'   => 'str',
                    'value' => 'str',
                ],
                'rows' => [
                //['schema_version', "".\JKingWeb\Arsse\Database::SCHEMA_VERSION],
                ['album',"A Farewell to Kings"],
                ],
            ],
        ];
        // the schema_version key is a special case, and to avoid jumping through hoops for every test we deal with it now
        $this->data = $dataBare;
        // as far as tests are concerned the schema version is part of the expectations primed into the database
        array_unshift($this->data['arsse_meta']['rows'], ['schema_version', "".Database::SCHEMA_VERSION]);
        // but it's already been inserted by the driver, so we prime without it
        $this->primeDatabase(static::$drv, $dataBare);
    }

    protected function tearDownSeriesMeta(): void {
        unset($this->data);
    }

    /** @covers \JKingWeb\Arsse\Database::metaSet */
    public function testAddANewValue(): void {
        $this->assertTrue(Arsse::$db->metaSet("favourite", "Cygnus X-1"));
        $state = $this->primeExpectations($this->data, ['arsse_meta' => ['key','value']]);
        $state['arsse_meta']['rows'][] = ["favourite","Cygnus X-1"];
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::metaSet */
    public function testAddANewTypedValue(): void {
        $this->assertTrue(Arsse::$db->metaSet("answer", 42, "int"));
        $this->assertTrue(Arsse::$db->metaSet("true", true, "bool"));
        $this->assertTrue(Arsse::$db->metaSet("false", false, "bool"));
        $this->assertTrue(Arsse::$db->metaSet("millennium", new \DateTime("2000-01-01T00:00:00Z"), "datetime"));
        $state = $this->primeExpectations($this->data, ['arsse_meta' => ['key','value']]);
        $state['arsse_meta']['rows'][] = ["answer","42"];
        $state['arsse_meta']['rows'][] = ["true","1"];
        $state['arsse_meta']['rows'][] = ["false","0"];
        $state['arsse_meta']['rows'][] = ["millennium","2000-01-01 00:00:00"];
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::metaSet */
    public function testChangeAnExistingValue(): void {
        $this->assertTrue(Arsse::$db->metaSet("album", "Hemispheres"));
        $state = $this->primeExpectations($this->data, ['arsse_meta' => ['key','value']]);
        $state['arsse_meta']['rows'][1][1] = "Hemispheres";
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::metaRemove */
    public function testRemoveAValue(): void {
        $this->assertTrue(Arsse::$db->metaRemove("album"));
        $this->assertFalse(Arsse::$db->metaRemove("album"));
        $state = $this->primeExpectations($this->data, ['arsse_meta' => ['key','value']]);
        unset($state['arsse_meta']['rows'][1]);
        $this->compareExpectations(static::$drv, $state);
    }

    /** @covers \JKingWeb\Arsse\Database::metaGet */
    public function testRetrieveAValue(): void {
        $this->assertSame("".Database::SCHEMA_VERSION, Arsse::$db->metaGet("schema_version"));
        $this->assertSame("A Farewell to Kings", Arsse::$db->metaGet("album"));
        $this->assertSame(null, Arsse::$db->metaGet("this_key_does_not_exist"));
    }
}
