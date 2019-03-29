<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\ImportExport;

use JKingWeb\Arsse\Arsse;

class OPML {
    public function export(string $user, bool $flat = false): string {
        $folders = [];
        $parents = [0 => null];
        $tags = [];
        $document = new \DOMDocument("1.0", "utf-8");
        $document->formatOutput = true;
        $document->appendChild($document->createElement("opml"));
        $document->documentElement->setAttribute("version", "2.0");
        $document->documentElement->appendChild($document->createElement("head"));
        // create the "root folder" node (the body node, in OPML terms)
        $folders[0] = $document->createElement("body");
        $transaction = Arsse::$db->begin();
        foreach (Arsse::$db->tagSummarize($user) as $r) {
            $sub = $r['subscription'];
            $tag = $r['name'];
            $tag = str_replace(",", "", $tag);
            if (!isset($tags[$sub])) {
                $tags[$sub] = [];
            }
            $tags[$sub][] = $tag;
        }
        if (!$flat) {
            foreach (Arsse::$db->folderList($user) as $r) {
                $parents[$r['id']] = $r['parent'] ?? 0;
                $el = $document->createElement("outline");
                $el->setAttribute("text", $r['name']);
                $folders[$r['id']] = $el;
            }
        }
        foreach (Arsse::$db->subscriptionList($user) as $r) {
            $el = $document->createElement(("outline"));
            $el->setAttribute("text", $r['title']);
            $el->setAttribute("type", "rss");
            $el->setAttribute("xmlUrl", $r['url']);
            if (sizeof($tags[$r['id']])) {
                $el->setAttribute("category", implode(",", $tags[$r['id']]));
            }
            ($folders[$r['folder'] ?? 0] ?? $folders[0])->appendChild($el);
        }
        $transaction->rollback();
        foreach ($folders as $id => $el) {
            $parent = $parents[$id] ?? $document->documentElement;
            $parent->appendChild($el);
        }
        // return the serialization
        return $document->saveXML();
    }
}
