<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Misc;

use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Misc\ValueInfo;

/** @covers \JKingWeb\Arsse\Context\Context<extended> */
class TestContext extends \JKingWeb\Arsse\Test\AbstractTest {
    public function testVerifyInitialState() {
        $c = new Context;
        foreach ((new \ReflectionObject($c))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isStatic() || strpos($m->name, "__") === 0) {
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
            'folders' => [12,22],
            'folderShallow' => 42,
            'foldersShallow' => [0,1],
            'tag' => 44,
            'tags' => [44, 2112],
            'tagName' => "XLIV",
            'tagNames' => ["XLIV", "MMCXII"],
            'subscription' => 2112,
            'subscriptions' => [44, 2112],
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
            'labels' => [2112, 1984],
            'labelName' => "Rush",
            'labelNames' => ["Rush", "Orwell"],
            'labelled' => true,
            'annotated' => true,
            'searchTerms' => ["foo", "bar"],
            'annotationTerms' => ["foo", "bar"],
            'titleTerms' => ["foo", "bar"],
            'authorTerms' => ["foo", "bar"],
            'not' => (new Context)->subscription(5),
        ];
        $times = ['modifiedSince','notModifiedSince','markedSince','notMarkedSince'];
        $c = new Context;
        foreach ((new \ReflectionObject($c))->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isStatic() || strpos($m->name, "__") === 0) {
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
            // clear the context option
            $c->$method(null);
            $this->assertFalse($c->$method());
        }
    }

    public function testCleanIdArrayValues() {
        $methods = ["articles", "editions", "tags", "labels", "subscriptions"];
        $in = [1, "2", 3.5, 4.0, 4, "ook", 0, -20, true, false, null, new \DateTime(), -1.0];
        $out = [1, 2, 4];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCleanFolderIdArrayValues() {
        $methods = ["folders", "foldersShallow"];
        $in = [1, "2", 3.5, 4.0, 4, "ook", 0, -20, true, false, null, new \DateTime(), -1.0];
        $out = [1, 2, 4, 0];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCleanStringArrayValues() {
        $methods = ["searchTerms", "annotationTerms", "titleTerms", "authorTerms", "tagNames", "labelNames"];
        $now = new \DateTime;
        $in = [1, 3.0, "ook", 0, true, false, null, $now, ""];
        $out = ["1", "3", "ook", "0", valueInfo::normalize($now, ValueInfo::T_STRING)];
        $c = new Context;
        foreach ($methods as $method) {
            $this->assertSame($out, $c->$method($in)->$method, "Context method $method did not return the expected results");
        }
    }

    public function testCloneAContext() {
        $c1 = new Context;
        $c2 = clone $c1;
        $this->assertEquals($c1, $c2);
        $this->assertEquals($c1->not, $c2->not);
        $this->assertNotSame($c1, $c2);
        $this->assertNotSame($c1->not, $c2->not);
        $this->assertSame($c1, $c1->not->article(null));
        $this->assertSame($c2, $c2->not->article(null));
    }
}
