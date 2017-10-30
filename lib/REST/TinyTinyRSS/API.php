<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST\TinyTinyRSS;

use JKingWeb\Arsse\Feed;
use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\Context;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\ExceptionType;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\REST\Response;

/*

Protocol difference so far:
    - Handling of incorrect Content-Type and/or HTTP method is different
    - TT-RSS accepts whitespace-only names; we do not
    - TT-RSS allows two folders to share the same name under the same parent; we do not
    - Session lifetime is much shorter by default
    - Categories and feeds will always be sorted alphabetically (the protocol does not allow for clients to re-order)
    - The "Archived" virtual feed is non-functional (the protocol does not allow archiving)
    - The "Published" virtual feed is non-functional (this will not be implemented in the near term)
    - setArticleLabel responds with errors for invalid labels where TT-RSS simply returns a zero result
    - The result of setArticleLabel counts only records which actually changed rather than all entries attempted
*/



class API extends \JKingWeb\Arsse\REST\AbstractHandler {
    const LEVEL = 14;
    const VERSION = "17.4";
    const LABEL_OFFSET = 1024;
    const VALID_INPUT = [
        'op'                  => ValueInfo::T_STRING,
        'sid'                 => ValueInfo::T_STRING,
        'seq'                 => ValueInfo::T_INT,
        'user'                => ValueInfo::T_STRING | ValueInfo::M_STRICT,
        'password'            => ValueInfo::T_STRING | ValueInfo::M_STRICT,
        'include_empty'       => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'unread_only'         => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'enable_nested'       => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'caption'             => ValueInfo::T_STRING | ValueInfo::M_STRICT,
        'parent_id'           => ValueInfo::T_INT,
        'category_id'         => ValueInfo::T_INT,
        'feed_url'            => ValueInfo::T_STRING | ValueInfo::M_STRICT,
        'login'               => ValueInfo::T_STRING | ValueInfo::M_STRICT,
        'feed_id'             => ValueInfo::T_INT,
        'article_id'          => ValueInfo::T_INT,
        'label_id'            => ValueInfo::T_INT,
        'article_ids'         => ValueInfo::T_STRING,
        'assign'              => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'is_cat'              => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'cat_id'              => ValueInfo::T_INT,
        'limit'               => ValueInfo::T_INT,
        'offset'              => ValueInfo::T_INT,
        'include_nested'      => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'skip'                => ValueInfo::T_INT,
        'filter'              => ValueInfo::T_STRING,
        'show_excerpt'        => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'show_content'        => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'view_mode'           => ValueInfo::T_STRING,
        'include_attachments' => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'since_id'            => ValueInfo::T_INT,
        'order_by'            => ValueInfo::T_STRING,
        'sanitize'            => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'force_update'        => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'has_sandbox'         => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'include_header'      => ValueInfo::T_BOOL | ValueInfo::M_DROP,
        'search'              => ValueInfo::T_STRING,
        'search_mode'         => ValueInfo::T_STRING,
        'match_on'            => ValueInfo::T_STRING,
        'mode'                => ValueInfo::T_INT,
        'field'               => ValueInfo::T_INT,
        'data'                => ValueInfo::T_STRING,
        'pref_name'           => ValueInfo::T_STRING,
    ];
    const FATAL_ERR = [
        'seq'     => null,
        'status'  => 1,
        'content' => ['error' => "NOT_LOGGED_IN"],
    ];
    
    public function __construct() {
    }

    public function dispatch(\JKingWeb\Arsse\REST\Request $req): Response {
        if ($req->method != "POST") {
            // only POST requests are allowed
            return new Response(405, self::FATAL_ERR, "application/json", ["Allow: POST"]);
        }
        if ($req->body) {
            // only JSON entities are allowed
            if (!preg_match("<^application/json\b|^$>", $req->type)) {
                return new Response(415, self::FATAL_ERR, "application/json", ['Accept: application/json']);
            }
            $data = @json_decode($req->body, true);
            if (json_last_error() != \JSON_ERROR_NONE || !is_array($data)) {
                // non-JSON input indicates an error
                return new Response(400, self::FATAL_ERR);
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
                    // because method names are supposed to be case insensitive, we need to try a bit harder to match
                    $method = strtolower($method);
                    $map = get_class_methods($this);
                    $map = array_combine(array_map("strtolower", $map), $map);
                    if (!array_key_exists($method, $map)) {
                        // if the method really doesn't exist, throw an exception
                        throw new Exception("UNKNWON_METHOD", ['method' => $data['op']]);
                    }
                    // otherwise retrieve the correct camelCase and continue
                    $method = $map[$method];
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
            return new Response(400, self::FATAL_ERR);
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
        return ['unread' => $out];
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
        $catmap[0] = sizeof($categories);
        $categories[] = ['id' => 0, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Uncategorized"), 'parent' => 0, 'children' => 0, 'counter' => 0];
        $catmap[-2] = sizeof($categories);
        $categories[] = ['id' => -2, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Labels"), 'parent' => 0, 'children' => 0, 'counter' => 0];
        // prepare data for each subscription; we also add unread counts for their host categories
        foreach (Arsse::$db->subscriptionList($user) as $f) {
            if ($f['unread']) {
                // add the feed to the list of feeds
                $feeds[] = ['id' => $f['id'], 'updated' => Date::transform($f['updated'], "iso8601", "sql"),'counter' => $f['unread'], 'has_img' => (int) (strlen((string) $f['favicon']) > 0)];
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
            $categories[$catmap[-2]]['counter'] += $unread;
        }
        // do a second pass on categories, summing descendant unread counts for ancestors, pruning categories with no unread, and building a final category list
        $cats = [];
        while ($categories) {
            foreach ($categories as $c) {
                if ($c['children']) {
                    // only act on leaf nodes
                    continue;
                }
                if ($c['parent']) {
                    // if the category has a parent, add its counter to the parent's counter, and decrement the parent's child count
                    $categories[$catmap[$c['parent']]]['counter'] += $c['counter'];
                    $categories[$catmap[$c['parent']]]['children'] -= 1;
                }
                if ($c['counter']) {
                    // if the category's counter is non-zero, add the category to the output list
                    $cats[] = ['id' => $c['id'], 'kind' => "cat", 'counter' => $c['counter']];
                }
                // remove the category from the input list
                unset($categories[$catmap[$c['id']]]);
            }
        }
        // prepare data for the virtual feeds and other counters
        $special = [
            ['id' => "global-unread",    'counter' => $countAll], //this should not count archived articles, but we do not have an archive
            ['id' => "subscribed-feeds", 'counter' => $countSubs],
            ['id' => 0,  'counter' => 0, 'auxcounter' => 0], // Archived articles
            ['id' => -1, 'counter' => $starred['unread'], 'auxcounter' => $starred['total']], // Starred articles
            ['id' => -2, 'counter' => 0, 'auxcounter' => 0], // Published articles
            ['id' => -3, 'counter' => $fresh, 'auxcounter' => 0], // Fresh articles
            ['id' => -4, 'counter' => $countAll, 'auxcounter' => 0], // All articles
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
            'id' => "CAT:-1",
            'bare_id' => -1,
            'type' => "category",
            'unread' => 0,
            'items' => [
                array_merge([ // All articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.All"),
                    'id' => "FEED:-4",
                    'bare_id' => -4,
                    'icon' => "images/folder.png",
                    'unread' => array_reduce($subs, function($sum, $value) {return $sum + $value['unread'];}, 0), // the sum of all feeds' unread is the total unread
                ], $tSpecial),
                array_merge([ // Fresh articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Fresh"),
                    'id' => "FEED:-3",
                    'bare_id' => -3,
                    'icon' => "images/fresh.png",
                    'unread' => Arsse::$db->articleCount($user, (new Context)->unread(true)->modifiedSince(Date::sub("PT24H"))),
                ], $tSpecial),
                array_merge([ // Starred articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Starred"),
                    'id' => "FEED:-1",
                    'bare_id' => -1,
                    'icon' => "images/star.png",
                    'unread' => Arsse::$db->articleStarred($user)['unread'],
                ], $tSpecial),
                array_merge([ // Published articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Published"),
                    'id' => "FEED:-2",
                    'bare_id' => -2,
                    'icon' => "images/feed.png",
                    'unread' => 0, // TODO: unread count should be populated if the Published feed is ever implemented
                ], $tSpecial),
                array_merge([ // Archived articles
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Archived"),
                    'id' => "FEED:0",
                    'bare_id' => 0,
                    'icon' => "images/archive.png",
                    'unread' => 0, // Article archiving is not exposed by the API, so this is always zero
                ], $tSpecial),
                array_merge([ // Recently read
                    'name' => Arsse::$lang->msg("API.TTRSS.Feed.Read"),
                    'id' => "FEED:-6",
                    'bare_id' => -6,
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
                'id' => "CAT:-2",
                'bare_id' => -2,
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
                'id'           => "CAT:0",
                'bare_id'      => 0,
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
                'parent_id'    => $c['parent'],
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
            $map[$cats[$a]['id']] = $a;
            $cats[$a]['unread'] = 0;
            $cats[$a]['order'] = $a + 1;
        }
        // add the "Uncategorized", "Special", and "Labels" virtual categories to the list
        $map[0] = sizeof($cats);
        $cats[] = ['id' => 0, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Uncategorized"), 'children' => 0, 'unread' => 0, 'feeds' => 0];
        $map[-1] = sizeof($cats);
        $cats[] = ['id' => -1, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Special"), 'children' => 0, 'unread' => 0, 'feeds' => 6];
        $map[-2] = sizeof($cats);
        $cats[] = ['id' => -2, 'name' => Arsse::$lang->msg("API.TTRSS.Category.Labels"), 'children' => 0, 'unread' => 0, 'feeds' => 0];
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
        $f = $map[-2];
        foreach ($labels as $label) {
            $cats[$f]['unread'] += $label['articles'] - $label['read'];
            $cats[$f]['feeds'] += 1;
        }
        // get the unread counts for the special feeds
        // FIXME: this is pretty inefficient
        $f = $map[-1];
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
            return Arsse::$db->folderAdd(Arsse::$user->id, $in);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10236: // folder already exists
                    // retrieve the ID of the existing folder; duplicating a folder silently returns the existing one
                    $folders = Arsse::$db->folderList(Arsse::$user->id, $in['parent'], false);
                    foreach ($folders as $folder) {
                        if ($folder['name']==$in['name']) {
                            return (int) $folder['id'];
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
            'name' => $data['caption'],
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

    protected function labelIn($id): int {
        if (!(ValueInfo::int($id) & ValueInfo::NEG) || $id > (-1 - self::LABEL_OFFSET)) {
            throw new Exception("INCORRECT_USAGE");
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
}
