<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Misc\Context;

/** @covers \JKingWeb\Arsse\Misc\Context */
class TestContext extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testVerifyInitialState() {
        $c = new Context;
        foreach ((new \ReflectionObject($c))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isConstructor() || $m->isStatic()) {
                continue;
            }
            $method = $m->name;
            $this->assertFalse($c->$method(), "Context method $method did not initially return false");
            $this->assertEquals(null, $c->$method, "Context property $method is not initially falsy");
        }
    }

    public function testSetContextOptions() {
        $v = [
            'reverse' => true,
            'limit' => 10,
            'offset' => 5,
            'folder' => 42,
            'folderShallow' => 42,
            'subscription' => 2112,
            'article' => 255,
            'edition' => 65535,
            'latestArticle' => 47,
            'oldestArticle' => 1337,
            'latestEdition' => 47,
            'oldestEdition' => 1337,
            'unread' => true,
            'starred' => true,
            'modifiedSince' => new \DateTime(),
            'notModifiedSince' => new \DateTime(),
            'markedSince' => new \DateTime(),
            'notMarkedSince' => new \DateTime(),
            'editions' => [1,2],
            'articles' => [1,2],
            'label' => 2112,
            'labelName' => "Rush",
            'labelled' => true,
            'annotated' => true,
        ];
        $times = ['modifiedSince','notModifiedSince','markedSince','notMarkedSince'];
        $c = new Context;
        foreach ((new \ReflectionObject($c))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isConstructor() || $m->isStatic()) {
                continue;
            }
            $method = $m->name;
            $this->assertArrayHasKey($method, $v, "Context method $method not included in test");
            $this->assertInstanceOf(Context::class, $c->$method($v[$method]));
            $this->assertTrue($c->$method());
            if (in_array($method, $times)) {
                $this->assertTime($c->$method, $v[$method], "Context method $method did not return the expected results");
            } else {
                $this->assertSame($c->$method, $v[$method], "Context method $method did not return the expected results");
            }
        }
    }

    public function testCleanArrayValues() {
        $methods = ["articles", "editions"];
        $in = [1, "2", 3.5, 3.0, "ook", 0, -20, true, false, null, new \DateTime(), -1.0];
        $out = [1,2, 3];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }
}
