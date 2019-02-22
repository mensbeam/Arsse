<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

class Context {
    public $reverse = false;
    public $limit = 0;
    public $offset = 0;
    public $folder;
    public $folderShallow;
    public $subscription;
    public $oldestArticle;
    public $latestArticle;
    public $oldestEdition;
    public $latestEdition;
    public $unread = null;
    public $starred = null;
    public $modifiedSince;
    public $notModifiedSince;
    public $markedSince;
    public $notMarkedSince;
    public $edition;
    public $article;
    public $editions;
    public $articles;
    public $label;
    public $labelName;
    public $labelled = null;
    public $annotated = null;
    public $searchTerms = [];

    protected $props = [];

    protected function act(string $prop, int $set, $value) {
        if ($set) {
            if (is_null($value)) {
                unset($this->props[$prop]);
                $this->$prop = (new \ReflectionClass($this))->getDefaultProperties()[$prop];
            } else {
                $this->props[$prop] = true;
                $this->$prop = $value;
            }
            return $this;
        } else {
            return isset($this->props[$prop]);
        }
    }

    protected function cleanIdArray(array $spec): array {
        $spec = array_values($spec);
        for ($a = 0; $a < sizeof($spec); $a++) {
            if (ValueInfo::id($spec[$a])) {
                $spec[$a] = (int) $spec[$a];
            } else {
                $spec[$a] = 0;
            }
        }
        return array_values(array_filter($spec));
    }

    protected function cleanStringArray(array $spec): array {
        $spec = array_values($spec);
        for ($a = 0; $a < sizeof($spec); $a++) {
            if (strlen($str = ValueInfo::normalize($spec[$a], ValueInfo::T_STRING))) {
                $spec[$a] = $str;
            } else {
                unset($spec[$a]);
            }
        }
        return array_values($spec);
    }

    public function reverse(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function limit(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function offset(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function folder(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function folderShallow(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function subscription(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function latestArticle(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function oldestArticle(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function latestEdition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function oldestEdition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function unread(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function starred(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function modifiedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function notModifiedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function markedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function notMarkedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function edition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function article(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function editions(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function articles(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function label(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelName(string $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelled(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function annotated(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function searchTerms(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
