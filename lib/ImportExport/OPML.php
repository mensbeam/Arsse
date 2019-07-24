<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\Exception as UserException;

class OPML extends AbstractImportExport {
    protected function parse(string $opml, bool $flat): array {
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
        // function to find the next node in the tree
        $next = function(\DOMNode $node, bool $visitChildren = true) use ($body) {
            if ($visitChildren && $node->hasChildNodes()) {
                return $node->firstChild;
            } elseif ($node->nextSibling) {
                return $node->nextSibling;
            } else {
                while (!$node->nextSibling && !$node->isSameNode($body)) {
                    $node = $node->parentNode;
                }
                if (!$node->isSameNode($body)) {
                    return $node->nextSibling;
                } else {
                    return null;
                }
            }
        };
        $folders = [];
        $feeds = [];
        // add the root folder to a map from folder DOM nodes to folder ID numbers
        $folderMap = new \SplObjectStorage;
        $folderMap[$body] = sizeof($folderMap);
        // iterate through each node in the body
        $node = $body->firstChild;
        while ($node) {
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
                        // filter out any blank categories
                        $categories = array_filter($categories, function($v) {
                            return strlen($v);
                        });
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
                    $node = $next($node);
                }
            } else {
                // skip any node which is not an outline element; if the node has descendents they are skipped as well
                $node = $next($node, false);
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
}
