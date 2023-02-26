<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\TestCase\Database;

use JKingWeb\Arsse\Arsse;

trait SeriesIcon {
    protected static $drv;

    protected function setUpSeriesIcon(): void {
        // set up the test data
        $this->data = [
            'arsse_users' => [
                'columns' => ["id", "password", "num"],
                'rows'    => [
                    ["jane.doe@example.com", "",1],
                    ["john.doe@example.com", "",2],
                ],
            ],
            'arsse_icons' => [
                'columns' => ["id", "url", "type", "data"],
                'rows'    => [
                    [1,'http://localhost:8000/Icon/PNG','image/png',base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAZdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuMjHxIGmVAAAADUlEQVQYV2NgYGBgAAAABQABijPjAAAAAABJRU5ErkJggg==")],
                    [2,'http://localhost:8000/Icon/GIF','image/gif',base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
                    [3,'http://localhost:8000/Icon/SVG1','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect fill="#fff" height="600" width="900"/><circle fill="#bc002d" cx="450" cy="300" r="180"/></svg>'],
                    [4,'http://localhost:8000/Icon/SVG2','image/svg+xml','<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="#ED2939"/><rect width="600" height="600" fill="#fff"/><rect width="300" height="600" fill="#002395"/></svg>'],
                ],
            ],
            'arsse_subscriptions' => [
                'columns' => ["id", "owner", "url", "title", "icon", "deleted"],
                'rows'    => [
                    [1,'john.doe@example.com',"http://localhost:8000/Feed/Matching/3",                      "Ook",      1, 0],
                    [2,'john.doe@example.com',"http://localhost:8000/Feed/Matching/1",                      "Eek",      2, 0],
                    [3,'john.doe@example.com',"http://localhost:8000/Feed/Fetching/Error?code=404",         "Ack",      3, 0],
                    [4,'john.doe@example.com',"http://localhost:8000/Feed/NextFetch/NotModified?t=".time(), "Ooook", null, 0],
                    [5,'john.doe@example.com',"http://localhost:8000/Feed/Parsing/Valid",                   "Ooook",    2, 0],
                    [6,'john.doe@example.com',"http://localhost:8000/Feed/Discovery/Valid",                 "Aaack",    4, 1],
                    [7,'jane.doe@example.com',"http://localhost:8000/Feed/Parsing/Valid",                   "Ooook",    2, 0],
                ],
            ],
        ];
    }

    protected function tearDownSeriesIcon(): void {
        unset($this->data);
    }

    public function testListTheIconsOfAUser() {
        $exp = [
            ['id' => 1,'url' => 'http://localhost:8000/Icon/PNG',  'type' => 'image/png',     'data' => base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAAAZdEVYdFNvZnR3YXJlAHBhaW50Lm5ldCA0LjAuMjHxIGmVAAAADUlEQVQYV2NgYGBgAAAABQABijPjAAAAAABJRU5ErkJggg==")],
            ['id' => 2,'url' => 'http://localhost:8000/Icon/GIF',  'type' => 'image/gif',     'data' => base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
            ['id' => 3,'url' => 'http://localhost:8000/Icon/SVG1', 'type' => 'image/svg+xml', 'data' => '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect fill="#fff" height="600" width="900"/><circle fill="#bc002d" cx="450" cy="300" r="180"/></svg>'],
        ];
        $this->assertResult($exp, Arsse::$db->iconList("john.doe@example.com"));
        $exp = [
            ['id' => 2,'url' => 'http://localhost:8000/Icon/GIF',  'type' => 'image/gif',     'data' => base64_decode("R0lGODlhAQABAIABAAAAAP///yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==")],
        ];
        $this->assertResult($exp, Arsse::$db->iconList("jane.doe@example.com"));
    }
}
