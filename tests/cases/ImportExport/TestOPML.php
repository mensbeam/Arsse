<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\ImportExport\OPML;

/** @covers \JKingWeb\Arsse\ImportExport\OPML<extended> */
class TestOPML extends \JKingWeb\Arsse\Test\AbstractTest {
    protected $folders = [
        ['id' => 5, 'parent' => 3,    'children' => 0, 'feeds' => 1, 'name' => "Local"],
        ['id' => 6, 'parent' => 3,    'children' => 0, 'feeds' => 2, 'name' => "National"],
        ['id' => 4, 'parent' => null, 'children' => 0, 'feeds' => 0, 'name' => "Photography"],
        ['id' => 3, 'parent' => null, 'children' => 2, 'feeds' => 0, 'name' => "Politics"],
        ['id' => 2, 'parent' => 1,    'children' => 0, 'feeds' => 1, 'name' => "Rocketry"],
        ['id' => 1, 'parent' => null, 'children' => 1, 'feeds' => 1, 'name' => "Science"],
    ];
    protected $subscriptions = [
        ['id' => 3, 'folder' => 1,    'top_folder' => 1,    'unread' => 2,  'updated' => "2016-05-23 06:40:02", 'err_msg' => 'argh', 'title' => 'Ars Technica',   'url' => "http://example.com/3", 'favicon' => 'http://example.com/3.png'],
        ['id' => 4, 'folder' => 6,    'top_folder' => 3,    'unread' => 6,  'updated' => "2017-10-09 15:58:34", 'err_msg' => '',     'title' => 'CBC News',       'url' => "http://example.com/4", 'favicon' => 'http://example.com/4.png'],
        ['id' => 6, 'folder' => null, 'top_folder' => null, 'unread' => 0,  'updated' => "2010-02-12 20:08:47", 'err_msg' => '',     'title' => 'Eurogamer',      'url' => "http://example.com/6", 'favicon' => 'http://example.com/6.png'],
        ['id' => 1, 'folder' => 2,    'top_folder' => 1,    'unread' => 5,  'updated' => "2017-09-15 22:54:16", 'err_msg' => '',     'title' => 'NASA JPL',       'url' => "http://example.com/1", 'favicon' => null],
        ['id' => 5, 'folder' => 6,    'top_folder' => 3,    'unread' => 12, 'updated' => "2017-07-07 17:07:17", 'err_msg' => '',     'title' => 'Ottawa Citizen', 'url' => "http://example.com/5", 'favicon' => ''],
        ['id' => 2, 'folder' => 5,    'top_folder' => 3,    'unread' => 10, 'updated' => "2011-11-11 11:11:11", 'err_msg' => 'oops', 'title' => 'Toronto Star',   'url' => "http://example.com/2", 'favicon' => 'http://example.com/2.png'],
    ];
    protected $tags = [
        ['id' => 1, 'name' => "Canada", 'subscription' => 2],
        ['id' => 1, 'name' => "Canada", 'subscription' => 4],
        ['id' => 1, 'name' => "Canada", 'subscription' => 5],
        ['id' => 2, 'name' => "Politics", 'subscription' => 4],
        ['id' => 2, 'name' => "Politics", 'subscription' => 5],
        ['id' => 3, 'name' => "Science, etc", 'subscription' => 1],
        ['id' => 3, 'name' => "Science, etc", 'subscription' => 3],
        // Eurogamer is untagged
    ];
    protected $serialization = <<<OPML_EXPORT_SERIALIZATION
<?xml version="1.0" encoding="utf-8"?>
<opml version="2.0">
<head/>
<body>
    <outline text="Photography"/>
    <outline text="Politics">
        <outline text="Local">
            <outline type="rss" text="Toronto Star" xmlUrl="http://example.com/2" category="Canada"/>
        </outline>
        <outline text="National">
            <outline type="rss" text="CBC News" xmlUrl="http://example.com/4" category="Canada,Politics"/>
            <outline type="rss" text="Ottawa Citizen" xmlUrl="http://example.com/5" category="Canada,Politics"/>
        </outline>
    </outline>
    <outline text="Science">
        <outline text="Rocketry">
            <outline type="rss" text="NASA JPL" xmlUrl="http://example.com/1" category="Science etc"/>
        </outline>
        <outline type="rss" text="Ars Technica" xmlUrl="http://example.com/3" category="Science etc"/>
    </outline>
    <outline type="rss" text="Eurogamer" xmlUrl="http://example.com/6"/>
</body>
</opml>
OPML_EXPORT_SERIALIZATION;
    protected $serializationFlat = <<<OPML_EXPORT_SERIALIZATION
<?xml version="1.0" encoding="utf-8"?>
<opml version="2.0">
<head/>
<body>
    <outline type="rss" text="Ars Technica" xmlUrl="http://example.com/3" category="Science etc"/>
    <outline type="rss" text="CBC News" xmlUrl="http://example.com/4" category="Canada,Politics"/>
    <outline type="rss" text="Eurogamer" xmlUrl="http://example.com/6"/>
    <outline type="rss" text="NASA JPL" xmlUrl="http://example.com/1" category="Science etc"/>
    <outline type="rss" text="Ottawa Citizen" xmlUrl="http://example.com/5" category="Canada,Politics"/>
    <outline type="rss" text="Toronto Star" xmlUrl="http://example.com/2" category="Canada"/>
</body>
</opml>
OPML_EXPORT_SERIALIZATION;

    public function setUp() {
        self::clearData();
        Arsse::$db = \Phake::mock(\JKingWeb\Arsse\Database::class);
        Arsse::$user = \Phake::mock(\JKingWeb\Arsse\User::class);
        \Phake::when(Arsse::$user)->exists->thenReturn(true);
    }

    public function testExportToOpml() {
        \Phake::when(Arsse::$db)->folderList("john.doe@example.com")->thenReturn(new Result($this->folders));
        \Phake::when(Arsse::$db)->subscriptionList("john.doe@example.com")->thenReturn(new Result($this->subscriptions));
        \Phake::when(Arsse::$db)->tagSummarize("john.doe@example.com")->thenReturn(new Result($this->tags));
        $this->assertXmlStringEqualsXmlString($this->serialization, (new OPML)->export("john.doe@example.com"));
    }

    public function testExportToFlatOpml() {
        \Phake::when(Arsse::$db)->folderList("john.doe@example.com")->thenReturn(new Result($this->folders));
        \Phake::when(Arsse::$db)->subscriptionList("john.doe@example.com")->thenReturn(new Result($this->subscriptions));
        \Phake::when(Arsse::$db)->tagSummarize("john.doe@example.com")->thenReturn(new Result($this->tags));
        $this->assertXmlStringEqualsXmlString($this->serializationFlat, (new OPML)->export("john.doe@example.com", true));
    }

    public function testExportToOpmlAMissingUser() {
        \Phake::when(Arsse::$user)->exists->thenReturn(false);
        $this->assertException("doesNotExist", "User");
        (new OPML)->export("john.doe@example.com");
    }
}
