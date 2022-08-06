<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST\NextcloudNews;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Service;
use JKingWeb\Arsse\Context\Context;
use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\AbstractException;
use JKingWeb\Arsse\Db\ExceptionInput;
use JKingWeb\Arsse\Feed\Exception as FeedException;
use JKingWeb\Arsse\Misc\HTTP;
use JKingWeb\Arsse\REST\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class V1_2 extends \JKingWeb\Arsse\REST\AbstractHandler {
    public const VERSION = "11.0.5";
    protected const ACCEPTED_TYPE = "application/json";

    protected $dateFormat = "unix";

    protected $validInput = [
        'name'         => ValueInfo::T_STRING,
        'url'          => ValueInfo::T_STRING,
        'folderId'     => ValueInfo::T_INT,
        'feedTitle'    => ValueInfo::T_STRING,
        'userId'       => ValueInfo::T_STRING,
        'feedId'       => ValueInfo::T_INT,
        'newestItemId' => ValueInfo::T_INT,
        'batchSize'    => ValueInfo::T_INT,
        'offset'       => ValueInfo::T_INT,
        'type'         => ValueInfo::T_INT,
        'id'           => ValueInfo::T_INT,
        'getRead'      => ValueInfo::T_BOOL,
        'oldestFirst'  => ValueInfo::T_BOOL,
        'lastModified' => ValueInfo::T_DATE,
        'items'        => ValueInfo::T_MIXED | ValueInfo::M_ARRAY,
    ];
    protected $paths = [
        '/folders'               => ['GET' => "folderList",       'POST'   => "folderAdd"],
        '/folders/1'             => ['PUT' => "folderRename",     'DELETE' => "folderRemove"],
        '/folders/1/read'        => ['PUT' => "folderMarkRead"],
        '/feeds'                 => ['GET' => "subscriptionList", 'POST' => "subscriptionAdd"],
        '/feeds/1'               => ['DELETE' => "subscriptionRemove"],
        '/feeds/1/move'          => ['PUT' => "subscriptionMove"],
        '/feeds/1/rename'        => ['PUT' => "subscriptionRename"],
        '/feeds/1/read'          => ['PUT' => "subscriptionMarkRead"],
        '/feeds/all'             => ['GET' => "feedListStale"],
        '/feeds/update'          => ['GET' => "feedUpdate"],
        '/items'                 => ['GET' => "articleList"],
        '/items/updated'         => ['GET' => "articleList"],
        '/items/read'            => ['PUT' => "articleMarkReadAll"],
        '/items/1/read'          => ['PUT' => "articleMarkRead"],
        '/items/1/unread'        => ['PUT' => "articleMarkRead"],
        '/items/read/multiple'   => ['PUT' => "articleMarkReadMulti"],
        '/items/unread/multiple' => ['PUT' => "articleMarkReadMulti"],
        '/items/1/1/star'        => ['PUT' => "articleMarkStarred"],
        '/items/1/1/unstar'      => ['PUT' => "articleMarkStarred"],
        '/items/star/multiple'   => ['PUT' => "articleMarkStarredMulti"],
        '/items/unstar/multiple' => ['PUT' => "articleMarkStarredMulti"],
        '/cleanup/before-update' => ['GET' => "cleanupBefore"],
        '/cleanup/after-update'  => ['GET' => "cleanupAfter"],
        '/version'               => ['GET' => "serverVersion"],
        '/status'                => ['GET' => "serverStatus"],
        '/user'                  => ['GET' => "userStatus"],
    ];

    public function __construct() {
    }

    public function dispatch(ServerRequestInterface $req): ResponseInterface {
        // get the request path only; this is assumed to already be normalized
        $target = parse_url($req->getRequestTarget())['path'] ?? "";
        // handle HTTP OPTIONS requests
        if ($req->getMethod() === "OPTIONS") {
            return $this->handleHTTPOptions($target);
        }
        // try to authenticate
        if ($req->getAttribute("authenticated", false)) {
            Arsse::$user->id = $req->getAttribute("authenticatedUser");
        } else {
            return HTTP::respEmpty(401);
        }
        // normalize the input
        $data = (string) $req->getBody();
        if ($data) {
            // if the entity body is not JSON according to content type, return "415 Unsupported Media Type"
            if (!HTTP::matchType($req, "", self::ACCEPTED_TYPE)) {
                return HTTP::respEmpty(415, ['Accept' => self::ACCEPTED_TYPE]);
            }
            $data = @json_decode($data, true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                // if the body could not be parsed as JSON, return "400 Bad Request"
                return HTTP::respEmpty(400);
            }
        } else {
            $data = [];
        }
        // merge GET and POST data, and normalize it. POST parameters are preferred over GET parameters
        $data = $this->normalizeInput(array_merge($req->getQueryParams(), $data), $this->validInput, "unix");
        // check to make sure the requested function is implemented
        $func = $this->chooseCall($target, $req->getMethod());
        if ($func instanceof ResponseInterface) {
            return $func;
        }
        // dispatch
        try {
            $path = explode("/", ltrim($target, "/"));
            return $this->$func($path, $data);
            // @codeCoverageIgnoreStart
        } catch (Exception $e) {
            // if there was a REST exception return 400
            return HTTP::respEmpty(400);
        } catch (AbstractException $e) {
            // if there was any other Arsse exception return 500
            return HTTP::respEmpty(500);
        }
        // @codeCoverageIgnoreEnd
    }

    protected function normalizePathIds(string $url): string {
        $path = explode("/", $url);
        // any path components which are database IDs (integers greater than zero) should be replaced with "1", for easier comparison (we don't care about the specific ID)
        for ($a = 0; $a < sizeof($path); $a++) {
            if (ValueInfo::id($path[$a])) {
                $path[$a] = "1";
            }
        }
        return implode("/", $path);
    }

    protected function normalizeInput(array $data, array $types, string $dateFormat = null, int $mode = 0): array {
        $out = [];
        foreach ($types as $key => $type) {
            if (isset($data[$key])) {
                $out[$key] = ValueInfo::normalize($data[$key], $type | $mode, $dateFormat);
            } else {
                $out[$key] = null;
            }
        }
        return $out;
    }

    protected function chooseCall(string $url, string $method) {
        // // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIds($url);
        // normalize the HTTP method to uppercase
        $method = strtoupper($method);
        // we now evaluate the supplied URL against every supported path for the selected scope
        if (isset($this->paths[$url])) {
            // if the path is supported, make sure the method is allowed
            if (isset($this->paths[$url][$method])) {
                // if it is allowed, return the object method to run, assuming the method exists
                assert(method_exists($this, $this->paths[$url][$method]), new \Exception("Method is not implemented"));
                return $this->paths[$url][$method];
            } else {
                // otherwise return 405
                return HTTP::respEmpty(405, ['Allow' => implode(", ", array_keys($this->paths[$url]))]);
            }
        } else {
            // if the path is not supported, return 404
            return HTTP::respEmpty(404);
        }
    }

    protected function folderTranslate(array $folder): array {
        // map fields to proper names
        $folder = $this->fieldMapNames($folder, [
            'id'   => "id",
            'name' => "name",
        ]);
        // cast values
        $folder = $this->fieldMapTypes($folder, [
            'id'   => "int",
            'name' => "string",
        ], $this->dateFormat);
        return $folder;
    }

    protected function feedTranslate(array $feed): array {
        // map fields to proper names
        $feed = $this->fieldMapNames($feed, [
            'id'               => "id",
            'url'              => "url",
            'title'            => "title",
            'added'            => "added",
            'pinned'           => "pinned",
            'link'             => "source",
            'faviconLink'      => "icon_url",
            'folderId'         => "top_folder",
            'unreadCount'      => "unread",
            'ordering'         => "order_type",
            'updateErrorCount' => "err_count",
            'lastUpdateError'  => "err_msg",
        ]);
        // cast values
        $feed = $this->fieldMapTypes($feed, [
            'id'               => "int",
            'url'              => "string",
            'title'            => "string",
            'added'            => "datetime",
            'pinned'           => "bool",
            'link'             => "string",
            'faviconLink'      => "string",
            'folderId'         => "int",
            'unreadCount'      => "int",
            'ordering'         => "int",
            'updateErrorCount' => "int",
            'lastUpdateError'  => "string",
        ], $this->dateFormat);
        return $feed;
    }

    protected function articleTranslate(array $article): array {
        // map fields to proper names
        $article = $this->fieldMapNames($article, [
            'id'            => "edition",
            'guid'          => "guid",
            'guidHash'      => "id",
            'url'           => "url",
            'title'         => "title",
            'author'        => "author",
            'pubDate'       => "edited_date",
            'body'          => "content",
            'enclosureMime' => "media_type",
            'enclosureLink' => "media_url",
            'feedId'        => "subscription",
            'unread'        => "unread",
            'starred'       => "starred",
            'lastModified'  => "modified_date",
            'fingerprint'   => "fingerprint",
        ]);
        // cast values
        $article = $this->fieldMapTypes($article, [
            'id'            => "int",
            'guid'          => "string",
            'guidHash'      => "string",
            'url'           => "string",
            'title'         => "string",
            'author'        => "string",
            'pubDate'       => "datetime",
            'body'          => "string",
            'enclosureMime' => "string",
            'enclosureLink' => "string",
            'feedId'        => "int",
            'unread'        => "bool",
            'starred'       => "bool",
            'lastModified'  => "datetime",
            'fingerprint'   => "string",
        ], $this->dateFormat);
        return $article;
    }

    protected function handleHTTPOptions(string $url): ResponseInterface {
        // normalize the URL path: change any IDs to 1 for easier comparison
        $url = $this->normalizePathIDs($url);
        if (isset($this->paths[$url])) {
            // if the path is supported, respond with the allowed methods and other metadata
            $allowed = array_keys($this->paths[$url]);
            // if GET is allowed, so is HEAD
            if (in_array("GET", $allowed)) {
                array_unshift($allowed, "HEAD");
            }
            return HTTP::respEmpty(204, [
                'Allow'  => implode(",", $allowed),
                'Accept' => self::ACCEPTED_TYPE,
            ]);
        } else {
            // if the path is not supported, return 404
            return HTTP::respEmpty(404);
        }
    }

    // list folders
    protected function folderList(array $url, array $data): ResponseInterface {
        $folders = [];
        foreach (Arsse::$db->folderList(Arsse::$user->id, null, false) as $folder) {
            $folders[] = $this->folderTranslate($folder);
        }
        return HTTP::respJson(['folders' => $folders]);
    }

    // create a folder
    protected function folderAdd(array $url, array $data): ResponseInterface {
        try {
            $folder = Arsse::$db->folderAdd(Arsse::$user->id, ['name' => $data['name']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                // folder already exists
                case 10236: return HTTP::respEmpty(409);
                // folder name not acceptable
                case 10231:
                case 10232: return HTTP::respEmpty(422);
                // other errors related to input
                default: return HTTP::respEmpty(400); // @codeCoverageIgnore
            }
        }
        $folder = $this->folderTranslate(Arsse::$db->folderPropertiesGet(Arsse::$user->id, $folder));
        return HTTP::respJson(['folders' => [$folder]]);
    }

    // delete a folder
    protected function folderRemove(array $url, array $data): ResponseInterface {
        // perform the deletion
        try {
            Arsse::$db->folderRemove(Arsse::$user->id, (int) $url[1]);
        } catch (ExceptionInput $e) {
            // folder does not exist
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // rename a folder (also supports moving nesting folders, but this is not a feature of the API)
    protected function folderRename(array $url, array $data): ResponseInterface {
        try {
            Arsse::$db->folderPropertiesSet(Arsse::$user->id, (int) $url[1], ['name' => $data['name']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                // folder does not exist
                case 10239: return HTTP::respEmpty(404);
                // folder already exists
                case 10236: return HTTP::respEmpty(409);
                // folder name not acceptable
                case 10231:
                case 10232: return HTTP::respEmpty(422);
                // other errors related to input
                default: return HTTP::respEmpty(400); // @codeCoverageIgnore
            }
        }
        return HTTP::respEmpty(204);
    }

    // mark all articles associated with a folder as read
    protected function folderMarkRead(array $url, array $data): ResponseInterface {
        if (!ValueInfo::id($data['newestItemId'])) {
            // if the item ID is invalid (i.e. not a positive integer), this is an error
            return HTTP::respEmpty(422);
        }
        // build the context
        $c = (new Context)->hidden(false);
        $c->editionRange(null, (int) $data['newestItemId']);
        $c->folder((int) $url[1]);
        // perform the operation
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        } catch (ExceptionInput $e) {
            // folder does not exist
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // return list of feeds which should be refreshed
    protected function feedListStale(array $url, array $data): ResponseInterface {
        if (!$this->isAdmin()) {
            return HTTP::respEmpty(403);
        }
        // list stale feeds which should be checked for updates
        $feeds = Arsse::$db->feedListStale();
        $out = [];
        foreach ($feeds as $feed) {
            // since in our implementation feeds don't belong the users, the 'userId' field will always be an empty string
            $out[] = ['id' => (int) $feed, 'userId' => ""];
        }
        return HTTP::respJson(['feeds' => $out]);
    }

    // refresh a feed
    protected function feedUpdate(array $url, array $data): ResponseInterface {
        if (!$this->isAdmin()) {
            return HTTP::respEmpty(403);
        }
        try {
            Arsse::$db->feedUpdate($data['feedId']);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10239: // feed does not exist
                    return HTTP::respEmpty(404);
                case 10237: // feed ID invalid
                    return HTTP::respEmpty(422);
                default: // other errors related to input
                    return HTTP::respEmpty(400); // @codeCoverageIgnore
            }
        }
        return HTTP::respEmpty(204);
    }

    // add a new feed
    protected function subscriptionAdd(array $url, array $data): ResponseInterface {
        // try to add the feed
        $tr = Arsse::$db->begin();
        try {
            $id = Arsse::$db->subscriptionAdd(Arsse::$user->id, (string) $data['url']);
        } catch (ExceptionInput $e) {
            // feed already exists
            return HTTP::respEmpty(409);
        } catch (FeedException $e) {
            // feed could not be retrieved
            return HTTP::respEmpty(422);
        }
        // if a folder was specified, move the feed to the correct folder; silently ignore errors
        if ($data['folderId']) {
            try {
                Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $id, ['folder' => $data['folderId']]);
            } catch (ExceptionInput $e) {
            }
        }
        $tr->commit();
        // fetch the feed's metadata and format it appropriately
        $feed = Arsse::$db->subscriptionPropertiesGet(Arsse::$user->id, $id);
        $feed = $this->feedTranslate($feed);
        $out = ['feeds' => [$feed]];
        $newest = Arsse::$db->editionLatest(Arsse::$user->id, (new Context)->subscription($id)->hidden(false));
        if ($newest) {
            $out['newestItemId'] = $newest;
        }
        return HTTP::respJson($out);
    }

    // return list of feeds for the logged-in user
    protected function subscriptionList(array $url, array $data): ResponseInterface {
        $subs = Arsse::$db->subscriptionList(Arsse::$user->id);
        $out = [];
        foreach ($subs as $sub) {
            $out[] = $this->feedTranslate($sub);
        }
        $out = ['feeds' => $out];
        $out['starredCount'] = (int) Arsse::$db->articleStarred(Arsse::$user->id)['total'];
        $newest = Arsse::$db->editionLatest(Arsse::$user->id);
        if ($newest) {
            $out['newestItemId'] = $newest;
        }
        return HTTP::respJson($out);
    }

    // delete a feed
    protected function subscriptionRemove(array $url, array $data): ResponseInterface {
        try {
            Arsse::$db->subscriptionRemove(Arsse::$user->id, (int) $url[1]);
        } catch (ExceptionInput $e) {
            // feed does not exist
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // rename a feed
    protected function subscriptionRename(array $url, array $data): ResponseInterface {
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $url[1], ['title' => (string) $data['feedTitle']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                // subscription does not exist
                case 10239: return HTTP::respEmpty(404);
                // name is invalid
                case 10231:
                case 10232: return HTTP::respEmpty(422);
                // other errors related to input
                default: return HTTP::respEmpty(400); // @codeCoverageIgnore
            }
        }
        return HTTP::respEmpty(204);
    }

    // move a feed to a folder
    protected function subscriptionMove(array $url, array $data): ResponseInterface {
        // if no folder is specified this is an error
        if (!isset($data['folderId'])) {
            return HTTP::respEmpty(422);
        }
        // perform the move
        try {
            Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, (int) $url[1], ['folder' => $data['folderId']]);
        } catch (ExceptionInput $e) {
            switch ($e->getCode()) {
                case 10239: // subscription does not exist
                    return HTTP::respEmpty(404);
                case 10235: // folder does not exist
                case 10237: // folder ID is invalid
                    return HTTP::respEmpty(422);
                default: // other errors related to input
                    return HTTP::respEmpty(400); // @codeCoverageIgnore
            }
        }
        return HTTP::respEmpty(204);
    }

    // mark all articles associated with a subscription as read
    protected function subscriptionMarkRead(array $url, array $data): ResponseInterface {
        if (!ValueInfo::id($data['newestItemId'])) {
            // if the item ID is invalid (i.e. not a positive integer), this is an error
            return HTTP::respEmpty(422);
        }
        // build the context
        $c = (new Context)->hidden(false);
        $c->editionRange(null, (int) $data['newestItemId']);
        $c->subscription((int) $url[1]);
        // perform the operation
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        } catch (ExceptionInput $e) {
            // subscription does not exist
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // list articles and their properties
    protected function articleList(array $url, array $data): ResponseInterface {
        // set the context options supplied by the client
        $c = (new Context)->hidden(false);
        // set the batch size
        if ($data['batchSize'] > 0) {
            $c->limit($data['batchSize']);
        }
        // set the order of returned items
        $reverse = !$data['oldestFirst'];
        // set the edition mark-off; the database uses an or-equal comparison for internal consistency, but the protocol does not, so we must adjust by one
        if ($data['offset'] > 0) {
            if ($reverse) {
                $c->editionRange(null, $data['offset'] - 1);
            } else {
                $c->editionRange($data['offset'] + 1, null);
            }
        }
        // set whether to only return unread
        if (!ValueInfo::bool($data['getRead'], true)) {
            $c->unread(true);
        }
        // if no type is specified assume 3 (All)
        $data['type'] = $data['type'] ?? 3;
        switch ($data['type']) {
            case 0: // feed
                if (isset($data['id'])) {
                    $c->subscription($data['id']);
                }
                break;
            case 1: // folder
                if (isset($data['id'])) {
                    $c->folder($data['id']);
                }
                break;
            case 2: // starred
                $c->starred(true);
                break;
            default: // @codeCoverageIgnore
                // return all items
        }
        // whether to return only updated items
        if ($data['lastModified']) {
            $c->markedRange($data['lastModified'], null);
        }
        // perform the fetch
        try {
            $items = Arsse::$db->articleList(Arsse::$user->id, $c, [
                "edition",
                "guid",
                "id",
                "url",
                "title",
                "author",
                "edited_date",
                "content",
                "media_type",
                "media_url",
                "subscription",
                "unread",
                "starred",
                "modified_date",
                "fingerprint",
            ], [$reverse ? "edition desc" : "edition"]);
        } catch (ExceptionInput $e) {
            // ID of subscription or folder is not valid
            return HTTP::respEmpty(422);
        }
        $out = [];
        foreach ($items as $item) {
            $out[] = $this->articleTranslate($item);
        }
        $out = ['items' => $out];
        return HTTP::respJson($out);
    }

    // mark all articles as read
    protected function articleMarkReadAll(array $url, array $data): ResponseInterface {
        if (!ValueInfo::id($data['newestItemId'])) {
            // if the item ID is invalid (i.e. not a positive integer), this is an error
            return HTTP::respEmpty(422);
        }
        // build the context
        $c = (new Context)->hidden(false);
        $c->editionRange(null, (int) $data['newestItemId']);
        // perform the operation
        Arsse::$db->articleMark(Arsse::$user->id, ['read' => true], $c);
        return HTTP::respEmpty(204);
    }

    // mark a single article as read
    protected function articleMarkRead(array $url, array $data): ResponseInterface {
        // initialize the matching context
        $c = new Context;
        $c->edition((int) $url[1]);
        // determine whether to mark read or unread
        $set = ($url[2] === "read");
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => $set], $c);
        } catch (ExceptionInput $e) {
            // ID is not valid
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // mark a single article as read
    protected function articleMarkStarred(array $url, array $data): ResponseInterface {
        // initialize the matching context
        $c = new Context;
        $c->article((int) $url[2]);
        // determine whether to mark read or unread
        $set = ($url[3] === "star");
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $set], $c);
        } catch (ExceptionInput $e) {
            // ID is not valid
            return HTTP::respEmpty(404);
        }
        return HTTP::respEmpty(204);
    }

    // mark an array of articles as read
    protected function articleMarkReadMulti(array $url, array $data): ResponseInterface {
        // determine whether to mark read or unread
        $set = ($url[1] === "read");
        // initialize the matching context
        $c = new Context;
        $c->editions($data['items'] ?? []);
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['read' => $set], $c);
        } catch (ExceptionInput $e) {
        }
        return HTTP::respEmpty(204);
    }

    // mark an array of articles as starred
    protected function articleMarkStarredMulti(array $url, array $data): ResponseInterface {
        // determine whether to mark starred or unstarred
        $set = ($url[1] === "star");
        // initialize the matching context
        $c = new Context;
        $c->articles(array_column($data['items'] ?? [], "guidHash"));
        try {
            Arsse::$db->articleMark(Arsse::$user->id, ['starred' => $set], $c);
        } catch (ExceptionInput $e) {
        }
        return HTTP::respEmpty(204);
    }

    protected function userStatus(array $url, array $data): ResponseInterface {
        return HTTP::respJson([
            'userId'             => (string) Arsse::$user->id,
            'displayName'        => (string) Arsse::$user->id,
            'lastLoginTimestamp' => time(),
            'avatar'             => null,
        ]);
    }

    protected function cleanupBefore(array $url, array $data): ResponseInterface {
        if (!$this->isAdmin()) {
            return HTTP::respEmpty(403);
        }
        Service::cleanupPre();
        return HTTP::respEmpty(204);
    }

    protected function cleanupAfter(array $url, array $data): ResponseInterface {
        if (!$this->isAdmin()) {
            return HTTP::respEmpty(403);
        }
        Service::cleanupPost();
        return HTTP::respEmpty(204);
    }

    // return the server version
    protected function serverVersion(array $url, array $data): ResponseInterface {
        return HTTP::respJson([
            'version'       => self::VERSION,
            'arsse_version' => Arsse::VERSION,
        ]);
    }

    protected function serverStatus(array $url, array $data): ResponseInterface {
        return HTTP::respJson([
            'version'       => self::VERSION,
            'arsse_version' => Arsse::VERSION,
            'warnings'      => [
                'improperlyConfiguredCron' => !Service::hasCheckedIn(),
                'incorrectDbCharset'       => !Arsse::$db->driverCharsetAcceptable(),
            ],
        ]);
    }
}
