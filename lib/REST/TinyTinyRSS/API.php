<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\ExceptionType;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Db\ResultEmpty;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;

/*

Protocol difference so far:
    - Malformed JSON data returns a different error code than login failure, for clarity
    - TT-RSS accepts whitespace-only names for categories, labels, and feeds; we do not
    - TT-RSS allows two folders to share the same name under the same parent; we do not
    - TT-RSS requires the user to choose in the face of multiple found feeds during discovery; we use the first one (picoFeed limitation)
    - Session lifetime is much shorter by default
    - Categories and feeds will always be sorted alphabetically (the protocol does not allow for clients to re-order)
    - The "Archived" virtual feed is non-functional (the protocol does not allow archiving)
    - The "Published" virtual feed is non-functional (this will not be implemented in the near term)
    - setArticleLabel responds with errors for invalid labels where TT-RSS simply returns a zero result
    - The result of setArticleLabel counts only records which actually changed rather than all entries attempted
    - Using both limit/skip and unread_only in getFeeds produces reliable results, unlike in TT-RSS
    - Top-level categories in getFeedTree have a 'parent_id' property (set to null); in TT-RSS the property is absent
    - Article hashes are SHA-256 rather than SHA-1.
    - Articles have at most one attachment (enclosure), whereas TTRSS allows for several; there is also significantly less detail. These are limitations of picoFeed which should be addressed
    - IDs for enclosures are always 0 as we don't give them IDs
    - Searching in getHeadlines is not yet implemented
    - Category -3 (all non-special feeds) is handled correctly in getHeadlines; TT-RSS returns results for feed -3 (Fresh)
    - Sorting of headlines does not match TT-RSS: special feeds are not sorted specially like they should be
    - The 'sanitize', 'force_update', and 'has_sandbox' parameters of getHeadlines are ignored
*/



class API extends \JKingWeb\Arsse\REST\AbstractHandler {
    const LEVEL = 14;           // emulated API level
    const VERSION = "17.4";     // emulated TT-RSS version
    const LABEL_OFFSET = 1024;  // offset below zero at which labels begin, counting down
    const LIMIT_ARTICLES = 200; // maximum number of articles returned by getHeadlines
    const LIMIT_EXCERPT = 100;  // maximum length of excerpts in getHeadlines, counted in grapheme units
    // special feeds
    const FEED_ARCHIVED = 0;
    const FEED_STARRED = -1;
    const FEED_PUBLISHED = -2;
    const FEED_FRESH = -3;
    const FEED_ALL = -4;
    const FEED_READ = -6;
    // special categories
    const CAT_UNCATEGORIZED = 0;
    const CAT_SPECIAL = -1;
    const CAT_LABELS = -2;
    const CAT_NOT_SPECIAL = -3;
    const CAT_ALL = -4;
    // valid input
    const VALID_INPUT = [
        'op'                  => ValueInfo::T_STRING,                           // the function ("operation") to perform
        'sid'                 => ValueInfo::T_STRING,                           // session ID
        'seq'                 => ValueInfo::T_INT,                              // request number from client
        'user'                => ValueInfo::T_STRING | ValueInfo::M_STRICT,     // user name for `login`
        'password'            => ValueInfo::T_STRING | ValueInfo::M_STRICT,     // password for `login` and `subscribeToFeed`
        'include_empty'       => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to include empty items in `getFeedTree` and `getCategories`
        'unread_only'         => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to exclude items without unread articles in `getCategories` and `getFeeds`
        'enable_nested'       => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to NOT show subcategories in `getCategories
        'include_nested'      => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to include subcategories in `getFeeds` and the articles thereof in `getHeadlines`
        'caption'             => ValueInfo::T_STRING | ValueInfo::M_STRICT,     // name for categories, feed, and labels
        'parent_id'           => ValueInfo::T_INT,                              // parent category for `addCategory` and `moveCategory`
        'category_id'         => ValueInfo::T_INT,                              // parent category for `subscribeToFeed` and `moveFeed`, and subject for category-modification functions
        'cat_id'              => ValueInfo::T_INT,                              // parent category for `getFeeds`
        'label_id'            => ValueInfo::T_INT,                              // label ID in label-related functions
        'feed_url'            => ValueInfo::T_STRING | ValueInfo::M_STRICT,     // URL of feed in `subscribeToFeed`
        'login'               => ValueInfo::T_STRING | ValueInfo::M_STRICT,     // remote user name in `subscribeToFeed`
        'feed_id'             => ValueInfo::T_INT,                              // feed, label, or category ID for various functions
        'is_cat'              => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether 'feed_id' refers to a category
        'article_id'          => ValueInfo::T_MIXED,                            // single article ID in `getLabels`; one or more (comma-separated) article IDs in `getArticle`
        'article_ids'         => ValueInfo::T_STRING,                           // one or more (comma-separated) article IDs in `updateArticle` and `setArticleLabel`
        'assign'              => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to assign or clear (false) a label in `setArticleLabel`
        'limit'               => ValueInfo::T_INT,                              // maximum number of records returned in `getFeeds`, `getHeadlines`, and `getCompactHeadlines`
        'offset'              => ValueInfo::T_INT,                              // number of records to skip in `getFeeds`, for pagination
        'skip'                => ValueInfo::T_INT,                              // number of records to skip in `getHeadlines` and `getCompactHeadlines`, for pagination
        'show_excerpt'        => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to include article excerpts in `getHeadlines`
        'show_content'        => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to include article content in `getHeadlines`
        'include_attachments' => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to include article enclosures in `getHeadlines`
        'view_mode'           => ValueInfo::T_STRING,                           // various filters for `getHeadlines`
        'since_id'            => ValueInfo::T_INT,                              // cut-off article ID for `getHeadlines` and `getCompactHeadlines; returns only higher article IDs when specified
        'order_by'            => ValueInfo::T_STRING,                           // sort order for `getHeadlines`
        'include_header'      => ValueInfo::T_BOOL | ValueInfo::M_DROP,         // whether to attach a header to the results of `getHeadlines`
        'search'              => ValueInfo::T_STRING,                           // search string for `getHeadlines` (not yet implemented)
        'field'               => ValueInfo::T_INT,                              // which state to change in `updateArticle`
        'mode'                => ValueInfo::T_INT,                              // whether to set, clear, or toggle the selected state in `updateArticle`
        'data'                => ValueInfo::T_STRING,                           // note text in `updateArticle` if setting a note
    ];
    // generic error construct
    const FATAL_ERR = [
        'seq'     => null,
        'status'  => 1,
        'content' => ['error' => "MALFORMED_INPUT"],
    ];
    
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        if ($req->method=="OPTIONS") {
            // respond to OPTIONS rquests; the response is a fib, as we technically accept any type or method
            return new Response(204, "", "", [
                "Allow: POST",
                "Accept: application/json, text/json",
            ]);
        }
        if ($req->body) {
            // only JSON entities are allowed, but Content-Type is ignored, as is request method
            $data = @json_decode($req->body, true);
            if (json_last_error() != \JSON_ERROR_NONE || !is_array($data)) {
                return new Response(200, self::FATAL_ERR);
            }
            try {
                // normalize input
                try {
                    $data['seq'] = isset($data['seq']) ? $data['seq'] : 0;
                    $data = $this->normalizeInput($data, self::VALID_INPUT, "unix");
                } catch (ExceptionType $e) {
                    throw new Exception("INCORRECT_USAGE");
                }
                if (strtolower((string) $data['op']) != "login") {
                    // unless logging in, a session identifier is required
                    $this->resumeSession((string) $data['sid']);
                }
                $method = "op".ucfirst($data['op']);
                if (!method_exists($this, $method)) {
                    // TT-RSS operations are case-insensitive by dint of PHP method names being case-insensitive; this will only trigger if the method really doesn't exist
                    throw new Exception("UNKNOWN_METHOD", ['method' => $data['op']]);
                }
                return new Response(200, [
                    'seq' => $data['seq'],
                    'status' => 0,
                    'content' => $this->$method($data),
                ]);
            } catch (Exception $e) {
                return new Response(200, [
                    'seq' => $data['seq'],
                    'status' => 1,
                    'content' => $e->getData(),
                ]);
            } catch (AbstractException $e) {
                return new Response(500);
            }
        } else {
            // absence of a request body indicates an error
            return new Response(200, self::FATAL_ERR);
        }
    }

    protected function resumeSession(string $id): bool {
        try {
            // verify the supplied session is valid
            $s = Arsse::$db->sessionResume($id);
        } catch (\JKingWeb\Arsse\User\ExceptionSession $e) {
            // if not throw an exception
            throw new Exception("NOT_LOGGED_IN");
        }
        // resume the session (currently only the user name)
        Arsse::$user->id = $s['user'];
        return true;
    }

    public function opGetApiLevel(array $data): array {
        return ['level' => self::LEVEL];
    }
    
    public function opGetVersion(array $data): array {
        return [
            'version'       => self::VERSION,
            'arsse_version' => Arsse::VERSION,
        ];
    }

    public function opLogin(array $data): array {
        if (Arsse::$user->auth((string) $data['user'], (string) $data['password'])) {
            $id = Arsse::$db->sessionCreate($data['user']);
            return [
                'session_id' => $id,
                'api_level'  => self::LEVEL
            ];
        } else {
            throw new Exception("LOGIN_ERROR");
        }
    }

    public function opLogout(array $data): array {
        Arsse::$db->sessionDestroy(Arsse::$user->id, $data['sid']);
        return ['status' => "OK"];
    }

    public function opIsLoggedIn(array $data): array {
        // session validity is already checked by the dispatcher, so we need only return true
        return ['status' => true];
    }

    public function opGetConfig(array $data): array {
        return [
            'icons_dir' => "feed-icons",
            'icons_url' => "feed-icons",
            'daemon_is_running' => Service::hasCheckedIn(),
            'num_feeds' => Arsse::$db->subscriptionCount(Arsse::$user->id),
        ];
    }

    public function opGetUnread(array $data): array {
        // simply sum the unread count of each subscription
        $out = 0;
        foreach (Arsse::$db->subscriptionList(Arsse::$user->id) as $sub) {
            $out += $sub['unread'];
        }
        return ['unread' => (string) $out]; // string cast to be consistent with TTRSS
    }

    public function opGetCounters(array $data): array {
        $user = Arsse::$user->id;
        $starred = Arsse::$db->articleStarred($user);
        $fresh = Arsse::$db->articleCount($user, (new Context)->unread(true)->modifiedSince(Date::sub("PT24H")));
        $countAll = 0;
        $countSubs = 0;
        $feeds = [];
        $labels = [];
        // do a first pass on categories: add the ID to a lookup table and set the unread counter to zero
        $categories = Arsse::$db->folderList($user)->getAll();
        $catmap = [];
        for ($a = 0; $a < sizeof($categories); $a++) {
            $catmap[(int) $categories[$a]['id']] = $a;
            $categories[$a]['counter'] = 0;
        }
        // add the "Uncategorized" and "Labels" virtual categories to the list
        $catmap[self::CAT_UNCATEGORIZED] = sizeof($categories);
        $categories[] = ['id' => self::CAT_UNCATEGORIZED, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Uncategorized"), 'parent' => 0, 'children' => 0, 'counter' => 0];
        $catmap[self::CAT_LABELS] = sizeof($categories);
        $categories[] = ['id' => self::CAT_LABELS, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Labels"), 'parent' => 0, 'children' => 0, 'counter' => 0];
        // prepare data for each subscription; we also add unread counts for their host categories
        foreach (Arsse::$db->subscriptionList($user) as $f) {
            if ($f['unread']) {
                // add the feed to the list of feeds
                $feeds[] = ['id' => (string) $f['id'], 'updated' => Date::transform($f['updated'], "iso8601", "sql"),'counter' => $f['unread'], 'has_img' => (int) (strlen((string) $f['favicon']) > 0)]; // ID is cast to string for consistency with TTRSS
                // add the feed's unread count to the global unread count
                $countAll += $f['unread'];
                // add the feed's unread count to its category unread count
                $categories[$catmap[(int) $f['folder']]]['counter'] += $f['unread'];
            }
            // increment the global feed count
            $countSubs += 1;
        }
        // prepare data for each non-empty label
        foreach (Arsse::$db->labelList($user, false) as $l) {
            $unread = $l['articles'] - $l['read'];
            $labels[] = ['id' => $this->labelOut($l['id']), 'counter' => $unread, 'auxcounter' => $l['articles']];
            $categories[$catmap[self::CAT_LABELS]]['counter'] += $unread;
        }
        // do a second pass on categories, summing descendant unread counts for ancestors
        $cats = $categories;
        $catCounts = [];
        while ($cats) {
            foreach ($cats as $c) {
                if ($c['children']) {
                    // only act on leaf nodes
                    continue;
                }
                if ($c['parent']) {
                    // if the category has a parent, add its counter to the parent's counter, and decrement the parent's child count
                    $cats[$catmap[$c['parent']]]['counter'] += $c['counter'];
                    $cats[$catmap[$c['parent']]]['children'] -= 1;
                }
                $catCounts[$c['id']] = $c['counter'];
                // remove the category from the input list
                unset($cats[$catmap[$c['id']]]);
            }
        }
        // do a third pass on categories, building a final category list
        foreach ($categories as $c) {
            // only include categories with unread articles
            if ($catCounts[$c['id']]) {
                $cats[] = ['id' => $c['id'], 'kind' => "cat", 'counter' => $catCounts[$c['id']]];
            }
        }
        // prepare data for the virtual feeds and other counters
        $special = [
            ['id' => "global-unread",      'counter' => $countAll], //this should not count archived articles, but we do not have an archive
            ['id' => "subscribed-feeds",   'counter' => $countSubs],
            ['id' => self::FEED_ARCHIVED,  'counter' => 0, 'auxcounter' => 0], // Archived articles
            ['id' => self::FEED_STARRED,   'counter' => $starred['unread'], 'auxcounter' => $starred['total']], // Starred articles
            ['id' => self::FEED_PUBLISHED, 'counter' => 0, 'auxcounter' => 0], // Published articles
            ['id' => self::FEED_FRESH,     'counter' => $fresh, 'auxcounter' => 0], // Fresh articles
            ['id' => self::FEED_ALL,       'counter' => $countAll, 'auxcounter' => 0], // All articles
        ];
        return array_merge($special, $labels, $feeds, $cats);
    }

    public function opGetFeedTree(array $data) : array {
        $all = $data['include_empty'] ?? false;
        $user = Arsse::$user->id;
        $tSpecial = [
            'type'       => "feed",
            'auxcounter' => 0,
            'error'      => "",
            'updated'    => "",
        ];
        $out = [];
        // get the lists of categories and feeds
        $cats = Arsse::$db->folderList($user, null, true)->getAll();
        $subs = Arsse::$db->subscriptionList($user)->getAll();
        // start with the special feeds
        $out[] = [
            'name' => Arsse::$lang->msg("API.TTRSS.Category.Special"),
            'id' => "CAT:".self::CAT_SPECIAL,
            'bare_id' => self::CAT_SPECIAL,
            'type' => "category",
            'unread' => 0,
            'items' => [
                array_merge([ // All articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.All"),
                    'id' => "FEED:".self::FEED_ALL,
                    'bare_id' => self::FEED_ALL,
                    'icon' => "images/folder.png",
                    'unread' => array_reduce($subs, function ($sum, $value) {
                        return $sum + $value['unread'];
                    }, 0), // the sum of all feeds' unread is the total unread
                ], $tSpecial),
                array_merge([ // Fresh articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Fresh"),
                    'id' => "FEED:".self::FEED_FRESH,
                    'bare_id' => self::FEED_FRESH,
                    'icon' => "images/fresh.png",
                    'unread' => Arsse::$db->articleCount($user, (new Context)->unread(true)->modifiedSince(Date::sub("PT24H"))),
                ], $tSpecial),
                array_merge([ // Starred articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Starred"),
                    'id' => "FEED:".self::FEED_STARRED,
                    'bare_id' => self::FEED_STARRED,
                    'icon' => "images/star.png",
                    'unread' => Arsse::$db->articleStarred($user)['unread'],
                ], $tSpecial),
                array_merge([ // Published articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Published"),
                    'id' => "FEED:".self::FEED_PUBLISHED,
                    'bare_id' => self::FEED_PUBLISHED,
                    'icon' => "images/feed.png",
                    'unread' => 0, // TODO: unread count should be populated if the Published feed is ever implemented
                ], $tSpecial),
                array_merge([ // Archived articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Archived"),
                    'id' => "FEED:".self::FEED_ARCHIVED,
                    'bare_id' => self::FEED_ARCHIVED,
                    'icon' => "images/archive.png",
                    'unread' => 0, // Article archiving is not exposed by the API, so this is always zero
                ], $tSpecial),
                array_merge([ // Recently read
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Read"),
                    'id' => "FEED:".self::FEED_READ,
                    'bare_id' => self::FEED_READ,
                    'icon' => "images/time.png",
                    'unread' => 0, // this is by definition zero; unread articles do not appear in this feed
                ], $tSpecial),
            ],
        ];
        // next prepare labels
        $items = [];
        $unread = 0;
        // add each label to a holding list (NOTE: the 'include_empty' parameter does not affect whether labels with zero total articles are shown: all labels are always shown)
        foreach (Arsse::$db->labelList($user, true) as $l) {
            $items[] = [
                'name'       => $l['name'],
                'id'         => "FEED:".$this->labelOut($l['id']),
                'bare_id'    => $this->labelOut($l['id']),
                'unread'     => 0,
                'icon'       => "images/label.png",
                'type'       => "feed",
                'auxcounter' => 0,
                'error'      => "",
                'updated'    => "",
                'fg_color'   => "",
                'bg_color'   => "",
            ];
            $unread += ($l['articles'] - $l['read']);
        }
        // if there are labels, all the label category,
        if ($items) {
            $out[] = [
                'name' => Arsse::$lang->msg("API.TTRSS.Category.Labels"),
                'id' => "CAT:".self::CAT_LABELS,
                'bare_id' => self::CAT_LABELS,
                'type' => "category",
                'unread' => $unread,
                'items' => $items,
            ];
        }
        // get the lists of categories and feeds
        $cats = Arsse::$db->folderList($user, null, true)->getAll();
        $subs = Arsse::$db->subscriptionList($user)->getAll();
        // process all the top-level categories; their contents are gathered recursively in another function
        $items = $this->enumerateCategories($cats, $subs, null, $all);
        $out = array_merge($out, $items['list']);
        // process uncategorized feeds; exclude the "Uncategorized" category if there are no orphan feeds and we're not displaying empties
        $items = $this->enumerateFeeds($subs, null);
        if ($items || !$all) {
            $out[] = [
                'name'         => Arsse::$lang->msg("API.TTRSS.Category.Uncategorized"),
                'id'           => "CAT:".self::CAT_UNCATEGORIZED,
                'bare_id'      => self::CAT_UNCATEGORIZED,
                'type'         => "category",
                'auxcounter'   => 0,
                'unread'       => 0,
                'child_unread' => 0,
                'checkbox'     => false,
                'parent_id'    => null,
                'param'        => Arsse::$lang->msg("API.TTRSS.FeedCount", sizeof($items)),
                'items'        => $items,
            ];
        }
        // return the result wrapped in some boilerplate
        return ['categories' => ['identifier' => "id", 'label' => "name", 'items' => $out]];
    }

    protected function enumerateFeeds(array $subs, int $parent = null): array {
        $out = [];
        foreach ($subs as $s) {
            if ($s['folder'] != $parent) {
                continue;
            }
            $out[] = [
                'name'       => $s['title'],
                'id'         => "FEED:".$s['id'],
                'bare_id'    => $s['id'],
                'icon'       => $s['favicon'] ? "feed-icons/".$s['id'].".ico" : false,
                'error'      => (string) $s['err_msg'],
                'param'      => Date::transform($s['updated'], "iso8601", "sql"),
                'unread'     => 0,
                'auxcounter' => 0,
                'checkbox'   => false,
                // NOTE: feeds don't have a type property (even though both labels and special feeds do); don't ask me why
            ];
        }
        return $out;
    }

    protected function enumerateCategories(array $cats, array $subs, int $parent = null, bool $all = false): array {
        $out = [];
        $feedTotal = 0;
        foreach ($cats as $c) {
            if ($c['parent'] != $parent || (!$all && !($c['children'] + $c['feeds']))) {
                // if the category is the wrong level, or if it's empty and we're not including empties, skip it
                continue;
            }
            $children  = $c['children'] ? $this->enumerateCategories($cats, $subs, $c['id'], $all) : ['list' => [], 'feeds' => 0];
            $feeds = $c['feeds'] ? $this->enumerateFeeds($subs, $c['id']) : [];
            $count = sizeof($feeds) + $children['feeds'];
            $out[] = [
                'name'         => $c['name'],
                'id'           => "CAT:".$c['id'],
                'bare_id'      => $c['id'],
                'parent_id'    => $c['parent'], // top-level categories are not supposed to have this property; we deviated and have the property set to null because it's simpler that way
                'type'         => "category",
                'auxcounter'   => 0,
                'unread'       => 0,
                'child_unread' => 0,
                'checkbox'     => false,
                'param'        => Arsse::$lang->msg("API.TTRSS.FeedCount", $count),
                'items'        => array_merge($children['list'], $feeds),
            ];
            $feedTotal += $count;
        }
        return ['list' => $out, 'feeds' => $feedTotal];
    }

    public function opGetCategories(array $data): array {
        // normalize input
        $all = $data['include_empty'] ?? false;
        $read = !($data['unread_only'] ?? false);
        $deep = !($data['enable_nested'] ?? false);
        $user = Arsse::$user->id;
        // for each category, add the ID to a lookup table, set the number of unread to zero, and assign an increasing order index
        $cats = Arsse::$db->folderList($user, null, $deep)->getAll();
        $map = [];
        for ($a = 0; $a < sizeof($cats); $a++) {
            $cats[$a]['id'] = (string) $cats[$a]['id']; // real categories have IDs as strings in TTRSS
            $map[$cats[$a]['id']] = $a;
            $cats[$a]['unread'] = 0;
            $cats[$a]['order'] = $a + 1;
        }
        // add the "Uncategorized", "Special", and "Labels" virtual categories to the list
        $map[self::CAT_UNCATEGORIZED] = sizeof($cats);
        $cats[] = ['id' => self::CAT_UNCATEGORIZED, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Uncategorized"), 'children' => 0, 'unread' => 0, 'feeds' => 0];
        $map[self::CAT_SPECIAL] = sizeof($cats);
        $cats[] = ['id' => self::CAT_SPECIAL, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Special"), 'children' => 0, 'unread' => 0, 'feeds' => 6];
        $map[self::CAT_LABELS] = sizeof($cats);
        $cats[] = ['id' => self::CAT_LABELS, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Labels"), 'children' => 0, 'unread' => 0, 'feeds' => 0];
        // for each subscription, add the unread count to its category, and increment the category's feed count
        $subs = Arsse::$db->subscriptionList($user);
        foreach ($subs as $sub) {
            // note we use top_folder if we're in "nested" mode
            $f = $map[(int) ($deep ? $sub['folder'] : $sub['top_folder'])];
            $cats[$f]['unread'] += $sub['unread'];
            if (!$cats[$f]['id']) {
                $cats[$f]['feeds'] += 1;
            }
        }
        // for each label, add the unread count to the labels category, and increment the labels category's feed count
        $labels = Arsse::$db->labelList($user);
        $f = $map[self::CAT_LABELS];
        foreach ($labels as $label) {
            $cats[$f]['unread'] += $label['articles'] - $label['read'];
            $cats[$f]['feeds'] += 1;
        }
        // get the unread counts for the special feeds
        // FIXME: this is pretty inefficient
        $f = $map[self::CAT_SPECIAL];
        $cats[$f]['unread'] += Arsse::$db->articleStarred($user)['unread']; // starred
        $cats[$f]['unread'] += Arsse::$db->articleCount($user, (new Context)->unread(true)->modifiedSince(Date::sub("PT24H"))); // fresh
        if (!$read) {
            // if we're only including unread entries, remove any categories with zero unread items (this will by definition also exclude empties)
            $count = sizeof($cats);
            for ($a = 0; $a < $count; $a++) {
                if (!$cats[$a]['unread']) {
                    unset($cats[$a]);
                }
            }
            $cats = array_values($cats);
        } elseif (!$all) {
            // otherwise if we're not including empty entries, remove categories with no children and no feeds
            $count = sizeof($cats);
            for ($a = 0; $a < $count; $a++) {
                if (($cats[$a]['children'] + $cats[$a]['feeds']) < 1) {
                    unset($cats[$a]);
                }
            }
            $cats = array_values($cats);
        }
        // transform the result and return
        $out = [];
        for ($a = 0; $a < sizeof($cats); $a++) {
            if ($cats[$a]['id']==-2) {
                // the Labels category has its unread count as a string in TTRSS (don't ask me why)
                settype($cats[$a]['unread'], "string");
            }
            $out[] = $this->fieldMapNames($cats[$a], [
                'id'       => "id",
                'title'    => "name",
                'unread'   => "unread",
                'order_id' => "order",
            ]);
        }
        return $out;
    }

    public function opAddCategory(array $data) {
        $in = [
            'name'   => $data['caption'],
            'parent' => $data['parent_id'],
        ];
        try {
            return (string) Arsse::$db->folderAdd(Arsse::$user->id, $in); // output is a string in TTRSS
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10236: // folder already exists
                    // retrieve the ID of the existing folder; duplicating a folder silently returns the existing one
                    $folders = Arsse::$db->folderList(Arsse::$user->id, $in['parent'], false);
                    foreach ($folders as $folder) {
                        if ($folder['name']==$in['name']) {
                            return (string) ((int) $folder['id']); // output is a string in TTRSS
                        }
                    }
                    return false; // @codeCoverageIgnore
                case 10235: // parent folder does not exist; this returns false as an ID
                    return false;
                default: // other errors related to input
                    throw new Exception("INCORRECT_USAGE");
            }
        }
    }

    public function opRemoveCategory(array $data) {
        if (!ValueInfo::id($data['category_id'])) {
            // if the folder is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        try {
            // attempt to remove the folder
            Arsse::$db->folderRemove(Arsse::$user->id, (int) $data['category_id']);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opMoveCategory(array $data) {
        if (!ValueInfo::id($data['category_id']) || !ValueInfo::id($data['parent_id'], true)) {
            // if the folder or parent is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'parent' => (int) $data['parent_id'],
        ];
        try {
            // try to move the folder
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, (int) $data['category_id'], $in);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opRenameCategory(array $data) {
        $info = ValueInfo::str($data['caption']);
        if (!ValueInfo::id($data['category_id']) || !($info & ValueInfo::VALID) || ($info & ValueInfo::EMPTY) || ($info & ValueInfo::WHITE)) {
            // if the folder or its new name are invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'name' => $data['caption'],
        ];
        try {
            // try to rename the folder
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, $data['category_id'], $in);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opGetFeeds(array $data): array {
        $user = Arsse::$user->id;
        // normalize input
        $cat = $data['cat_id'] ?? 0;
        $unread = $data['unread_only'] ?? false;
        $limit = $data['limit'] ?? 0;
        $offset = $data['offset'] ?? 0;
        $nested = $data['include_nested'] ?? false;
        // if a special category was selected, nesting does not apply
        if (!ValueInfo::id($cat)) {
            $nested = false;
            // if the All, Special, or Labels category was selected, pagination also does not apply
            if (in_array($cat, [self::CAT_ALL, self::CAT_SPECIAL, self::CAT_LABELS])) {
                $limit = 0;
                $offset = 0;
            }
        }
        // retrieve or build the list of relevant feeds
        $out = [];
        $subs = [];
        $count = 0;
        // if the category is the special Labels category or the special All category (which includes labels), add labels to the list
        if ($cat==self::CAT_ALL || $cat==self::CAT_LABELS) {
            // NOTE: unused labels are not included
            foreach (Arsse::$db->labelList($user, false) as $l) {
                if ($unread && !$l['unread']) {
                    continue;
                }
                $out[] = [
                    'id'     => $this->labelOut($l['id']),
                    'title'  => $l['name'],
                    'unread' => (string) $l['unread'], // the unread count of labels is output as a string in TTRSS
                    'cat_id' => self::CAT_LABELS,
                ];
            }
        }
        // if the category is the special Special (!) category or the special All category (which includes "special" feeds), add those feeds to the list
        if ($cat==self::CAT_ALL || $cat==self::CAT_SPECIAL) {
            // gather some statistics
            $starred = Arsse::$db->articleStarred($user)['unread'];
            $fresh = Arsse::$db->articleCount($user, (new Context)->unread(true)->modifiedSince(Date::sub("PT24H")));
            $global = Arsse::$db->articleCount($user, (new Context)->unread(true));
            $published = 0; // TODO: if the Published feed is implemented, the getFeeds method needs to be adjusted accordingly
            $archived = 0; // the archived feed is non-functional in the TT-RSS protocol itself
            // build the list; exclude anything with zero unread if requested
            if (!$unread || $starred) {
                $out[] = [
                    'id'     => self::FEED_STARRED,
                    'title'  => Arsse::$lang->msg("API.TTRSS.Feed.Starred"),
                    'unread' => (string) $starred, // output is a string in TTRSS
                    'cat_id' => self::CAT_SPECIAL,
                ];
            }
            if (!$unread || $published) {
                $out[] = [
                    'id'     => self::FEED_PUBLISHED,
                    'title'  => Arsse::$lang->msg("API.TTRSS.Feed.Published"),
                    'unread' => (string) $published, // output is a string in TTRSS
                    'cat_id' => self::CAT_SPECIAL,
                ];
            }
            if (!$unread || $fresh) {
                $out[] = [
                    'id'     => self::FEED_FRESH,
                    'title'  => Arsse::$lang->msg("API.TTRSS.Feed.Fresh"),
                    'unread' => (string) $fresh, // output is a string in TTRSS
                    'cat_id' => self::CAT_SPECIAL,
                ];
            }
            if (!$unread || $global) {
                $out[] = [
                    'id'     => self::FEED_ALL,
                    'title'  => Arsse::$lang->msg("API.TTRSS.Feed.All"),
                    'unread' => (string) $global, // output is a string in TTRSS
                    'cat_id' => self::CAT_SPECIAL,
                ];
            }
            if (!$unread) {
                $out[] = [
                    'id'     => self::FEED_READ,
                    'title'  => Arsse::$lang->msg("API.TTRSS.Feed.Read"),
                    'unread' => 0, // zero by definition; this one is -NOT- a string in TTRSS
                    'cat_id' => self::CAT_SPECIAL,
                ];
            }
            if (!$unread || $archived) {
                $out[] = [
                    'id'     => self::FEED_ARCHIVED,
                    'title'  => Arsse::$lang->msg("API.TTRSS.Feed.Archived"),
                    'unread' => (string) $archived, // output is a string in TTRSS
                    'cat_id' => self::CAT_SPECIAL,
                ];
            }
        }
        // categories and real feeds have a sequential order index; we don't store this, so we just increment with each entry from here
        $order = 0;
        // if a "nested" list was requested, append the category's child categories to the putput
        if ($nested) {
            try {
                // NOTE: the list is a flat one: it includes children, but not other descendents
                foreach (Arsse::$db->folderList($user, $cat, false) as $c) {
                    // get the number of unread for the category and its descendents; those with zero unread are excluded in "unread-only" mode
                    $count = Arsse::$db->articleCount($user, (new Context)->unread(true)->folder($c['id']));
                    if (!$unread || $count) {
                        $out[] = [
                            'id' => $c['id'],
                            'title' => $c['name'],
                            'unread' => $count,
                            'is_cat' => true,
                            'order_id' => ++$order,
                        ];
                    }
                }
            } catch (ExceptionInput $e) {
                // in case of errors (because the category does not exist) return the list so far (which should be empty)
                return $out;
            }
        }
        try {
            if ($cat==self::CAT_NOT_SPECIAL || $cat==self::CAT_ALL) {
                // if the "All" or "Not Special" categories were selected this returns all subscription, to any depth
                $subs = Arsse::$db->subscriptionList($user, null, true);
            } elseif ($cat==self::CAT_UNCATEGORIZED) {
                // the "Uncategorized" special category returns subscriptions in the root, without going deeper
                $subs = Arsse::$db->subscriptionList($user, null, false);
            } else {
                // other categories return their subscriptions, without going deeper
                $subs = Arsse::$db->subscriptionList($user, $cat, false);
            }
        } catch (ExceptionInput $e) {
            // in case of errors (invalid category), return what we have so far
            return $out;
        }
        // append subscriptions to the output
        $order = 0;
        $count = 0;
        foreach ($subs as $s) {
            $order++;
            if ($unread && !$s['unread']) {
                // ignore any subscriptions with zero unread in "unread-only" mode
                continue;
            } elseif ($offset > 0) {
                // skip as many subscriptions as necessary to remove any requested offset
                $offset--;
                continue;
            } elseif ($limit && $count >= $limit) {
                // if we've reached the requested limit, stop
                // NOTE: TT-RSS blindly accepts negative limits and returns an empty array
                break;
            }
            // otherwise, append the subscription
            $out[] = [
                'id'           => $s['id'],
                'title'        => $s['title'],
                'unread'       => $s['unread'],
                'cat_id'       => (int) $s['folder'],
                'feed_url'     => $s['url'],
                'has_icon'     => (bool) $s['favicon'],
                'last_updated' => (int) Date::transform($s['updated'], "unix", "sql"),
                'order_id'     => $order,
            ];
            $count++;
        }
        return $out;
    }

    protected function feedError(FeedException $e): array {
        // N.B.: we don't return code 4 (multiple feeds discovered); we simply pick the first feed discovered
        switch ($e->getCode()) {
            case 10502: // invalid URL
                return ['code' => 2, 'message' => $e->getMessage()];
            case 10521: // no feeds discovered
                return ['code' => 3, 'message' => $e->getMessage()];
            case 10511:
            case 10512:
            case 10522: // malformed data
                return ['code' => 6, 'message' => $e->getMessage()];
            default: // unable to download
                return ['code' => 5, 'message' => $e->getMessage()];
        }
    }

    public function opSubscribeToFeed(array $data): array {
        if (!$data['feed_url'] || !ValueInfo::id($data['category_id'], true)) {
            // if the feed URL or the category ID is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $url = (string) $data['feed_url'];
        $folder = (int) $data['category_id'];
        $fetchUser = (string) $data['login'];
        $fetchPassword = (string) $data['password'];
        // check to make sure the requested folder exists before doing anything else, if one is specified
        if ($folder) {
            try {
                Arsse::$db->folderPropertiesGet(Arsse::$user->id, $folder);
            } catch (ExceptionInput $e) {
                // folder does not exist: TT-RSS is a bit weird in this case and returns a feed ID of 0. It checks the feed first, but we do not
                return ['code' => 1, 'feed_id' => 0];
            }
        }
        try {
            $id = Arsse::$db->subscriptionAdd(Arsse::$user->id, $url, $fetchUser, $fetchPassword);
        } catch (ExceptionInput $e) {
            // subscription already exists; retrieve the existing ID and return that with the correct code
            for ($triedDiscovery = 0; $triedDiscovery <= 1; $triedDiscovery++) {
                $subs = Arsse::$db->subscriptionList(Arsse::$user->id);
                $id = false;
                foreach ($subs as $sub) {
                    if ($sub['url']===$url) {
                        $id = (int) $sub['id'];
                        break;
                    }
                }
                if ($id) {
                    break;
                } elseif (!$triedDiscovery) {
                    // if we didn't find the ID we perform feed discovery for the next iteration; this is pretty messy: discovery ends up being done twice because it was already done in $db->subscriptionAdd()
                    try {
                        $url = Feed::discover($url, $fetchUser, $fetchPassword);
                    } catch (FeedException $e) {
                        // feed errors (handled above)
                        return $this->feedError($e);
                    }
                }
            }
            return ['code' => 0, 'feed_id' => $id];
        } catch (FeedException $e) {
            // feed errors (handled above)
            return $this->feedError($e);
        }
        // if all went well, move the new subscription to the requested folder (if one was requested)
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['folder' => $folder]);
        } catch (ExceptionInput $e) {
            // ignore errors
        }
        return ['code' => 1, 'feed_id' => $id];
    }

    public function opUnsubscribeFeed(array $data): array {
        try {
            // attempt to remove the feed
            Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $data['feed_id']);
        } catch (ExceptionInput $e) {
            throw new Exception("FEED_NOT_FOUND");
        }
        return ['status' => "OK"];
    }

    public function opMoveFeed(array $data) {
        if (!ValueInfo::id($data['feed_id']) || !isset($data['category_id']) || !ValueInfo::id($data['category_id'], true)) {
            // if the feed or folder is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'folder' => $data['category_id'],
        ];
        try {
            // try to move the feed
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $data['feed_id'], $in);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opRenameFeed(array $data) {
        $info = ValueInfo::str($data['caption']);
        if (!ValueInfo::id($data['feed_id']) || !($info & ValueInfo::VALID) || ($info & ValueInfo::EMPTY) || ($info & ValueInfo::WHITE)) {
            // if the feed ID or name is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        $in = [
            'title' => $data['caption'],
        ];
        try {
            // try to rename the feed
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $data['feed_id'], $in);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opUpdateFeed(array $data): array {
        if (!isset($data['feed_id']) || !ValueInfo::id($data['feed_id'])) {
            // if the feed is invalid, throw an error
            throw new Exception("INCORRECT_USAGE");
        }
        try {
            Arsse::$db->feedUpdate(Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, $data['feed_id'])['feed']);
        } catch (ExceptionInput $e) {
            throw new Exception("FEED_NOT_FOUND");
        }
        return ['status' => "OK"];
    }

    protected function labelIn($id, bool $throw = true): int {
        if (!(ValueInfo::int($id) & ValueInfo::NEG) || $id > (-1 - self::LABEL_OFFSET)) {
            if ($throw) {
                throw new Exception("INCORRECT_USAGE");
            } else {
                return 0;
            }
        }
        return (abs($id) - self::LABEL_OFFSET);
    }

    protected function labelOut(int $id): int {
        return ($id * -1 - self::LABEL_OFFSET);
    }

    public function opGetLabels(array $data): array {
        // this function doesn't complain about invalid article IDs
        $article = ValueInfo::id($data['article_id']) ? $data['article_id'] : 0;
        try {
            $list = $article ? Arsse::$db->articleLabelsGet(Arsse::$user->id, $article) : [];
        } catch (ExceptionInput $e) {
            $list = [];
        }
        $out = [];
        foreach (Arsse::$db->labelList(Arsse::$user->id) as $l) {
            $out[] = [
                'id'       => $this->labelOut($l['id']),
                'caption'  => $l['name'],
                'fg_color' => "",
                'bg_color' => "",
                'checked'  => in_array($l['id'], $list),
            ];
        }
        return $out;
    }

    public function opAddLabel(array $data) {
        $in = [
            'name'   => (string) $data['caption'],
        ];
        try {
            return $this->labelOut(Arsse::$db->labelAdd(Arsse::$user->id, $in));
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10236: // label already exists
                    // retrieve the ID of the existing label; duplicating a label silently returns the existing one
                     return $this->labelOut(Arsse::$db->labelPropertiesGet(Arsse::$user->id, $in['name'], true)['id']);
                default: // other errors related to input
                    throw new Exception("INCORRECT_USAGE");
            }
        }
    }

    public function opRemoveLabel(array $data) {
        // normalize the label ID; missing or invalid IDs are rejected
        $id = $this->labelIn($data['label_id']);
        try {
            // attempt to remove the label
            Arsse::$db->labelRemove(Arsse::$user->id, $id);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        return null;
    }

    public function opRenameLabel(array $data) {
        // normalize input; missing or invalid IDs are rejected
        $id = $this->labelIn($data['label_id']);
        $name = (string) $data['caption'];
        try {
            // try to rename the folder
            Arsse::$db->labelPropertiesSet(Arsse::$user->id, $id, ['name' => $name]);
        } catch (ExceptionInput $e) {
            if ($e->getCode()==10237) {
                // if the supplied ID was invalid, report an error; other errors are to be ignored
                throw new Exception("INCORRECT_USAGE");
            }
        }
        return null;
    }

    public function opSetArticleLabel(array $data): array {
        $label = $this->labelIn($data['label_id']);
        $articles = explode(",", (string) $data['article_ids']);
        $assign = $data['assign'] ?? false;
        $out = 0;
        $in = array_chunk($articles, 50);
        for ($a = 0; $a < sizeof($in); $a++) {
            // initialize the matching context
            $c = new Context;
            $c->articles($in[$a]);
            try {
                $out += Arsse::$db->labelArticlesSet(Arsse::$user->id, $label, $c, !$assign);
            } catch (ExceptionInput $e) {
            }
        }
        return ['status' => "OK", 'updated' => $out];
    }

    public function opCatchUpFeed(array $data): array {
        $id = $data['feed_id'] ?? self::FEED_ARCHIVED;
        $cat = $data['is_cat'] ?? false;
        $out = ['status' => "OK"];
        // first prepare the context; unsupported contexts simply return early
        $c = new Context;
        if ($cat) { // categories
            switch ($id) {
                case self::CAT_SPECIAL:
                case self::CAT_NOT_SPECIAL:
                case self::CAT_ALL:
                    // not valid
                    return $out;
                case self::CAT_UNCATEGORIZED:
                    // this requires a shallow context since in TTRSS the zero/null folder ("Uncategorized") is apart from the tree rather than at the root
                    $c->folderShallow(0);
                    break;
                case self::CAT_LABELS:
                    $c->labelled(true);
                    break;
                default:
                    // any actual category
                    $c->folder($id);
                    break;
            }
        } else { // feeds
            if ($this->labelIn($id, false)) { // labels
                $c->label($this->labelIn($id));
            } else {
                switch ($id) {
                    case self::FEED_ARCHIVED:
                        // not implemented (also, evidently, not implemented in TTRSS)
                        return $out;
                    case self::FEED_STARRED:
                        $c->starred(true);
                        break;
                    case self::FEED_PUBLISHED:
                        // not implemented
                        // TODO: if the Published feed is implemented, the catchup function needs to be modified accordingly
                        return $out;
                    case self::FEED_FRESH:
                        $c->modifiedSince(Date::sub("PT24H"));
                        break;
                    case self::FEED_ALL:
                        // no context needed here
                        break;
                    case self::FEED_READ:
                        // everything in the Recently read feed is, by definition, already read
                        return $out;
                    default:
                        // any actual feed
                        $c->subscription($id);
                }
            }
        }
        // perform the marking
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        } catch (ExceptionInput $e) {
            // ignore all errors
        }
        // return boilerplate output
        return $out;
    }

    public function opUpdateArticle(array $data): array {
        // normalize input
        $articles = array_filter(ValueInfo::normalize(explode(",", (string) $data['article_ids']), ValueInfo::T_INT | ValueInfo::M_ARRAY), [ValueInfo::class, "id"]);
        if (!$articles) {
            // if there are no valid articles this is an error
            throw new Exception("INCORRECT_USAGE");
        }
        $out = 0;
        $tr = Arsse::$db->begin();
        switch ($data['field']) {
            case 0: // starred
                switch ($data['mode']) {
                    case 0: // set false
                    case 1: // set true
                        $out += Arsse::$db->articleMark(Arsse::$user->id, ['starred' => (bool) $data['mode']], (new Context)->articles($articles));
                        break;
                    case 2: //toggle
                        $out += Arsse::$db->articleMark(Arsse::$user->id, ['starred' => true], (new Context)->articles($articles)->starred(false));
                        $out += Arsse::$db->articleMark(Arsse::$user->id, ['starred' => false], (new Context)->articles($articles)->starred(true));
                        break;
                    default:
                        throw new Exception("INCORRECT_USAGE");
                }
                break;
            case 1: // published
                switch ($data['mode']) {
                    case 0: // set false
                    case 1: // set true
                    case 2: //toggle
                        // TODO: the Published feed is not yet implemeted; once it is the updateArticle operation must be amended accordingly
                        break;
                    default:
                        throw new Exception("INCORRECT_USAGE");
                }
                break;
            case 2: // unread
                // NOTE: we use a "read" flag rather than "unread", so the booleans are swapped
                switch ($data['mode']) {
                    case 0: // set false
                    case 1: // set true
                        $out += Arsse::$db->articleMark(Arsse::$user->id, ['read' => !$data['mode']], (new Context)->articles($articles));
                        break;
                    case 2: //toggle
                        $out += Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], (new Context)->articles($articles)->unread(true));
                        $out += Arsse::$db->articleMark(Arsse::$user->id, ['read' => false], (new Context)->articles($articles)->unread(false));
                        break;
                    default:
                        throw new Exception("INCORRECT_USAGE");
                }
                break;
            case 3: // article note
                $out += Arsse::$db->articleMark(Arsse::$user->id, ['note' => (string) $data['data']], (new Context)->articles($articles));
                break;
            default:
                throw new Exception("INCORRECT_USAGE");
        }
        $tr->commit();
        return ['status' => "OK", 'updated' => $out];
    }

    public function opGetArticle(array $data): array {
        // normalize input
        $articles = array_filter(ValueInfo::normalize(explode(",", (string) $data['article_id']), ValueInfo::T_INT | ValueInfo::M_ARRAY), [ValueInfo::class, "id"]);
        if (!$articles) {
            // if there are no valid articles this is an error
            throw new Exception("INCORRECT_USAGE");
        }
        $tr = Arsse::$db->begin();
        // retrieve the list of label names for the user
        $labels = [];
        foreach (Arsse::$db->labelList(Arsse::$user->id, false) as $label) {
            $labels[$label['id']] = $label['name'];
        }
        // retrieve the requested articles
        $out = [];
        foreach (Arsse::$db->articleList(Arsse::$user->id, (new Context)->articles($articles)) as $article) {
            $out[] = [
                'id' => (string) $article['id'], // string cast to be consistent with TTRSS
                'guid' => $article['guid'] ? "SHA256:".$article['guid'] : null,
                'title' => $article['title'],
                'link' => $article['url'],
                'labels' => $this->articleLabelList($labels, $article['id']),
                'unread' => (bool) $article['unread'],
                'marked' => (bool) $article['starred'],
                'published' => false, // TODO: if the Published feed is implemented, the getArticle operation should be amended accordingly
                'comments' => "", // FIXME: What is this?
                'author' => $article['author'],
                'updated' => Date::transform($article['edited_date'], "unix", "sql"),
                'feed_id' => (string) $article['subscription'], // string cast to be consistent with TTRSS
                'feed_title' => $article['subscription_title'],
                'attachments' => $article['media_url'] ? [[
                    'id' => (string) 0, // string cast to be consistent with TTRSS; nonsense ID because we don't use them for enclosures
                    'content_url' => $article['media_url'],
                    'content_type' => $article['media_type'],
                    'title' => "",
                    'duration' => "",
                    'width' => "",
                    'height' => "",
                    'post_id' => (string) $article['id'], // string cast to be consistent with TTRSS
                ]] : [], // TODO: We need to support multiple enclosures
                'score' => 0, // score is not implemented as it is not modifiable from the TTRSS API
                'note' => strlen((string) $article['note']) ? $article['note'] : null,
                'lang' => "", // FIXME: picoFeed should be able to retrieve this information
                'content' => $article['content'],
            ];
        }
        return $out;
    }

    protected function articleLabelList(array $labels, int $id): array {
        $out = [];
        if (!$labels) {
            return $out;
        }
        foreach (Arsse::$db->articleLabelsGet(Arsse::$user->id, $id) as $label) {
            $out[] = [
                $this->labelOut($label), // ID
                $labels[$label],         // name
                "",                      // foreground colour
                "",                      // background colour
            ];
        }
        return $out;
    }

    public function opGetCompactHeadlines(array $data): array {
        // getCompactHeadlines supports fewer features than getHeadlines
        $data = [
            'feed_id'   => $data['feed_id'],
            'view_mode' => $data['view_mode'],
            'since_id'  => $data['since_id'],
            'limit'     => $data['limit'],
            'skip'      => $data['skip'],
        ];
        $data = $this->normalizeInput($data, self::VALID_INPUT, "unix");
        // fetch the list of IDs
        $out = [];
        try {
            foreach ($this->fetchArticles($data, Database::LIST_MINIMAL) as $row) {
                $out[] = ['id' => $row['id']];
            }
        } catch (ExceptionInput $e) {
            // ignore database errors (feeds/categories that don't exist)
        }
        return $out;
    }

    public function opGetHeadlines(array $data): array {
        // normalize input
        $data['limit'] = max(min(!$data['limit'] ? self::LIMIT_ARTICLES : $data['limit'], self::LIMIT_ARTICLES), 0); // at most 200; not specified/zero yields 200; negative values yield no limit
        $tr = Arsse::$db->begin();
        // retrieve the list of label names for the user
        $labels = [];
        foreach (Arsse::$db->labelList(Arsse::$user->id, false) as $label) {
            $labels[$label['id']] = $label['name'];
        }
        // retrieve the requested articles
        $out = [];
        try {
            foreach ($this->fetchArticles($data, Database::LIST_FULL) as $article) {
                $row = [
                    'id' => $article['id'],
                    'guid' => $article['guid'] ? "SHA256:".$article['guid'] : "",
                    'title' => $article['title'],
                    'link' => $article['url'],
                    'labels' => $this->articleLabelList($labels, $article['id']),
                    'unread' => (bool) $article['unread'],
                    'marked' => (bool) $article['starred'],
                    'published' => false, // TODO: if the Published feed is implemented, the getHeadlines operation should be amended accordingly
                    'author' => $article['author'],
                    'updated' => Date::transform($article['edited_date'], "unix", "sql"),
                    'is_updated' => ($article['published_date'] < $article['edited_date']),
                    'feed_id' => (string) $article['subscription'], // string cast to be consistent with TTRSS
                    'feed_title' => $article['subscription_title'],
                    'score' => 0, // score is not implemented as it is not modifiable from the TTRSS API
                    'note' => strlen((string) $article['note']) ? $article['note'] : null,
                    'lang' => "", // FIXME: picoFeed should be able to retrieve this information
                    'tags' => Arsse::$db->articleCategoriesGet(Arsse::$user->id, $article['id']),
                    'comments_count' => 0,
                    'comments_link' => "",
                    'always_display_attachments' => false,
                ];
                if ($data['show_content']) {
                    $row['content'] = $article['content'];
                }
                if ($data['show_excerpt']) {
                    // prepare an excerpt from the content
                    $text = strip_tags($article['content']); // get rid of all tags; elements with problematic content (e.g. script, style) should already be gone thanks to sanitization
                    $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, "UTF-8");
                    $text = trim($text); // trim whitespace at ends
                    $text = preg_replace("<\s+>s", " ", $text); // replace runs of whitespace with a single space
                    $row['excerpt'] = grapheme_substr($text, 0, self::LIMIT_EXCERPT).(grapheme_strlen($text) > self::LIMIT_EXCERPT ? "…" : ""); // add an ellipsis if the string is longer than N characters
                }
                if ($data['include_attachments']) {
                    $row['attachments'] = $article['media_url'] ? [[
                        'id' => (string) 0, // string cast to be consistent with TTRSS; nonsense ID because we don't use them for enclosures
                        'content_url' => $article['media_url'],
                        'content_type' => $article['media_type'],
                        'title' => "",
                        'duration' => "",
                        'width' => "",
                        'height' => "",
                        'post_id' => (string) $article['id'], // string cast to be consistent with TTRSS
                    ]] : []; // TODO: We need to support multiple enclosures
                }
                $out[] = $row;
            }
        } catch (ExceptionInput $e) {
            // ignore database errors (feeds/categories that don't exist)
            // ensure that if using a header the database is not needlessly queried again
            $data['skip'] = null;
        }
        if ($data['include_header']) {
            if ($data['skip'] > 0 && $data['order_by'] != "date_reverse") {
                // when paginating the header returns the latest ("first") item ID in the full list; we get this ID here
                $data['skip'] = 0;
                $data['limit'] = 1;
                $firstID = ($this->fetchArticles($data, Database::LIST_MINIMAL)->getRow() ?? ['id' => 0])['id'];
            } elseif ($data['order_by']=="date_reverse") {
                // the "date_reverse" sort order doesn't get a first ID because it's meaningless for ascending-order pagination (pages doesn't go stale)
                $firstID = 0;
            } else {
                // otherwise just use the ID of the first item in the list we've already computed
                $firstID = ($out) ? $out[0]['id'] : 0;
            }
            // wrap the output with (but after) the header
            $out = [
                [
                    'id'       => $data['feed_id'],
                    'is_cat'   => $data['is_cat'] ?? false,
                    'first_id' => $firstID,
                ],
                $out,
            ];
        }
        return $out;
    }

    protected function fetchArticles(array $data, int $fields): \JKingWeb\Arsse\Db\Result {
        // normalize input
        if (is_null($data['feed_id'])) {
            throw new Exception("INCORRECT_USAGE");
        }
        $id = $data['feed_id'];
        $cat = $data['is_cat'] ?? false;
        $shallow = !($data['include_nested'] ?? false);
        $viewMode = in_array($data['view_mode'], ["all_articles", "adaptive", "unread", "marked", "has_note", "published"]) ? $data['view_mode'] : "all_articles";
        // prepare the context; unsupported, invalid, or inherently empty contexts return synthetic empty result sets
        $c = new Context;
        $tr = Arsse::$db->begin();
        // start with the feed or category ID
        if ($cat) { // categories
            switch ($id) {
                case self::CAT_SPECIAL:
                    // not valid
                    return new ResultEmpty;
                case self::CAT_NOT_SPECIAL:
                case self::CAT_ALL:
                    // no context needed here
                    break;
                case self::CAT_UNCATEGORIZED:
                    // this requires a shallow context since in TTRSS the zero/null folder ("Uncategorized") is apart from the tree rather than at the root
                    $c->folderShallow(0);
                    break;
                case self::CAT_LABELS:
                    $c->labelled(true);
                    break;
                default:
                    // any actual category
                    if ($shallow) {
                        $c->folderShallow($id);
                    } else {
                        $c->folder($id);
                    }
                    break;
            }
        } else { // feeds
            if ($this->labelIn($id, false)) { // labels
                $c->label($this->labelIn($id));
            } else {
                switch ($id) {
                    case self::FEED_ARCHIVED:
                        // not implemented
                        return new ResultEmpty;
                    case self::FEED_STARRED:
                        $c->starred(true);
                        break;
                    case self::FEED_PUBLISHED:
                        // not implemented
                        // TODO: if the Published feed is implemented, the headline function needs to be modified accordingly
                        return new ResultEmpty;
                    case self::FEED_FRESH:
                        $c->modifiedSince(Date::sub("PT24H"))->unread(true);
                        break;
                    case self::FEED_ALL:
                        // no context needed here
                        break;
                    case self::FEED_READ:
                        $c->markedSince(Date::sub("PT24H"))->unread(false); // FIXME: this selects any recently touched article which is read, not necessarily a recently read one
                        break;
                    default:
                        // any actual feed
                        $c->subscription($id);
                        break;
                }
            }
        }
        // next handle the view mode
        switch ($viewMode) {
            case "all_articles":
                // no context needed here
                break;
            case "adaptive":
                // adaptive means "return only unread unless there are none, in which case return all articles"
                if ($c->unread !== false && Arsse::$db->articleCount(Arsse::$user->id, (clone $c)->unread(true))) {
                    $c->unread(true);
                }
                break;
            case "unread":
                if ($c->unread !== false) {
                    $c->unread(true);
                } else {
                    // unread mode in the "Recently Read" feed is a no-op
                    return new ResultEmpty;
                }
                break;
            case "marked":
                $c->starred(true);
                break;
            case "has_note":
                $c->annotated(true);
                break;
            case "published":
                // not implemented
                // TODO: if the Published feed is implemented, the headline function needs to be modified accordingly
                return new ResultEmpty;
            default:
                throw new \JKingWeb\Arsse\Exception("constantUnknown", $viewMode); // @codeCoverageIgnore
        }
        // TODO: implement searching
        // handle sorting
        switch ($data['order_by']) {
            case "date_reverse":
                // sort oldest first
                $c->reverse(false);
                break;
            case "feed_dates":
                // sort newest first
                $c->reverse(true);
                break;
            default:
                // in TT-RSS the default sort order is unusual for some of the special feeds; we do not implement this
                $c->reverse(true);
                break;
        }
        // set the limit and offset
        if ($data['limit'] > 0) {
            $c->limit($data['limit']);
        }
        if ($data['skip'] > 0) {
            $c->offset($data['skip']);
        }
        // set the minimum article ID
        if ($data['since_id'] > 0) {
            $c->oldestArticle($data['since_id'] + 1);
        }
        // return results
        return Arsse::$db->articleList(Arsse::$user->id, $c, $fields);
    }
}
