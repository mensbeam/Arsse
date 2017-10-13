<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

class Context {
    public $reverse = false;
    public $limit = 0;
    public $offset = 0;
    public $folder;
    public $subscription;
    public $oldestEdition;
    public $latestEdition;
    public $unread = false;
    public $starred = false;
    public $modifiedSince;
    public $notModifiedSince;
    public $edition;
    public $article;
    public $editions;
    public $articles;
    public $label;
    public $labelName;

    protected $props = [];

    protected function act(string $prop, int $set, $value) {
        if ($set) {
            $this->props[$prop] = true;
            $this->$prop = $value;
            return $this;
        } else {
            return isset($this->props[$prop]);
        }
    }

    protected function cleanArray(array $spec): array {
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
    
    public function subscription(int $spec = null) {
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
    
    public function edition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    public function article(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function editions(array $spec = null) {
        if ($spec) {
            $spec = $this->cleanArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function articles(array $spec = null) {
        if ($spec) {
            $spec = $this->cleanArray($spec);
        }
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function label(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    public function labelName(string $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}
