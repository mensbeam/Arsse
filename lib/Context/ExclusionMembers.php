<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

use JKingWeb\Arsse\Misc\ValueInfo;
use JKingWeb\Arsse\Misc\Date;

trait ExclusionMembers {
    public $folder = null;
    public $folders = [];
    public $folderShallow = null;
    public $foldersShallow = [];
    public $tag = null;
    public $tags = [];
    public $tagName = null;
    public $tagNames = [];
    public $subscription = null;
    public $subscriptions = [];
    public $edition = null;
    public $editions = [];
    public $article = null;
    public $articles = [];
    public $label = null;
    public $labels = [];
    public $labelName = null;
    public $labelNames = [];
    public $annotationTerms = [];
    public $searchTerms = [];
    public $titleTerms = [];
    public $authorTerms = [];
    public $articleRange = [null, null];
    public $editionRange = [null, null];
    public $modifiedRange = [null, null];
    public $modifiedRanges = [];
    public $markedRange = [null, null];
    public $markedRanges = [];

    protected function cleanIdArray(array $spec, bool $allowZero = false): array {
        $spec = array_values($spec);
        for ($a = 0; $a < sizeof($spec); $a++) {
            if (ValueInfo::id($spec[$a], $allowZero)) {
                $spec[$a] = (int) $spec[$a];
            } else {
                $spec[$a] = null;
            }
        }
        return array_values(array_unique(array_filter($spec, function($v) {
            return !is_null($v);
        })));
    }

    protected function cleanStringArray(array $spec): array {
        $spec = array_values($spec);
        $stop = sizeof($spec);
        for ($a = 0; $a < $stop; $a++) {
            if (strlen($str = ValueInfo::normalize($spec[$a], ValueInfo::T_STRING | ValueInfo::M_DROP) ?? "")) {
                $spec[$a] = $str;
            } else {
                unset($spec[$a]);
            }
        }
        return array_values(array_unique($spec));
    }

    protected function cleanDateRangeArray(array $spec): array {
        $spec = array_values($spec);
        $stop = sizeof($spec);
        for ($a = 0; $a < $stop; $a++) {
            if (!is_array($spec[$a]) || sizeof($spec[$a]) !== 2) {
                unset($spec[$a]);
            } else {
                $spec[$a] = ValueInfo::normalize($spec[$a], ValueInfo::T_DATE | ValueInfo::M_ARRAY | ValueInfo::M_DROP);
                if ($spec[$a] === [null, null]) {
                    unset($spec[$a]);
                }
            }
        }
        return array_values(array_unique($spec, \SORT_REGULAR));
    }

    public function folder(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function folders(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec, true);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function folderShallow(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function foldersShallow(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec, true);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function tag(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function tags(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function tagName(string $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function tagNames(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function subscription(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function subscriptions(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec);
        }
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

    public function labels(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanIdArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelName(string $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelNames(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function annotationTerms(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function searchTerms(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function titleTerms(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function authorTerms(array $spec = null) {
        if (isset($spec)) {
            $spec = $this->cleanStringArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function articleRange(?int $start = null, ?int $end = null) {
        if ($start === null && $end === null) {
            $spec = null;
        } else {
            $spec = [$start, $end];
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function editionRange(?int $start = null, ?int $end = null) {
        if ($start === null && $end === null) {
            $spec = null;
        } else {
            $spec = [$start, $end];
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function modifiedRange($start = null, $end = null) {
        if ($start === null && $end === null) {
            $spec = null;
        } else {
            $spec = [Date::normalize($start), Date::normalize($end)];
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function modifiedRanges(array $spec) {
        if (isset($spec)) {
            $spec = $this->cleanDateRangeArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }


    public function markedRange($start = null, $end = null) {
        if ($start === null && $end === null) {
            $spec = null;
        } else {
            $spec = [Date::normalize($start), Date::normalize($end)];
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function markedRanges(array $spec) {
        if (isset($spec)) {
            $spec = $this->cleanDateRangeArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
