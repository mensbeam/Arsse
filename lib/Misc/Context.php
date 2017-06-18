<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class Context {
    use DateFormatter;
    
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
        $spec = $this->dateNormalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function notModifiedSince($spec = null) {
        $spec = $this->dateNormalize($spec);
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function edition(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
    
    function article(int $spec = null) {
        return $this->act(__FUNCTION__, func_num_args(), $spec);
    }
}