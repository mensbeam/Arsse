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
use JKingWeb\Arsse\REST\Reader\FeedHQ\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(\JKingWeb\Arsse\REST\Reader\Reader::class)]
#[CoversClass(\JKingWeb\Arsse\REST\Reader\Exception::class)]
class TestReaderFeedHQ extends TestReader {
    public function setUp(): void {
        $this->setUpTest(Reader::class);
    }

    protected function req(string $method, string $target, string $data = "", ?string $user = null): ResponseInterface {
        if (strlen((string) $user)) {
            Arsse::$user->id = $user;
        }
        return $this->h->dispatch($this->serverRequest($method, "/reader/api/0".$target, "/reader/api/0", ['Accept' => "application/json"], [], $data, "application/x-www-form-urlencoded", [], $user));
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
            $exp = HTTP::respText("$token");
        } else {
            $random = "";
            \Phake::verify(Arsse::$db)->tokenCreate($user, "reader.post", \Phake::capture($random));
            $exp = HTTP::respText("$random");
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
            yield [Reader::class, $target, "T=12345\n", $failure];
            yield [Reader::class, $target, "",          $failure];
            yield [Reader::class, $target, "T=",        $failure];
            yield [Reader::class, $target, "T=x",       $failure];
            yield [Reader::class, $target, "T=56789",   $failure];
            yield [Reader::class, $target, "T=\n",      $failure];
            yield [Reader::class, $target, "T=x\n",     $failure];
            yield [Reader::class, $target, "T=56789\n", $failure];
        }
    }

    public function testGetUserInfo(): void {
        $user = "john.doe@example.com";
        \Phake::when(Arsse::$user)->propertiesGet(\Phake::anyParameters())->thenReturn([
            'num' => 2112,
        ]);
        $act = $this->req("GET", "/user-info", "", $user);
        $exp = HTTP::respJson([
            'userName' => "john.doe@example.com",
            'userEmail' => "",
            'userId' => "2112",
            'userProfileId' => "2112",
            'isBloggerUser' => false,
            'signupTimeSec' => 1608592157,
            'isMultiLoginEnabled' => false,
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
        $exp = <<<XML_FILE
<object>
  <string name="id">user/-/state/com.google/reading-list</string>
  <number name="updated">1608592157</number>
  <list name="items">
    <object>
      <string name="id">tag:google.com,2005:reader/item/0000000000000001</string>
      <string name="crawlTimeMsec">978307200000</string>
      <string name="timestampUsec">978307200000000</string>
      <number name="published">946684800</number>
      <number name="updated">946771200</number>
      <string name="title">Edition 65</string>
      <list name="canonical">
        <object>
          <string name="href">http://example.com/65</string>
        </object>
      </list>
      <list name="alternate">
        <object>
          <string name="href">http://example.com/65</string>
          <string name="type">text/html</string>
        </object>
      </list>
      <list name="categories">
        <string>user/-/state/com.google/reading-list</string>
        <string>user/-/state/org.freshrss/main</string>
        <string>user/-/state/com.google/unread</string>
        <string>user/-/state/com.google/kept-unread</string>
        <string>user/-/label/Ook</string>
        <string>user/-/label/Foo</string>
        <string>user/-/label/Bar</string>
        <string>Alfa</string>
        <string>Bravo</string>
      </list>
      <object name="origin">
        <string name="streamId">feed/1</string>
        <string name="htmlUrl">http://example.com/</string>
        <string name="title">Sub 1</string>
      </object>
      <object name="summary">
        <string name="content">Content 65</string>
      </object>
      <list name="enclosure"/>
      <string name="author">John Doe</string>
      <list name="linkingUsers"/>
      <list name="comments"/>
      <number name="commentsNum">-1</number>
      <list name="annotations"/>
    </object>
    <object>
      <string name="id">tag:google.com,2005:reader/item/000000000000000b</string>
      <string name="crawlTimeMsec">978652800000</string>
      <string name="timestampUsec">978652800000000</string>
      <number name="published">946944000</number>
      <number name="updated">946944000</number>
      <string name="title">Edition 32</string>
      <list name="canonical">
        <object>
          <string name="href">http://example.com/32</string>
        </object>
      </list>
      <list name="alternate">
        <object>
          <string name="href">http://example.com/32</string>
          <string name="type">text/html</string>
        </object>
      </list>
      <list name="categories">
        <string>user/-/state/com.google/reading-list</string>
        <string>user/-/state/org.freshrss/main</string>
        <string>user/-/state/com.google/read</string>
        <string>user/-/state/com.google/starred</string>
        <string>user/-/label/Dupe</string>
      </list>
      <object name="origin">
        <string name="streamId">feed/12</string>
        <string name="htmlUrl">http://example.org/</string>
        <string name="title">Sub 12</string>
      </object>
      <object name="summary">
        <string name="content">Content 32</string>
      </object>
      <list name="enclosure">
        <object>
          <string name="href">http://example.com/audio</string>
          <string name="type">audio/vorbis</string>
        </object>
      </list>
      <null name="author"/>
      <list name="linkingUsers"/>
      <list name="comments"/>
      <number name="commentsNum">-1</number>
      <list name="annotations"/>
    </object>
  </list>
</object>
XML_FILE;
        $exp = HTTP::respXml($exp);
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
        $exp = self::respError("AtomNotImplemented");
        $this->assertMessage($exp, $act);
    }
}
