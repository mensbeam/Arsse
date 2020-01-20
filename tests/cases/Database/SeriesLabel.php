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
    protected function setUpSeriesLabel():void {
        $this->data = [
            'arsse_users' => [
                'columns' => [
                    'id'       => 'str',
                    'password' => 'str',
                ],
                'rows' => [
                    ["jane.doe@example.com", ""],
                    ["john.doe@example.com", ""],
                    ["john.doe@example.org", ""],
                    ["john.doe@example.net", ""],
                ],
            ],
            'arsse_folders' => [
                'columns' => [
                    'id'     => "int",
                    'owner'  => "str",
                    'parent' => "int",
                    'name'   => "str",
                ],
                'rows' => [
                    [1, "john.doe@example.com", null, "Technology"],
                    [2, "john.doe@example.com",    1, "Software"],
                    [3, "john.doe@example.com",    1, "Rocketry"],
                    [4, "jane.doe@example.com", null, "Politics"],
                    [5, "john.doe@example.com", null, "Politics"],
                    [6, "john.doe@example.com",    2, "Politics"],
                    [7, "john.doe@example.net", null, "Technology"],
                    [8, "john.doe@example.net",    7, "Software"],
                    [9, "john.doe@example.net", null, "Politics"],
                ]
            ],
            'arsse_feeds' => [
                'columns' => [
                    'id'         => "int",
                    'url'        => "str",
                ],
                'rows' => [
                    [1,"http://example.com/1"],
                    [2,"http://example.com/2"],
                    [3,"http://example.com/3"],
                    [4,"http://example.com/4"],
                    [5,"http://example.com/5"],
                    [6,"http://example.com/6"],
                    [7,"http://example.com/7"],
                    [8,"http://example.com/8"],
                    [9,"http://example.com/9"],
                    [10,"http://example.com/10"],
                    [11,"http://example.com/11"],
                    [12,"http://example.com/12"],
                    [13,"http://example.com/13"],
                ]
            ],
            'arsse_subscriptions' => [
                'columns' => [
                    'id'         => "int",
                    'owner'      => "str",
                    'feed'       => "int",
                    'folder'     => "int",
                ],
                'rows' => [
                    [1,"john.doe@example.com",1,null],
                    [2,"john.doe@example.com",2,null],
                    [3,"john.doe@example.com",3,1],
                    [4,"john.doe@example.com",4,6],
                    [5,"john.doe@example.com",10,5],
                    [6,"jane.doe@example.com",1,null],
                    [7,"jane.doe@example.com",10,null],
                    [8,"john.doe@example.org",11,null],
                    [9,"john.doe@example.org",12,null],
                    [10,"john.doe@example.org",13,null],
                    [11,"john.doe@example.net",10,null],
                    [12,"john.doe@example.net",2,9],
                    [13,"john.doe@example.net",3,8],
                    [14,"john.doe@example.net",4,7],
                ]
            ],
            'arsse_articles' => [
                'columns' => [
                    'id'                 => "int",
                    'feed'               => "int",
                    'url'                => "str",
                    'title'              => "str",
                    'author'             => "str",
                    'published'          => "datetime",
                    'edited'             => "datetime",
                    'content'            => "str",
                    'guid'               => "str",
                    'url_title_hash'     => "str",
                    'url_content_hash'   => "str",
                    'title_content_hash' => "str",
                    'modified'           => "datetime",
                ],
                'rows' => [
                    [1,1,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [2,1,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [3,2,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [4,2,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [5,3,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [6,3,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [7,4,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [8,4,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [9,5,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [10,5,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [11,6,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [12,6,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [13,7,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [14,7,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [15,8,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [16,8,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [17,9,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [18,9,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [19,10,null,null,null,null,null,null,null,"","","","2000-01-01T00:00:00Z"],
                    [20,10,null,null,null,null,null,null,null,"","","","2010-01-01T00:00:00Z"],
                    [101,11,'http://example.com/1','Article title 1','','2000-01-01 00:00:00','2000-01-01 00:00:01','<p>Article content 1</p>','e433653cef2e572eee4215fa299a4a5af9137b2cefd6283c85bd69a32915beda','f5cb8bfc1c7396dc9816af212a3e2ac5221585c2a00bf7ccb6aabd95dcfcd6a6','fb0bc8f8cb08913dc5a497db700e327f1d34e4987402687d494a5891f24714d4','18fdd4fa93d693128c43b004399e5c9cea6c261ddfa002518d3669f55d8c2207','2000-01-01 01:00:00'],
                    [102,11,'http://example.com/2','Article title 2','','2000-01-02 00:00:00','2000-01-02 00:00:02','<p>Article content 2</p>','5be8a5a46ecd52ed132191c8d27fb1af6b3d4edc00234c5d9f8f0e10562ed3b7','0e86d2de822a174fe3c44a466953e63ca1f1a58a19cbf475fce0855d4e3d5153','13075894189c47ffcfafd1dfe7fbb539f7c74a69d35a399b3abf8518952714f9','2abd0a8cba83b8214a66c8f0293ba63e467d720540e29ff8ddcdab069d4f1c9e','2000-01-02 02:00:00'],
                    [103,12,'http://example.com/3','Article title 3','','2000-01-03 00:00:00','2000-01-03 00:00:03','<p>Article content 3</p>','31a6594500a48b59fcc8a075ce82b946c9c3c782460d088bd7b8ef3ede97ad92','f74b06b240bd08abf4d3fdfc20dba6a6f6eb8b4f1a00e9a617efd63a87180a4b','b278380e984cefe63f0e412b88ffc9cb0befdfa06fdc00bace1da99a8daff406','ad622b31e739cd3a3f3c788991082cf4d2f7a8773773008e75f0572e58cd373b','2000-01-03 03:00:00'],
                    [104,12,'http://example.com/4','Article title 4','','2000-01-04 00:00:00','2000-01-04 00:00:04','<p>Article content 4</p>','804e517d623390e71497982c77cf6823180342ebcd2e7d5e32da1e55b09dd180','f3615c7f16336d3ea242d35cf3fc17dbc4ee3afb78376bf49da2dd7a5a25dec8','f11c2b4046f207579aeb9c69a8c20ca5461cef49756ccfa5ba5e2344266da3b3','ab2da63276acce431250b18d3d49b988b226a99c7faadf275c90b751aee05be9','2000-01-04 04:00:00'],
                    [105,13,'http://example.com/5','Article title 5','','2000-01-05 00:00:00','2000-01-05 00:00:05','<p>Article content 5</p>','db3e736c2c492f5def5c5da33ddcbea1824040e9ced2142069276b0a6e291a41','d40da96e39eea6c55948ccbe9b3d275b5f931298288dbe953990c5f496097022','834240f84501b5341d375414718204ec421561f3825d34c22bf9182203e42900','43b970ac6ec5f8a9647b2c7e4eed8b1d7f62e154a95eed748b0294c1256764ba','2000-01-05 05:00:00'],
                ]
            ],
            'arsse_enclosures' => [
                'columns' => [
                    'article' => "int",
                    'url'     => "str",
                    'type'    => "str",
                ],
                'rows' => [
                    [102,"http://example.com/text","text/plain"],
                    [103,"http://example.com/video","video/webm"],
                    [104,"http://example.com/image","image/svg+xml"],
                    [105,"http://example.com/audio","audio/ogg"],

                ]
            ],
            'arsse_editions' => [
                'columns' => [
                    'id'       => "int",
                    'article'  => "int",
                ],
                'rows' => [
                    [1,1],
                    [2,2],
                    [3,3],
                    [4,4],
                    [5,5],
                    [6,6],
                    [7,7],
                    [8,8],
                    [9,9],
                    [10,10],
                    [11,11],
                    [12,12],
                    [13,13],
                    [14,14],
                    [15,15],
                    [16,16],
                    [17,17],
                    [18,18],
                    [19,19],
                    [20,20],
                    [101,101],
                    [102,102],
                    [103,103],
                    [104,104],
                    [105,105],
                    [202,102],
                    [203,103],
                    [204,104],
                    [205,105],
                    [305,105],
                    [1001,20],
                ]
            ],
            'arsse_marks' => [
                'columns' => [
                    'subscription' => "int",
                    'article'      => "int",
                    'read'         => "bool",
                    'starred'      => "bool",
                    'modified'     => "datetime"
                ],
                'rows' => [
                    [1,   1,1,1,'2000-01-01 00:00:00'],
                    [5,  19,1,0,'2000-01-01 00:00:00'],
                    [5,  20,0,1,'2010-01-01 00:00:00'],
                    [7,  20,1,0,'2010-01-01 00:00:00'],
                    [8, 102,1,0,'2000-01-02 02:00:00'],
                    [9, 103,0,1,'2000-01-03 03:00:00'],
                    [9, 104,1,1,'2000-01-04 04:00:00'],
                    [10,105,0,0,'2000-01-05 05:00:00'],
                    [11, 19,0,0,'2017-01-01 00:00:00'],
                    [11, 20,1,0,'2017-01-01 00:00:00'],
                    [12,  3,0,1,'2017-01-01 00:00:00'],
                    [12,  4,1,1,'2017-01-01 00:00:00'],
                ]
            ],
            'arsse_labels' => [
                'columns' => [
                    'id'       => "int",
                    'owner'    => "str",
                    'name'     => "str",
                ],
                'rows' => [
                    [1,"john.doe@example.com","Interesting"],
                    [2,"john.doe@example.com","Fascinating"],
                    [3,"jane.doe@example.com","Boring"],
                    [4,"john.doe@example.com","Lonely"],
                ],
            ],
            'arsse_label_members' => [
                'columns' => [
                    'label' => "int",
                    'article' => "int",
                    'subscription' => "int",
                    'assigned' => "bool",
                ],
                'rows' => [
                    [1, 1,1,1],
                    [2, 1,1,1],
                    [1,19,5,1],
                    [2,20,5,1],
                    [1, 5,3,0],
                    [2, 5,3,1],
                ],
            ],
        ];
        $this->checkLabels = ['arsse_labels' => ["id","owner","name"]];
        $this->checkMembers = ['arsse_label_members' => ["label","article","subscription","assigned"]];
        $this->user = "john.doe@example.com";
    }

    protected function tearDownSeriesLabel():void {
        unset($this->data, $this->checkLabels, $this->checkMembers, $this->user);
    }

    public function testAddALabel():void {
        $user = "john.doe@example.com";
        $labelID = $this->nextID("arsse_labels");
        $this->assertSame($labelID, Arsse::$db->labelAdd($user, ['name' => "Entertaining"]));
        \Phake::verify(Arsse::$user)->authorize($user, "labelAdd");
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        $state['arsse_labels']['rows'][] = [$labelID, $user, "Entertaining"];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testAddADuplicateLabel():void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => "Interesting"]);
    }

    public function testAddALabelWithAMissingName():void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", []);
    }

    public function testAddALabelWithABlankName():void {
        $this->assertException("missing", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => ""]);
    }

    public function testAddALabelWithAWhitespaceName():void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => " "]);
    }

    public function testAddALabelWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelAdd("john.doe@example.com", ['name' => "Boring"]);
    }

    public function testListLabels():void {
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
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "labelList");
    }

    public function testListLabelsWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelList("john.doe@example.com");
    }

    public function testRemoveALabel():void {
        $this->assertTrue(Arsse::$db->labelRemove("john.doe@example.com", 1));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "labelRemove");
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        array_shift($state['arsse_labels']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveALabelByName():void {
        $this->assertTrue(Arsse::$db->labelRemove("john.doe@example.com", "Interesting", true));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "labelRemove");
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        array_shift($state['arsse_labels']['rows']);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRemoveAMissingLabel():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", 2112);
    }

    public function testRemoveAnInvalidLabel():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", -1);
    }

    public function testRemoveAnInvalidLabelByName():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", [], true);
    }

    public function testRemoveALabelOfTheWrongOwner():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelRemove("john.doe@example.com", 3); // label ID 3 belongs to Jane
    }

    public function testRemoveALabelWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelRemove("john.doe@example.com", 1);
    }

    public function testGetThePropertiesOfALabel():void {
        $exp = [
            'id'       => 2,
            'name'     => "Fascinating",
            'articles' => 3,
            'read'     => 1,
        ];
        $this->assertArraySubset($exp, Arsse::$db->labelPropertiesGet("john.doe@example.com", 2));
        $this->assertArraySubset($exp, Arsse::$db->labelPropertiesGet("john.doe@example.com", "Fascinating", true));
        \Phake::verify(Arsse::$user, \Phake::times(2))->authorize("john.doe@example.com", "labelPropertiesGet");
    }

    public function testGetThePropertiesOfAMissingLabel():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", 2112);
    }

    public function testGetThePropertiesOfAnInvalidLabel():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", -1);
    }

    public function testGetThePropertiesOfAnInvalidLabelByName():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", [], true);
    }

    public function testGetThePropertiesOfALabelOfTheWrongOwner():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", 3); // label ID 3 belongs to Jane
    }

    public function testGetThePropertiesOfALabelWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelPropertiesGet("john.doe@example.com", 1);
    }

    public function testMakeNoChangesToALabel():void {
        $this->assertFalse(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, []));
    }

    public function testRenameALabel():void {
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "Curious"]));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "labelPropertiesSet");
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        $state['arsse_labels']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameALabelByName():void {
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", "Interesting", ['name' => "Curious"], true));
        \Phake::verify(Arsse::$user)->authorize("john.doe@example.com", "labelPropertiesSet");
        $state = $this->primeExpectations($this->data, $this->checkLabels);
        $state['arsse_labels']['rows'][0][2] = "Curious";
        $this->compareExpectations(static::$drv, $state);
    }

    public function testRenameALabelToTheEmptyString():void {
        $this->assertException("missing", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => ""]));
    }

    public function testRenameALabelToWhitespaceOnly():void {
        $this->assertException("whitespace", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "   "]));
    }

    public function testRenameALabelToAnInvalidValue():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        $this->assertTrue(Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => []]));
    }

    public function testCauseALabelCollision():void {
        $this->assertException("constraintViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "Fascinating"]);
    }

    public function testSetThePropertiesOfAMissingLabel():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 2112, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidLabel():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", -1, ['name' => "Exciting"]);
    }

    public function testSetThePropertiesOfAnInvalidLabelByName():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", [], ['name' => "Exciting"], true);
    }

    public function testSetThePropertiesOfALabelForTheWrongOwner():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 3, ['name' => "Exciting"]); // label ID 3 belongs to Jane
    }

    public function testSetThePropertiesOfALabelWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelPropertiesSet("john.doe@example.com", 1, ['name' => "Exciting"]);
    }

    public function testListLabelledArticles():void {
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

    public function testListLabelledArticlesForAMissingLabel():void {
        $this->assertException("subjectMissing", "Db", "ExceptionInput");
        Arsse::$db->labelArticlesGet("john.doe@example.com", 3);
    }

    public function testListLabelledArticlesForAnInvalidLabel():void {
        $this->assertException("typeViolation", "Db", "ExceptionInput");
        Arsse::$db->labelArticlesGet("john.doe@example.com", -1);
    }

    public function testListLabelledArticlesWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelArticlesGet("john.doe@example.com", 1);
    }

    public function testApplyALabelToArticles():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([2,5]));
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][4][3] = 1;
        $state['arsse_label_members']['rows'][] = [1,2,1,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearALabelFromArticles():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([1,5]), Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][3] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyALabelToArticlesByName():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", "Interesting", (new Context)->articles([2,5]), Database::ASSOC_ADD, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][4][3] = 1;
        $state['arsse_label_members']['rows'][] = [1,2,1,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearALabelFromArticlesByName():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", "Interesting", (new Context)->articles([1,5]), Database::ASSOC_REMOVE, true);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][3] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyALabelToNoArticles():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([10000]));
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testClearALabelFromNoArticles():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([10000]), Database::ASSOC_REMOVE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $this->compareExpectations(static::$drv, $state);
    }

    public function testReplaceArticlesOfALabel():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([2,5]), Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][3] = 0;
        $state['arsse_label_members']['rows'][2][3] = 0;
        $state['arsse_label_members']['rows'][4][3] = 1;
        $state['arsse_label_members']['rows'][] = [1,2,1,1];
        $this->compareExpectations(static::$drv, $state);
    }

    public function testPurgeArticlesOfALabel():void {
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([10000]), Database::ASSOC_REPLACE);
        $state = $this->primeExpectations($this->data, $this->checkMembers);
        $state['arsse_label_members']['rows'][0][3] = 0;
        $state['arsse_label_members']['rows'][2][3] = 0;
        $this->compareExpectations(static::$drv, $state);
    }

    public function testApplyALabelToArticlesWithoutAuthority():void {
        \Phake::when(Arsse::$user)->authorize->thenReturn(false);
        $this->assertException("notAuthorized", "User", "ExceptionAuthz");
        Arsse::$db->labelArticlesSet("john.doe@example.com", 1, (new Context)->articles([2,5]));
    }
}
