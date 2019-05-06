<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput as InputException;
use JKingWeb\Arsse\User\Exception as UserException;

class OPML {
    public function import(string $user, string $opml, bool $flat = false, bool $replace = false): bool {
        // first extract useful information from the input
        list($feeds, $folders) = $this->parse($opml, $flat);
        $folderMap = [];
        foreach ($folders as $f) {
            // check to make sure folder names are all valid
            if (!strlen(trim($f['name']))) {
                throw new Exception("invalidFolderName");
            }
            // check for duplicates
            if (!isset($folderMap[$f['parent']])) {
                $folderMap[$f['parent']] = [];
            }
            if (isset($folderMap[$f['parent']][$f['name']])) {
                throw new Exception("invalidFolderCopy");
            } else {
                $folderMap[$f['parent']][$f['name']] = true;
            }
        }
        // get feed IDs for each URL, adding feeds where necessary
        foreach ($feeds as $k => $f) {
            $feeds[$k]['id'] = Arsse::$db->feedAdd(($f['url']));
        }
        // start a transaction for atomic rollback
        $tr = Arsse::$db->begin();
        // get current state of database
        $foldersDb = iterator_to_array(Arsse::$db->folderList(Arsse::$user->id));
        $feedsDb =  iterator_to_array(Arsse::$db->subscriptionList(Arsse::$user->id));
        $tagsDb = iterator_to_array(Arsse::$db->tagList(Arsse::$user->id));
        // reconcile folders
        $folderMap = [0 => 0];
        foreach ($folders as $id => $f) {
            $parent = $folderMap[$f['parent']];
            // find a match for the import folder in the existing folders
            foreach ($foldersDb as $db) {
                if ((int) $db['parent'] == $parent && $db['name'] === $f['name']) {
                    $folderMap[$id] = (int) $db['id'];
                    break;
                }
            }
            if (!isset($folderMap[$id])) {
                // if no existing folder exists, add one
                $folderMap[$id] = Arsse::$db->folderAdd(Arsse::$user->id, ['name' => $f['name'], 'parent' -> $parent]);
            }
        }
        // process newsfeed subscriptions
        $feedMap = [];
        $tagMap = [];
        foreach ($feeds as $f) {
            $folder = $folderMap[$f['folder']];
            $title = strlen(trim($f['title'])) ? $f['title'] : null;
            $found = false;
            // find a match for the import feed is existing subscriptions
            foreach ($feedsDb as $db) {
                if ((int) $db['feed'] == $f['id']) {
                    $found = true;
                    $feedMap[$f['id']] = (int) $db['id'];
                    break;
                }
            }
            if (!$found) {
                // if no subscription exists, add one
                $feedMap[$f['id']] = Arsse::$db->subscriptionAdd(Arsse::$user->id, $f['url']);
            }
            if (!$found || $replace) {
                // set the subscription's properties, if this is a new feed or we're doing a full replacement
                Arsse::$db->subscriptionPropertiesSet(Arsse::$user->id, $feedMap[$f['id']], ['title' => $title, 'folder' => $folder]);
                // compile the set of used tags, if this is a new feed or we're doing a full replacement
                foreach ($f['tags'] as $t) {
                    if (!strlen(trim($t))) {
                        // ignore any blank tags
                        continue;
                    }
                    if (!isset($tagMap[$t])) {
                        // populate the tag map
                        $tagMap[$t] = [];
                    }
                    $tagMap[$t][] = $f['id'];
                }
            }
        }
        // set tags
        $mode = $replace ? Database::ASSOC_REPLACE : Database::ASSOC_ADD;
        foreach ($tagMap as $tag => $subs) {
            // make sure the tag exists
            $found = false;
            foreach ($tagsDb as $db) {
                if ($tag === $db['name']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // add the tag if it wasn't found
                Arsse::$db->tagAdd(Arsse::$user->id, ['name' => $tag]);
            }
            Arsse::$db->tagSubscriptionsSet(Arsse::$user->id, $tag, $subs, $mode, true);
        }
        // finally, if we're performing a replacement, delete any subscriptions, folders, or tags which were not present in the import
        if ($replace) {
            foreach (array_diff(array_column($feedsDb, "id"), $feedMap) as $id) {
                try {
                    Arsse::$db->subscriptionRemove(Arsse::$user->id, $id);
                } catch (InputException $e) {
                    // ignore errors
                }
            }
            foreach (array_diff(array_column($foldersDb, "id"), $folderMap) as $id) {
                try {
                    Arsse::$db->folderRemove(Arsse::$user->id, $id);
                } catch (InputException $e) {
                    // ignore errors
                }
            }
            foreach (array_diff(array_column($tagsDb, "name"), array_keys($tagMap)) as $id) {
                try {
                    Arsse::$db->tagRemove(Arsse::$user->id, $id, true);
                } catch (InputException $e) {
                    // ignore errors
                }
            }
        }
        $tr->commit();
        return true;
    }

    public function parse(string $opml, bool $flat): array {
        $d = new \DOMDocument;
        if (!@$d->loadXML($opml)) {
            // not a valid XML document
            $err = libxml_get_last_error();
            throw new Exception("invalidSyntax", ['line' => $err->line, 'column' => $err->column]);
        }
        $body = (new \DOMXPath($d))->query("/opml/body");
        if ($body->length != 1) {
            // not a valid OPML document
            throw new Exception("invalidSemantics", ['type' => "OPML"]);
        }
        $body = $body->item(0);
        $folders = [];
        $feeds = [];
        // add the root folder to a map from folder DOM nodes to folder ID numbers
        $folderMap = new \SplObjectStorage;
        $folderMap[$body] = sizeof($folderMap);
        // iterate through each node in the body
        $node = $body->firstChild;
        while ($node && !$node->isSameNode($body)) {
            if ($node->nodeType == \XML_ELEMENT_NODE && $node->nodeName === "outline") {
                // process any nodes which are outlines
                if ($node->getAttribute("type") === "rss") {
                    // feed nodes
                    $url = $node->getAttribute("xmlUrl");
                    $title = $node->getAttribute("text");
                    $folder = $folderMap[$node->parentNode] ?? 0;
                    $categories = $node->getAttribute("category");
                    if (strlen($categories)) {
                        // collapse and trim whitespace from category names, if any, splitting along commas
                        $categories = array_map(function($v) {
                            return trim(preg_replace("/\s+/", " ", $v));
                        }, explode(",", $categories));
                    } else {
                        $categories = [];
                    }
                    $feeds[] = ['url' => $url, 'title' => $title, 'folder' => $folder, 'tags' => $categories];
                    // skip any child nodes of a feed outline-entry
                    $node = $node->nextSibling ?: $node->parentNode;
                } else {
                    // any outline entries which are not feeds are treated as folders
                    if (!$flat) {
                        // only process folders if we're not treating he file as flat
                        $id = sizeof($folderMap);
                        $folderMap[$node] = $id;
                        $folders[$id] = ['id' => $id, 'name' => $node->getAttribute("text"), 'parent' => $folderMap[$node->parentNode]];
                    }
                    // proceed to child nodes, if any
                    $node = $node->hasChildNodes() ? $node->firstChild : ($node->nextSibling ?: $node->parentNode);
                }
            } else {
                // skip any node which is not an outline element; if the node has descendents they are skipped as well
                $node = $node->nextSibling ?: $node->parentNode;
            }
        }
        return [$feeds, $folders];
    }

    public function export(string $user, bool $flat = false): string {
        if (!Arsse::$user->exists($user)) {
            throw new UserException("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        $tags = [];
        $folders = [];
        $parents = [0 => null];
        // create a base document
        $document = new \DOMDocument("1.0", "utf-8");
        $document->formatOutput = true;
        $document->appendChild($document->createElement("opml"));
        $document->documentElement->setAttribute("version", "2.0");
        $document->documentElement->appendChild($document->createElement("head"));
        // create the "root folder" node (the body node, in OPML terms)
        $folders[0] = $document->createElement("body");
        // begin a transaction for read isolation
        $transaction = Arsse::$db->begin();
        // gather up the list of tags for each subscription
        foreach (Arsse::$db->tagSummarize($user) as $r) {
            $sub = $r['subscription'];
            $tag = $r['name'];
            // strip out any commas in the tag name; sadly this is lossy as OPML has no escape mechanism
            $tag = str_replace(",", "", $tag);
            if (!isset($tags[$sub])) {
                $tags[$sub] = [];
            }
            $tags[$sub][] = $tag;
        }
        if (!$flat) {
            // unless the output is requested flat, gather up the list of folders, using their database IDs as array indices
            foreach (Arsse::$db->folderList($user) as $r) {
                // note the index of its parent folder for later tree construction
                $parents[$r['id']] = $r['parent'] ?? 0;
                // create a DOM node for each folder; we don't insert it yet
                $el = $document->createElement("outline");
                $el->setAttribute("text", $r['name']);
                $folders[$r['id']] = $el;
            }
        }
        // insert each folder into its parent node; for the root folder the parent is the document root node
        foreach ($folders as $id => $el) {
            $parent = $folders[$parents[$id]] ?? $document->documentElement;
            $parent->appendChild($el);
        }
        // create a DOM node for each subscription and insert them directly into their folder DOM node
        foreach (Arsse::$db->subscriptionList($user) as $r) {
            $el = $document->createElement(("outline"));
            $el->setAttribute("type", "rss");
            $el->setAttribute("text", $r['title']);
            $el->setAttribute("xmlUrl", $r['url']);
            // include the category attribute only if there are tags
            if (isset($tags[$r['id']]) && sizeof($tags[$r['id']])) {
                $el->setAttribute("category", implode(",", $tags[$r['id']]));
            }
            // if flat output was requested subscriptions are inserted into the root folder
            ($folders[$r['folder'] ?? 0] ?? $folders[0])->appendChild($el);
        }
        // release the transaction
        $transaction->rollback();
        // return the serialization
        return $document->saveXML();
    }

    public function exportFile(string $file, string $user, bool $flat = false): bool {
        $data = $this->export($user, $flat);
        if (!@file_put_contents($file, $data)) {
            // if it fails throw an exception
            $err = file_exists($file) ? "fileUnwritable" : "fileUncreatable";
            throw new Exception($err, ['file' => $file, 'format' => str_replace(__NAMESPACE__."\\", "", __CLASS__)]);
        }
        return true;
    }

    public function imortFile(string $file, string $user, bool $flat = false, bool $replace): bool {
        $data = @file_get_contents($file);
        if ($data === false) {
            // if it fails throw an exception
            $err = file_exists($file) ? "fileUnreadable" : "fileMissing";
            throw new Exception($err, ['file' => $file, 'format' => str_replace(__NAMESPACE__."\\", "", __CLASS__)]);
        }
        return $this->import($user, $data, $flat, $replace);
    }
}
