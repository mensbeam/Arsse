<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

use JKingWeb\Arsse\Misc\ValueInfo;

class Target {
    public $relative = false;
    public $index = false;
    public $path = [];
    public $query = "";
    public $fragment = "";
    
    public function __construct(string $target) {
        $target = $this->parseFragment($target);
        $target = $this->parseQuery($target);
        $this->path = $this->parsePath($target);
    }

    public function __toString(): string {
        $out = "";
        $path = [];
        foreach ($this->path as $segment) {
            if (is_null($segment)) {
                if (!$path) {
                    $path[] = "..";
                } else {
                    continue;
                }
            } elseif ($segment==".") {
                $path[] = "%2E";
            } elseif ($segment=="..") {
                $path[] = "%2E%2E";
            } else {
                $path[] = rawurlencode(ValueInfo::normalize($segment, ValueInfo::T_STRING));
            }
        }
        $path = implode("/", $path);
        if (!$this->relative) {
            $out .= "/";
        }
        $out .= $path;
        if ($this->index && strlen($path)) {
            $out .= "/";
        }
        if (strlen($this->query)) {
            $out .= "?".$this->query;
        }
        if (strlen($this->fragment)) {
            $out .= "#".rawurlencode($this->fragment);
        }
        return $out;
    }

    public static function normalize(string $target): string {
        return (string) new self($target);
    }

    protected function parseFragment(string $target): string {
        // store and strip off any fragment identifier and return the target without a fragment
        $pos = strpos($target,"#");
        if ($pos !== false) {
            $this->fragment = rawurldecode(substr($target, $pos + 1));
            $target = substr($target, 0, $pos);
        }
        return $target;
    }

    protected function parseQuery(string $target): string {
        // store and strip off any query string and return the target without a query
        // note that the function assumes any fragment identifier has already been stripped off
        // unlike the other parts the query string is currently neither parsed nor normalized
        $pos = strpos($target,"?");
        if ($pos !== false) {
            $this->query = substr($target, $pos + 1);
            $target = substr($target, 0, $pos);
        }
        return $target;
    }

    protected function parsePath(string $target): array {
        // note that the function assumes any fragment identifier or query has already been stripped off
        // syntax-based normalization is applied to the path segments (see RFC 3986 sec. 6.2.2)
        // duplicate slashes are NOT collapsed
        if (substr($target, 0, 1)=="/") {
            // if the path starts with a slash, strip it off
            $target = substr($target, 1);
        } else {
            // otherwise this is a relative target
            $this->relative = true;
        }
        if (!strlen($target)) {
            // if the target is an empty string, this is an index target
            $this->index = true;
        } elseif (substr($target, -1, 1)=="/") {
            // if the path ends in a slash, this is an index target and the slash should be stripped off
            $this->index = true;
            $target = substr($target, 0, strlen($target) -1);
        }
        // after stripping, explode the path parts
        if (strlen($target)) {
            $target = explode("/", $target);
            $out = [];
            // resolve relative path segments and decode each retained segment
            foreach($target as $index => $segment) {
                if ($segment==".") {
                    // self-referential segments can be ignored
                    continue;
                } elseif ($segment=="..") {
                    if ($index==0) {
                        // if the first path segment refers to its parent (which we don't know about) we cannot output a correct path, so we do the best we can
                        $out[] = null;
                    } else {
                        // for any other segments after the first we pop off the last stored segment
                        array_pop($out);
                    }
                } else {
                    // any other segment is decoded and retained
                    $out[] = rawurldecode($segment);
                }
            }
            return $out;
        } else {
            return [];
        }
    }
}