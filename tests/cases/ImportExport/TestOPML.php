<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Test\Result;
use JKingWeb\Arsse\ImportExport\OPML;
use JKingWeb\Arsse\ImportExport\Exception;

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
        ['id' => 3, 'folder' => 1,    'top_folder' => 1,    'unread' => 2,  'updated' => "2016-05-23 06:40:02", 'err_msg' => 'argh', 'title' => 'Ars Technica',   'url' => "http://localhost:8000/3", 'icon_url' => 'http://localhost:8000/3.png'],
        ['id' => 4, 'folder' => 6,    'top_folder' => 3,    'unread' => 6,  'updated' => "2017-10-09 15:58:34", 'err_msg' => '',     'title' => 'CBC News',       'url' => "http://localhost:8000/4", 'icon_url' => 'http://localhost:8000/4.png'],
        ['id' => 6, 'folder' => null, 'top_folder' => null, 'unread' => 0,  'updated' => "2010-02-12 20:08:47", 'err_msg' => '',     'title' => 'Eurogamer',      'url' => "http://localhost:8000/6", 'icon_url' => 'http://localhost:8000/6.png'],
        ['id' => 1, 'folder' => 2,    'top_folder' => 1,    'unread' => 5,  'updated' => "2017-09-15 22:54:16", 'err_msg' => '',     'title' => 'NASA JPL',       'url' => "http://localhost:8000/1", 'icon_url' => null],
        ['id' => 5, 'folder' => 6,    'top_folder' => 3,    'unread' => 12, 'updated' => "2017-07-07 17:07:17", 'err_msg' => '',     'title' => 'Ottawa Citizen', 'url' => "http://localhost:8000/5", 'icon_url' => ''],
        ['id' => 2, 'folder' => 5,    'top_folder' => 3,    'unread' => 10, 'updated' => "2011-11-11 11:11:11", 'err_msg' => 'oops', 'title' => 'Toronto Star',   'url' => "http://localhost:8000/2", 'icon_url' => 'http://localhost:8000/2.png'],
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
            <outline type="rss" text="Toronto Star" xmlUrl="http://localhost:8000/2" category="Canada"/>
        </outline>
        <outline text="National">
            <outline type="rss" text="CBC News" xmlUrl="http://localhost:8000/4" category="Canada,Politics"/>
            <outline type="rss" text="Ottawa Citizen" xmlUrl="http://localhost:8000/5" category="Canada,Politics"/>
        </outline>
    </outline>
    <outline text="Science">
        <outline text="Rocketry">
            <outline type="rss" text="NASA JPL" xmlUrl="http://localhost:8000/1" category="Science etc"/>
        </outline>
        <outline type="rss" text="Ars Technica" xmlUrl="http://localhost:8000/3" category="Science etc"/>
    </outline>
    <outline type="rss" text="Eurogamer" xmlUrl="http://localhost:8000/6"/>
</body>
</opml>
OPML_EXPORT_SERIALIZATION;
    protected $serializationFlat = <<<OPML_EXPORT_SERIALIZATION
<?xml version="1.0" encoding="utf-8"?>
<opml version="2.0">
<head/>
<body>
    <outline type="rss" text="Ars Technica" xmlUrl="http://localhost:8000/3" category="Science etc"/>
    <outline type="rss" text="CBC News" xmlUrl="http://localhost:8000/4" category="Canada,Politics"/>
    <outline type="rss" text="Eurogamer" xmlUrl="http://localhost:8000/6"/>
    <outline type="rss" text="NASA JPL" xmlUrl="http://localhost:8000/1" category="Science etc"/>
    <outline type="rss" text="Ottawa Citizen" xmlUrl="http://localhost:8000/5" category="Canada,Politics"/>
    <outline type="rss" text="Toronto Star" xmlUrl="http://localhost:8000/2" category="Canada"/>
</body>
</opml>
OPML_EXPORT_SERIALIZATION;

    public function setUp(): void {
        self::clearData();
        Arsse::$db = \Phake::mock(\JKingWeb\Arsse\Database::class);
        \Phake::when(Arsse::$db)->userExists->thenReturn(true);
    }

    public function testExportToOpml(): void {
        \Phake::when(Arsse::$db)->folderList("john.doe@example.com")->thenReturn(new Result($this->folders));
        \Phake::when(Arsse::$db)->subscriptionList("john.doe@example.com")->thenReturn(new Result($this->subscriptions));
        \Phake::when(Arsse::$db)->tagSummarize("john.doe@example.com")->thenReturn(new Result($this->tags));
        $this->assertXmlStringEqualsXmlString($this->serialization, (new OPML)->export("john.doe@example.com"));
    }

    public function testExportToFlatOpml(): void {
        \Phake::when(Arsse::$db)->folderList("john.doe@example.com")->thenReturn(new Result($this->folders));
        \Phake::when(Arsse::$db)->subscriptionList("john.doe@example.com")->thenReturn(new Result($this->subscriptions));
        \Phake::when(Arsse::$db)->tagSummarize("john.doe@example.com")->thenReturn(new Result($this->tags));
        $this->assertXmlStringEqualsXmlString($this->serializationFlat, (new OPML)->export("john.doe@example.com", true));
    }

    public function testExportToOpmlAMissingUser(): void {
        \Phake::when(Arsse::$db)->userExists->thenReturn(false);
        $this->assertException("doesNotExist", "User", "ExceptionConflict");
        (new OPML)->export("john.doe@example.com");
    }

    /** @dataProvider provideParserData */
    public function testParseOpmlForImport(string $file, bool $flat, $exp): void {
        $data = file_get_contents(\JKingWeb\Arsse\DOCROOT."Import/OPML/$file");
        // set up a partial mock to make the ImportExport::parse() method visible
        $parser = \Phake::makeVisible(\Phake::partialMock(OPML::class));
        if ($exp instanceof \JKingWeb\Arsse\AbstractException) {
            $this->assertException($exp);
            $parser->parse($data, $flat);
        } else {
            $this->assertSame($exp, $parser->parse($data, $flat));
        }
    }

    public function provideParserData(): iterable {
        return [
            ["BrokenXML.opml", false, new Exception("invalidSyntax")],
            ["BrokenOPML.1.opml", false, new Exception("invalidSemantics")],
            ["BrokenOPML.2.opml", false, new Exception("invalidSemantics")],
            ["BrokenOPML.3.opml", false, new Exception("invalidSemantics")],
            ["BrokenOPML.4.opml", false, new Exception("invalidSemantics")],
            ["Empty.1.opml", false, [[], []]],
            ["Empty.2.opml", false, [[], []]],
            ["Empty.3.opml", false, [[], []]],
            ["FeedsOnly.opml", false, [[
                ['url' => "http://localhost:8000/1", 'title' => "Feed 1", 'folder' => 0, 'tags' => []],
                ['url' => "http://localhost:8000/2", 'title' => "",       'folder' => 0, 'tags' => []],
                ['url' => "http://localhost:8000/3", 'title' => "",       'folder' => 0, 'tags' => []],
                ['url' => "http://localhost:8000/4", 'title' => "",       'folder' => 0, 'tags' => []],
                ['url' => "",                     'title' => "",       'folder' => 0, 'tags' => ["whee"]],
                ['url' => "",                     'title' => "",       'folder' => 0, 'tags' => ["whee", "whoo"]],
            ], []]],
            ["FoldersOnly.opml", true, [[], []]],
            ["FoldersOnly.opml", false, [[], [1 =>
                ['id' => 1, 'name' => "Folder 1",       'parent' => 0],
                ['id' => 2, 'name' => "Folder 2",       'parent' => 0],
                ['id' => 3, 'name' => "Also a folder",  'parent' => 2],
                ['id' => 4, 'name' => "Still a folder", 'parent' => 2],
                ['id' => 5, 'name' => "Folder 5",       'parent' => 4],
                ['id' => 6, 'name' => "Folder 6",       'parent' => 0],
            ]]],
            ["MixedContent.opml", false, [[
                ['url' => "https://www.jpl.nasa.gov/multimedia/rss/news.xml",                              'title' => "NASA JPL",       'folder' => 3, 'tags' => ["tech"]],
                ['url' => "http://feeds.arstechnica.com/arstechnica/index/",                               'title' => "Ars Technica",   'folder' => 2, 'tags' => ["frequent", "tech"]],
                ['url' => "https://www.thestar.com/content/thestar/feed.RSSManagerServlet.topstories.rss", 'title' => "Toronto Star",   'folder' => 5, 'tags' => ["news", "canada", "toronto"]],
                ['url' => "http://rss.canada.com/get/?F239",                                               'title' => "Ottawa Citizen", 'folder' => 6, 'tags' => ["news", "canada"]],
                ['url' => "https://www.eurogamer.net/?format=rss",                                         'title' => "Eurogamer",      'folder' => 0, 'tags' => ["gaming", "frequent"]],
            ], [1      =>
                ['id' => 1, 'name' => "Photography", 'parent' => 0],
                ['id' => 2, 'name' => "Science",     'parent' => 0],
                ['id' => 3, 'name' => "Rocketry",    'parent' => 2],
                ['id' => 4, 'name' => "Politics",    'parent' => 0],
                ['id' => 5, 'name' => "Local",       'parent' => 4],
                ['id' => 6, 'name' => "National",    'parent' => 4],
            ]]],
        ];
    }
}
