<?php
declare(strict_types=1);
namespace JKingWeb\Arsse;
use JKingWeb\Arsse\Misc\Context;


class TestContext extends \PHPUnit\Framework\TestCase {
    use Test\Tools;

    function testVerifyInitialState() {
        $c = new Context;
        foreach((new \ReflectionObject($c))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if($m->isConstructor() || $m->isStatic()) continue;
            $method = $m->name;
            $this->assertFalse($c->$method(), "Context method $method did not initially return false");
            $this->assertEquals(null, $c->$method, "Context property $method is not initially falsy");
        }
    }

    function testSetContextOptions() {
        $v = [
            'reverse' => true,
            'limit' => 10,
            'offset' => 5,
            'folder' => 42,
            'subscription' => 2112,
            'article' => 255,
            'edition' => 65535,
            'latestEdition' => 47,
            'oldestEdition' => 1337,
            'unread' => true,
            'starred' => true,
            'modifiedSince' => new \DateTime(),
            'notModifiedSince' => new \DateTime(),
        ];
        $times = ['modifiedSince','notModifiedSince'];
        $c = new Context;
        foreach((new \ReflectionObject($c))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if($m->isConstructor() || $m->isStatic()) continue;
            $method = $m->name;
            $this->assertArrayHasKey($method, $v, "Context method $method not included in test");
            $this->assertInstanceOf(Context::class, $c->$method($v[$method]));
            $this->assertTrue($c->$method());
            if(in_array($method, $times)) {
                $this->assertTime($c->$method, $v[$method]);
            } else {
                $this->assertSame($c->$method, $v[$method]);
            }
        }
    }
}