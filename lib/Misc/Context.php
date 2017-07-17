<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;
use JKingWeb\Arsse\Misc\Date;

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

    protected $props = [];

    protected function act(string $prop, int $set, $value) {
        if($set) {
            $this->props[$prop] = true;
            $this->$prop = $value;
            return $this;
        } else {
            return isset($this->props[$prop]);
        }
    }

    protected function cleanArray(array $spec): array {
        $spec = array_values($spec);
        for($a = 0; $a < sizeof($spec); $a++) {
            $id = $spec[$a];
            if(is_int($id) && $id > -1) continue;
            if(is_float($id) && !fmod($id, 1) && $id >= 0) {
                $spec[$a] = (int) $id;
                continue;
            }
            if(is_string($id)) {
                $ch1 = strval(@intval($id));
                $ch2 = strval($id);
                if($ch1 !== $ch2 || $id < 1) $id = 0;
            } else {
                $id = 0;
            }
            $spec[$a] = (int) $id;
        }
        return array_values(array_filter($spec));
    }
    
    function reverse(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function limit(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function offset(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function folder(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function subscription(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function latestEdition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function oldestEdition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function unread(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function starred(bool $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function modifiedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function notModifiedSince($spec = null) {
        $spec = Date::normalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function edition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function article(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    function editions(array $spec = null) {
        if($spec) $spec = $this->cleanArray($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }

    function articles(array $spec = null) {
        if($spec) $spec = $this->cleanArray($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}