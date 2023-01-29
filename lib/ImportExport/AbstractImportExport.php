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
        // add any new feeds, and try an initial fetch on them
        $feedMap = [];
        foreach ($feeds as $k => $f) {
            try {
                $feedMap[$k] = Arsse::$db->subscriptionReserve($user, $f['url']);
            } catch (InputException $e) {
                // duplication is not an error in this case
            }
        }
        foreach ($feedMap as $f) {
            // this may fail with an exception, halting the process before visible modifications are made to the database
            Arsse::$db->subscriptionUpdate($user, $f, true);
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
        $tagMap = [];
        foreach ($feeds as $k => $f) {
            $folder = $folderMap[$f['folder']];
            $title = strlen(trim($f['title'])) ? $f['title'] : null;
            $new = false;
            // find a match for the import feed in existing subscriptions, if necessary; reveal the subscription if it's just been added
            if (!isset($feedMap[$k])) {
                foreach ($feedsDb as $db) {
                    if ($db['url'] === $f['url']) {
                        $feedMap[$k] = (int) $db['id'];
                        break;
                    }
                }
            } else {
                $new = true;
                Arsse::$db->subscriptionReveal($user, $feedMap[$k]);
            }
            if (!$new || $replace) {
                // set the subscription's properties, if this is a new feed or we're doing a full replacement
                Arsse::$db->subscriptionPropertiesSet($user, $feedMap[$k], ['title' => $title, 'folder' => $folder]);
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
                    $tagMap[$t][] = $feedMap[$k];
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
