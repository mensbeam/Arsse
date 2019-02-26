<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Context;

use JKingWeb\Arsse\Misc\ValueInfo;

class ExclusionContext {
    public $folder;
    public $folderShallow;
    public $subscription;
    public $edition;
    public $article;
    public $editions;
    public $articles;
    public $label;
    public $labelName;
    public $annotationTerms;
    public $searchTerms;
    public $titleTerms;
    public $authorTerms;

    protected $props = [];
    protected $parent;

    public function __construct(self $c = null) {
        $this->parent = $c;
    }

    public function __clone() {
        if ($this->parent) {
            $t = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT, 2)[1];
            if (($t['object'] ?? null) instanceof self && $t['function'] === "__clone") {
                $this->parent = $t['object'];
            }
        }
    }

    public function __destruct() {
        unset($this->parent);
    }

    protected function act(string $prop, int $set, $value) {
        if ($set) {
            if (is_null($value)) {
                unset($this->props[$prop]);
                $this->$prop = (new \ReflectionClass($this))->getDefaultProperties()[$prop];
            } else {
                $this->props[$prop] = true;
                $this->$prop = $value;
            }
            return $this->parent ?? $this;
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
        $stop = sizeof($spec);
        for ($a = 0; $a < $stop; $a++) {
            if (strlen($str = ValueInfo::normalize($spec[$a], ValueInfo::T_STRING | ValueInfo::M_DROP) ?? "")) {
                $spec[$a] = $str;
            } else {
                unset($spec[$a]);
            }
        }
        return array_values($spec);
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
}
