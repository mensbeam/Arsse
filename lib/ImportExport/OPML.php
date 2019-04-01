<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\User\Exception as UserException;

class OPML {
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
}
