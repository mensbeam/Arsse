<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\REST\Reader;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Reader\FreshRSS\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Reader::class)]
#[CoversClass(\JKingWeb\Arsse\REST\Reader\Exception::class)]
class TestReaderFreshRSS extends TestReader {
    public function setUp(): void {
        $this->setUpTest(Reader::class);
    }

    protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface {
        if (strlen((string) $user)) {
            Arsse::$user->id = $user;
        }
        return $this->h->dispatch($this->serverRequest($method, "/api/greader.php/reader/api/0".$target, "/api/greader.php/reader/api/0", ['Accept' => "application/json"], [], $data, "application/x-www-form-urlencoded", [], $user));
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function testIssuePostTokens(bool $existing): void {
        $user = "john.doe@example.com";
        $token = "ivSJ+XWZMktLeqgRbw+D0gTAhXlZCUv/FT6VVMfH2iENJP8yfpXK3pGiZ";
        $bogus = "ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ";
        \Phake::when(Arsse::$db)->tokenList(\Phake::anyParameters())->thenReturn(new Result($existing ? [['id' => $token]] : []));
        \Phake::when(Arsse::$db)->tokenCreate(\Phake::anyParameters())->thenReturn($bogus);
        $act = $this->req("GET", "/token", "", $user);
        \Phake::verify(Arsse::$db)->tokenList($user, "reader.post");
        if ($existing) {
            \Phake::verify(Arsse::$db, \Phake::never())->tokenCreate(\Phake::anyParameters());
            $exp = HTTP::respText("$token\n");
        } else {
            $random = "";
            \Phake::verify(Arsse::$db)->tokenCreate($user, "reader.post", \Phake::capture($random));
            $exp = HTTP::respText("$random\n");
            $this->assertNotEquals($token, $random);
            $this->assertNotEquals($bogus, $random);
            $this->assertSame(57, strlen($random));
        }
        $this->assertMessage($exp, $act);
    }

    public static function provideTokenChecks(): iterable {
        self::clearData(); // initializes string formatter
        $success = HTTP::respText("OK");
        $failure = self::respError("401", 401, ['X-Reader-Google-Bad-Token' => "true"]);
        $routes = ["/disable-tag", "/edit-tag", "/mark-all-as-read", "/rename-tag", "/subscription/edit", "/subscription/quickadd"];
        foreach ($routes as $target) {
            yield [Reader::class, $target, "T=12345",   $success];
            yield [Reader::class, $target, "T=12345\n", $success];
            yield [Reader::class, $target, "",          $success];
            yield [Reader::class, $target, "T=",        $success];
            yield [Reader::class, $target, "T=x",       $success];
            yield [Reader::class, $target, "T=56789",   $failure];
            yield [Reader::class, $target, "T=\n",      $failure];
            yield [Reader::class, $target, "T=x\n",     $failure];
            yield [Reader::class, $target, "T=56789\n", $failure];
        }
    }

    public function testGetUserInfo(): void {
        $user = "john.doe@example.com";
        $act = $this->req("GET", "/user-info", "", $user);
        $exp = HTTP::respJson([
            'userId' => "john.doe@example.com",
            'userName' => "john.doe@example.com",
            'userProfileId' => "john.doe@example.com",
            'userEmail' => "",
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesAsXml(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-02 00:00:00", 'subscription' => 1,  'subscription_url' => "http://example.com/", 'subscription_title' => "Sub 1",  'unread' => 1, 'starred' => 0, 'author' => "John Doe", 'title' => "Edition 65", 'url' => "http://example.com/65", 'content' => "Content 65", 'media_url' => null,                       'media_type' => null],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00", 'published_date' => "2000-01-04 00:00:00", 'edited_date' => "2000-01-04 00:00:00", 'subscription' => 12, 'subscription_url' => "http://example.org/", 'subscription_title' => "Sub 12", 'unread' => 0, 'starred' => 1, 'author' => null,       'title' => "Edition 32", 'url' => "http://example.com/32", 'content' => "Content 32", 'media_url' => "http://example.com/audio", 'media_type' => "audio/vorbis"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook",  'subscription' => 1],
            ['name' => "Dupe", 'subscription' => 12],
        ]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 1, true)->thenReturn(["Foo", "Bar"]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 11, true)->thenReturn(["Dupe"]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($user, 1)->thenReturn(["Alfa", "Bravo"]);
        $act = $this->req("GET", "/stream/contents/?output=xml", "", $user);
        $exp = HTTP::respJson([
            'id' => "user/-/state/com.google/reading-list",
            'updated' => Date::transform(self::NOW, "unix"),
            'items' => [
                [
                    'id' => "tag:google.com,2005:reader/item/0000000000000001",
                    'crawlTimeMsec' => Date::transform($articles[0]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[0]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[0]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[0]['edited_date'], "unix"),
                    'title'         => $articles[0]['title'],
                    'canonical'     => [['href' => $articles[0]['url']]],
                    'alternate'     => [['href' => $articles[0]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/unread",
                        "user/-/state/com.google/kept-unread",
                        "user/-/label/Ook",
                        "user/-/label/Foo",
                        "user/-/label/Bar",
                        "Alfa",
                        "Bravo",
                    ],
                    'origin'       => [
                        'streamId' => "feed/1",
                        'htmlUrl'  => $articles[0]['subscription_url'],
                        'title'    => $articles[0]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[0]['content']],
                    'enclosure'    => [],
                    'author'       => $articles[0]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
                [
                    'id' => "tag:google.com,2005:reader/item/000000000000000b",
                    'crawlTimeMsec' => Date::transform($articles[1]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[1]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[1]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[1]['edited_date'], "unix"),
                    'title'         => $articles[1]['title'],
                    'canonical'     => [['href' => $articles[1]['url']]],
                    'alternate'     => [['href' => $articles[1]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/read",
                        "user/-/state/com.google/starred",
                        "user/-/label/Dupe",
                    ],
                    'origin'       => [
                        'streamId' => "feed/12",
                        'htmlUrl'  => $articles[1]['subscription_url'],
                        'title'    => $articles[1]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[1]['content']],
                    'enclosure'    => [['href' => $articles[1]['media_url'], 'type' => $articles[1]['media_type']]],
                    'author'       => $articles[1]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListArticlesAsAtom(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00", 'published_date' => "2000-01-01 00:00:00", 'edited_date' => "2000-01-02 00:00:00", 'subscription' => 1,  'subscription_url' => "http://example.com/", 'subscription_title' => "Sub 1",  'unread' => 1, 'starred' => 0, 'author' => "John Doe", 'title' => "Edition 65", 'url' => "http://example.com/65", 'content' => "Content 65", 'media_url' => null,                       'media_type' => null],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00", 'published_date' => "2000-01-04 00:00:00", 'edited_date' => "2000-01-04 00:00:00", 'subscription' => 12, 'subscription_url' => "http://example.org/", 'subscription_title' => "Sub 12", 'unread' => 0, 'starred' => 1, 'author' => null,       'title' => "Edition 32", 'url' => "http://example.com/32", 'content' => "Content 32", 'media_url' => "http://example.com/audio", 'media_type' => "audio/vorbis"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        \Phake::when(Arsse::$db)->tagSummarize(\Phake::anyParameters())->thenReturn(new Result([
            ['name' => "Ook",  'subscription' => 1],
            ['name' => "Dupe", 'subscription' => 12],
        ]));
        \Phake::when(Arsse::$db)->articleLabelsGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 1, true)->thenReturn(["Foo", "Bar"]);
        \Phake::when(Arsse::$db)->articleLabelsGet($user, 11, true)->thenReturn(["Dupe"]);
        \Phake::when(Arsse::$db)->articleCategoriesGet(\Phake::anyParameters())->thenReturn([]);
        \Phake::when(Arsse::$db)->articleCategoriesGet($user, 1)->thenReturn(["Alfa", "Bravo"]);
        $act = $this->req("GET", "/stream/contents/?output=atom", "", $user);
        $exp = HTTP::respJson([
            'id' => "user/-/state/com.google/reading-list",
            'updated' => Date::transform(self::NOW, "unix"),
            'items' => [
                [
                    'id' => "tag:google.com,2005:reader/item/0000000000000001",
                    'crawlTimeMsec' => Date::transform($articles[0]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[0]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[0]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[0]['edited_date'], "unix"),
                    'title'         => $articles[0]['title'],
                    'canonical'     => [['href' => $articles[0]['url']]],
                    'alternate'     => [['href' => $articles[0]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/unread",
                        "user/-/state/com.google/kept-unread",
                        "user/-/label/Ook",
                        "user/-/label/Foo",
                        "user/-/label/Bar",
                        "Alfa",
                        "Bravo",
                    ],
                    'origin'       => [
                        'streamId' => "feed/1",
                        'htmlUrl'  => $articles[0]['subscription_url'],
                        'title'    => $articles[0]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[0]['content']],
                    'enclosure'    => [],
                    'author'       => $articles[0]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
                [
                    'id' => "tag:google.com,2005:reader/item/000000000000000b",
                    'crawlTimeMsec' => Date::transform($articles[1]['modified_date'], "unix")."000",
                    'timestampUsec' => Date::transform($articles[1]['modified_date'], "unix")."000000",
                    'published'     => Date::transform($articles[1]['published_date'], "unix"),
                    'updated'       => Date::transform($articles[1]['edited_date'], "unix"),
                    'title'         => $articles[1]['title'],
                    'canonical'     => [['href' => $articles[1]['url']]],
                    'alternate'     => [['href' => $articles[1]['url'], 'type' => "text/html"]],
                    'categories'    => [
                        "user/-/state/com.google/reading-list",
                        "user/-/state/org.freshrss/main",
                        "user/-/state/com.google/read",
                        "user/-/state/com.google/starred",
                        "user/-/label/Dupe",
                    ],
                    'origin'       => [
                        'streamId' => "feed/12",
                        'htmlUrl'  => $articles[1]['subscription_url'],
                        'title'    => $articles[1]['subscription_title'],
                    ],
                    'summary'      => ['content' => $articles[1]['content']],
                    'enclosure'    => [['href' => $articles[1]['media_url'], 'type' => $articles[1]['media_type']]],
                    'author'       => $articles[1]['author'],
                    'linkingUsers'  => [],
                    'comments'      => [],
                    'commentsNum'   => -1,
                    'annotations'   => [],
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
    }

    public function testListItemIdentifiers(): void {
        $user = "john.doe@example.com";
        $articles = [
            ['id' => 1,  'edition' => 65, 'modified_date' => "2001-01-01 00:00:00"],
            ['id' => 11, 'edition' => 32, 'modified_date' => "2001-01-05 00:00:00"],
        ];
        \Phake::when(Arsse::$db)->articleList(\Phake::anyParameters())->thenReturn(new Result($articles));
        $act = $this->req("GET", "/stream/items/ids", "", $user);
        $exp = HTTP::respJson([
            'itemRefs' => [
                [
                    'id' => "1",
                ],
                [
                    'id' => "11",
                ],
            ],
        ]);
        $this->assertMessage($exp, $act);
    }
}
