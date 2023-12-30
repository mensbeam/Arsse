<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Context\Context;

trait SeriesLabel {
    protected static $drv;
    protected $checkLabels;

    protected function setUpSeriesLabel(): void {
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                    ["john.doe@example.org", "",3],
                    ["john.doe@example.net", "",4],
                ],
            ],
            'arsse_folders' => [
                'columns' => ["id", "owner", "parent", "name"],
                'rows'    => [
                    [1, "john.doe@example.com", null, "Technology"],
                    [2, "john.doe@example.com",    1, "Software"],
                    [3, "john.doe@example.com",    1, "Rocketry"],
                    [4, "jane.doe@example.com", null, "Politics"],
                    [5, "john.doe@example.com", null, "Politics"],
                    [6, "john.doe@example.com",    2, "Politics"],
                    [7, "john.doe@example.net", null, "Technology"],
                    [8, "john.doe@example.net",    7, "Software"],
                    [9, "john.doe@example.net", null, "Politics"],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "folder", "deleted"],
                'rows'    => [
                    [1,  "john.doe@example.com", "http://example.com/1",  null, 0],
                    [2,  "john.doe@example.com", "http://example.com/2",  null, 0],
                    [3,  "john.doe@example.com", "http://example.com/3",     1, 0],
                    [4,  "john.doe@example.com", "http://example.com/4",     6, 0],
                    [5,  "john.doe@example.com", "http://example.com/10"    ,5, 0],
                    [6,  "jane.doe@example.com", "http://example.com/1",  null, 0],
                    [7,  "jane.doe@example.com", "http://example.com/10", null, 0],
                    [8,  "john.doe@example.org", "http://example.com/11", null, 0],
                    [9,  "john.doe@example.org", "http://example.com/12", null, 0],
                    [10, "john.doe@example.org", "http://example.com/13", null, 0],
                    [11, "john.doe@example.net", "http://example.com/10", null, 0],
                    [12, "john.doe@example.net", "http://example.com/2",     9, 0],
                    [13, "john.doe@example.net", "http://example.com/3",     8, 0],
                    [14, "john.doe@example.net", "http://example.com/4",     7, 0],
                    [16, "john.doe@example.com", "http://example.com/16", null, 1],
                ],
            ],
            'arsse_articles' => [
                'columns' => [
                    "id", "subscription", "url", "title", "author", "published", "edited", "guid",
                    "url_title_hash", "url_content_hash", "title_content_hash", "modified",
                    "read", "starred", "hidden", "marked", "note",
                ],
                'rows' => [
                    [1,   1,null,                  "Title one",      null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",1,1,0,'2000-01-01 00:00:00',''],
                    [2,   1,null,                  "Title two",      null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,0,0,'2010-01-01 00:00:00','Some Note'],
                    [3,   2,null,                  "Title three",    null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [4,   2,null,                  null,             "John Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [5,   3,null,                  null,             "John Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [6,   3,null,                  null,             "Jane Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,0,1,'2000-01-01 00:00:00',''],
                    [7,   4,null,                  null,             "Jane Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [8,   4,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,0,1,null,                 ''],
                    [19,  5,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",1,0,0,'2016-01-01 00:00:00',''],
                    [20,  5,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,1,0,'2005-01-01 00:00:00',''],
                    [501, 6,null,                  "Title one",      null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,1,1,'2000-01-01 00:00:00',''],
                    [502, 6,null,                  "Title two",      null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",1,0,1,'2010-01-01 00:00:00',''],
                    [519, 7,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [520, 7,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",1,0,0,'2010-01-01 00:00:00',''],
                    [101, 8,'http://example.com/1','Article title 1','',        '2000-01-01 00:00:00','2000-01-01 00:00:01','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207','2000-01-01 01:00:00',0,0,0,null,                 ''],
                    [102, 8,'http://example.com/2','Article title 2','',        '2000-01-02 00:00:00','2000-01-02 00:00:02','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e','2000-01-02 02:00:00',1,0,0,'2000-01-02 02:00:00','Note 2'],
                    [103, 9,'http://example.com/3','Article title 3','',        '2000-01-03 00:00:00','2000-01-03 00:00:03','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b','2000-01-03 03:00:00',0,1,0,'2000-01-03 03:00:00','Note 3'],
                    [104, 9,'http://example.com/4','Article title 4','',        '2000-01-04 00:00:00','2000-01-04 00:00:04','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9','2000-01-04 04:00:00',1,1,0,'2000-01-04 04:00:00','Note 4'],
                    [105,10,'http://example.com/5','Article title 5','',        '2000-01-05 00:00:00','2000-01-05 00:00:05','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba','2000-01-05 05:00:00',0,0,0,'2000-01-05 05:00:00',''],
                    [119,11,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,'2017-01-01 00:00:00','ook'],
                    [120,11,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",1,0,0,'2017-01-01 00:00:00','eek'],
                    [203,12,null,                  "Title three",    null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,1,0,'2017-01-01 00:00:00','ack'],
                    [204,12,null,                  null,             "John Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",1,1,0,'2017-01-01 00:00:00','ach'],
                    [205,13,null,                  null,             "John Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [206,13,null,                  null,             "Jane Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [207,14,null,                  null,             "Jane Doe",null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",0,0,0,null,                 ''],
                    [208,14,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2010-01-01 00:00:00",0,0,0,null,                 ''],
                    [999,16,null,                  null,             null,      null,                 null,                 null,                                                              "",                                                                "",                                                                "",                                                                "2000-01-01 00:00:00",1,1,0,null,                 ''],
                ],
            ],
            'arsse_article_contents' => [
                'columns' => ["id", "content"],
                'rows' => [
                    [1,   'First article'],
                    [2,   'Second article'],
                    [3,   'third article'],
                    [4,   ''],
                    [5,   ''],
                    [6,   ''],
                    [7,   ''],
                    [8,   ''],
                    [19,  ''],
                    [20,  ''],
                    [501,'First article'],
                    [502,'Second article'],
                    [519,''],
                    [520,''],
                    [101, '<p>Article content 1</p>'],
                    [102, '<p>Article content 2</p>'],
                    [103, '<p>Article content 3</p>'],
                    [104, '<p>Article content 4</p>'],
                    [105, '<p>Article content 5</p>'],
                    [119, ''],
                    [120, ''],
                    [203, 'third article'],
                    [204, ''],
                    [205, ''],
                    [206, ''],
                    [207, ''],
                    [208, ''],
                ],
            ],
            'arsse_editions' => [
                'columns' => ["id", "article"],
                'rows'    => [
                    [   1,  1],
                    [   2,  2],
                    [   3,  3],
                    [   4,  4],
                    [   5,  5],
                    [   6,  6],
                    [   7,  7],
                    [   8,  8],
                    [  19, 19],
                    [  20, 20],
                    [1001, 20],
                    [ 101,101],
                    [ 102,102],
                    [ 202,102],
                    [ 103,103],
                    [ 203,103],
                    [ 104,104],
                    [ 204,104],
                    [ 105,105],
                    [ 205,105],
                    [ 305,105],
                    [ 501,501],
                    [ 119,119],
                    [ 120,120],
                    [1101,120],
                    [2203,203],
                    [2204,204],
                    [2205,205],
                    [2206,206],
                    [2207,207],
                    [2208,208],
                    [ 502,502],
                    [ 519,519],
                    [ 520,520],
                    [1501,520],
                ],
            ],
            'arsse_enclosures' => [
                'columns' => ["article", "url", "type"],
                'rows'    => [
                    [102,"http://example.com/text","text/plain"],
                    [103,"http://example.com/video","video/webm"],
                    [104,"http://example.com/image","image/svg+xml"],
                    [105,"http://example.com/audio","audio/ogg"],
                ],
            ],
            'arsse_categories' => [ // author-supplied categories
                'columns' => ["article", "name"],
                'rows'    => [
                    [19, "Fascinating"],
                    [19, "Logical"],
                    [20, "Interesting"],
                    [20, "Logical"],
                    [119,"Fascinating"],
                    [119,"Logical"],
                    [120,"Interesting"],
                    [120,"Logical"],
                    [519,"Fascinating"],
                    [519,"Logical"],
                    [520,"Interesting"],
                    [520,"Logical"],
                ],
            ],
            'arsse_labels' => [ // labels applied to articles
                'columns' => ["id", "owner", "name"],
                'rows'    => [
                    [1,"john.doe@example.com","Interesting"],
                    [2,"john.doe@example.com","Fascinating"],
                    [3,"jane.doe@example.com","Boring"],
                    [4,"john.doe@example.com","Lonely"],
                ],
            ],
            'arsse_label_members' => [
                'columns' => ["label", "article", "assigned"],
                'rows'    => [
                    [1,  1,1],
                    [2,  1,1],
                    [1, 19,1],
                    [2, 20,1],
                    [1,  5,0],
                    [2,  5,1],
                    [2,  8,1],
                    [1,999,1],
                    [2,999,1],
                ],
            ],
        ];
        $this->checkLabels = ['arsse_labels' => ["id","owner","name"]];
        $this->checkMembers = ['arsse_label_members' => ["label","article","assigned"]];
        $this->user = "john.doe@example.com";
    }

    protected function tearDownSeriesLabel(): void {
        unset($this->data, $this->checkLabels, $this->checkMembers, $this->user);
    }

    public function testAddALabel(): void {
        $user = "john.doe@example.com";
        $labelID = $this->nextID("arsse_labels");
        $this->assertSame($labelID, Arsse::$db->labelAdd($user, ['name' => "Entertaining"]));
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        $state['arsse_labels']['rows'][] = [$labelID, $user, "Entertaining"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddADuplicateLabel(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => "Interesting"]);
    }

    public function testAddALabelWithAMissingName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", []);
    }

    public function testAddALabelWithABlankName(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => ""]);
    }

    public function testAddALabelWithAWhitespaceName(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => " "]);
    }

    public function testListLabels(): void {
        $exp = [
            ['id' => 2, 'name' => "Fascinating", 'articles' => 3, 'read' => 1],
            ['id' => 1, 'name' => "Interesting", 'articles' => 2, 'read' => 2],
            ['id' => 4, 'name' => "Lonely",      'articles' => 0, 'read' => 0],
        ];
        $this->assertResult($exp, Arsse::$db->labelList("john.doe@example.com"));
        $exp = [
            ['id' => 3, 'name' => "Boring",   'articles' => 0],
        ];
        $this->assertResult($exp, Arsse::$db->labelList("jane.doe@example.com"));
        $exp = [];
        $this->assertResult($exp, Arsse::$db->labelList("jane.doe@example.com", false));
    }

    public function testRemoveALabel(): void {
        $this->assertTrue(Arsse::$db->labelRemove("john.doe@example.com", 1));
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        array_shift($state['arsse_labels']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveALabelByName(): void {
        $this->assertTrue(Arsse::$db->labelRemove("john.doe@example.com", "Interesting", true));
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        array_shift($state['arsse_labels']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingLabel(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", 2112);
    }

    public function testRemoveAnInvalidLabel(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", -1);
    }

    public function testRemoveAnInvalidLabelByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", [], true);
    }

    public function testRemoveALabelOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", 3); // label ID 3 belongs to Jane
    }

    public function testGetThePropertiesOfALabel(): void {
        $exp = [
            'id'       => 2,
            'name'     => "Fascinating",
            'articles' => 3,
            'read'     => 1,
        ];
        $this->assertArraySubset($exp, Arsse::$db->labelPropertiesGet("john.doe@example.com", 2));
        $this->assertArraySubset($exp, Arsse::$db->labelPropertiesGet("john.doe@example.com", "Fascinating", true));
    }

    public function testGetThePropertiesOfAMissingLabel(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", 2112);
    }

    public function testGetThePropertiesOfAnInvalidLabel(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", -1);
    }

    public function testGetThePropertiesOfAnInvalidLabelByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", [], true);
    }

    public function testGetThePropertiesOfALabelOfTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", 3); // label ID 3 belongs to Jane
    }

    public function testMakeNoChangesToALabel(): void {
        $this->assertFalse(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, []));
    }

    public function testRenameALabel(): void {
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "Curious"]));
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        $state['arsse_labels']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameALabelByName(): void {
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", "Interesting", ['name' => "Curious"], true));
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        $state['arsse_labels']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameALabelToTheEmptyString(): void {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => ""]));
    }

    public function testRenameALabelToWhitespaceOnly(): void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "   "]));
    }

    public function testRenameALabelToAnInvalidValue(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => []]));
    }

    public function testCauseALabelCollision(): void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "Fascinating"]);
    }

    public function testSetThePropertiesOfAMissingLabel(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 2112, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidLabel(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", -1, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidLabelByName(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", [], ['name' => "Exciting"], true);
    }

    public function testSetThePropertiesOfALabelForTheWrongOwner(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 3, ['name' => "Exciting"]); // label ID 3 belongs to Jane
    }

    public function testListLabelledArticles(): void {
        $exp = [1,19];
        $this->assertEquals($exp, Arsse::$db->labelArticlesGet("john.doe@example.com", 1));
        $this->assertEquals($exp, Arsse::$db->labelArticlesGet("john.doe@example.com", "Interesting", true));
        $exp = [1,5,20];
        $this->assertEquals($exp, Arsse::$db->labelArticlesGet("john.doe@example.com", 2));
        $this->assertEquals($exp, Arsse::$db->labelArticlesGet("john.doe@example.com", "Fascinating", true));
        $exp = [];
        $this->assertEquals($exp, Arsse::$db->labelArticlesGet("john.doe@example.com", 4));
        $this->assertEquals($exp, Arsse::$db->labelArticlesGet("john.doe@example.com", "Lonely", true));
    }

    public function testListLabelledArticlesForAMissingLabel(): void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelArticlesGet("john.doe@example.com", 3);
    }

    public function testListLabelledArticlesForAnInvalidLabel(): void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelArticlesGet("john.doe@example.com", -1);
    }

    public function testApplyALabelToArticles(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([2,5]));
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][4][2] = 1;
        $state['arsse_label_members']['rows'][] = [1,2,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearALabelFromArticles(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([1,5]), Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyALabelToArticlesByName(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", "Interesting", (new Context)->articles([2,5]), Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][4][2] = 1;
        $state['arsse_label_members']['rows'][] = [1,2,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearALabelFromArticlesByName(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", "Interesting", (new Context)->articles([1,5]), Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyALabelToNoArticles(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([10000]));
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearALabelFromNoArticles(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([10000]), Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReplaceArticlesOfALabel(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([2,5]), Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][2] = 0;
        $state['arsse_label_members']['rows'][2][2] = 0;
        $state['arsse_label_members']['rows'][4][2] = 1;
        $state['arsse_label_members']['rows'][] = [1,2,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testPurgeArticlesOfALabel(): void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([10000]), Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][2] = 0;
        $state['arsse_label_members']['rows'][2][2] = 0;
        $this->compareExpectations(static::$drv, $state);
    }
}
