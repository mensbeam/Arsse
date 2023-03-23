<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);

namespace JKingWeb\Arsse\ImportExport;

use JKingWeb\Arsse\Arsse;
use JKingWeb\Arsse\Database;
use JKingWeb\Arsse\Db\ExceptionInput as InputException;
use JKingWeb\Arsse\User\ExceptionConflict as UserException;

abstract class AbstractImportExport {
    public function import(string $user, string $data, bool $flat = false, bool $replace = false): bool {
        if (!Arsse::$db->userExists($user)) {
            throw new UserException("doesNotExist", ["action" => __FUNCTION__, "user" => $user]);
        }
        // first extract useful information from the input
        [$feeds, $folders] = $this->parse($data, $flat);
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
        $foldersDb = iterator_to_array(Arsse::$db->folderList($user));
        $feedsDb = iterator_to_array(Arsse::$db->subscriptionList($user));
        $tagsDb = iterator_to_array(Arsse::$db->tagList($user));
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
                $folderMap[$id] = Arsse::$db->folderAdd($user, ['name' => $f['name'], 'parent' => $parent]);
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
                $feedMap[$f['id']] = Arsse::$db->subscriptionAdd($user, $f['url']);
            }
            if (!$found || $replace) {
                // set the subscription's properties, if this is a new feed or we're doing a full replacement
                Arsse::$db->subscriptionPropertiesSet($user, $feedMap[$f['id']], ['title' => $title, 'folder' => $folder]);
                // compile the set of used tags, if this is a new feed or we're doing a full replacement
                foreach ($f['tags'] as $t) {
                    if (!strlen(trim($t))) {
                        // fail if we have any blank tags
                        throw new Exception("invalidTagName");
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
                Arsse::$db->tagAdd($user, ['name' => $tag]);
            }
            Arsse::$db->tagSubscriptionsSet($user, $tag, $subs, $mode, true);
        }
        // finally, if we're performing a replacement, delete any subscriptions, folders, or tags which were not present in the import
        if ($replace) {
            foreach (array_diff(array_column($feedsDb, "id"), $feedMap) as $id) {
                try {
                    Arsse::$db->subscriptionRemove($user, $id);
                } catch (InputException $e) { // @codeCoverageIgnore
                    // ignore errors
                }
            }
            foreach (array_diff(array_column($foldersDb, "id"), $folderMap) as $id) {
                try {
                    Arsse::$db->folderRemove($user, $id);
                } catch (InputException $e) { // @codeCoverageIgnore
                    // ignore errors
                }
            }
            foreach (array_diff(array_column($tagsDb, "name"), array_keys($tagMap)) as $id) {
                try {
                    Arsse::$db->tagRemove($user, $id, true);
                } catch (InputException $e) { // @codeCoverageIgnore
                    // ignore errors
                }
            }
        }
        $tr->commit();
        return true;
    }

    abstract protected function parse(string $data, bool $flat): array;

    abstract public function export(string $user, bool $flat = false): string;

    public function exportFile(string $file, string $user, bool $flat = false): bool {
        $data = $this->export($user, $flat);
        if (!@file_put_contents($file, $data)) {
            // if it fails throw an exception
            $err = file_exists($file) ? "fileUnwritable" : "fileUncreatable";
            throw new Exception($err, ['file' => $file, 'format' => str_replace(__NAMESPACE__."\\", "", get_class($this))]);
        }
        return true;
    }

    public function importFile(string $file, string $user, bool $flat = false, bool $replace = false): bool {
        $data = @file_get_contents($file);
        if ($data === false) {
            // if it fails throw an exception
            $err = file_exists($file) ? "fileUnreadable" : "fileMissing";
            throw new Exception($err, ['file' => $file, 'format' => str_replace(__NAMESPACE__."\\", "", get_class($this))]);
        }
        return $this->import($user, $data, $flat, $replace);
    }
}
