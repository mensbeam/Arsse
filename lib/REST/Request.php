<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

class Request {
    public $method = "GET";
    public $url = "";
    public $path ="";
    public $paths = [];
    public $query = "";
    public $type ="";
    public $body = "";

    public function __construct(string $method = null, string $url = null, string $body = null, string $contentType = null) {
        $method = $method ?? $_SERVER['REQUEST_METHOD'];
        $url = $url ?? $_SERVER['REQUEST_URI'];
        $body = $body ?? file_get_contents("php://input");
        if (is_null($contentType)) {
            if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
            } else {
                $contentType = "";
            }
        }
        $this->method = strtoupper($method);
        $this->url = $url;
        $this->body = $body;
        $this->type = $contentType;
        $this->refreshURL();
    }

    public function refreshURL() {
        $url = $this->parseURL($this->url);
        $this->path = $url['path'];
        $this->paths = $url['paths'];
        $this->query = $url['query'];
    }

    protected function parseURL(string $url): array {
        // split the query string from the path
        $parts = explode("?", $url);
        $out = ['path' => $parts[0], 'paths' => [''], 'query' => []];
        // if there is a query string, parse it
        if (isset($parts[1])) {
            // split along & to get key-value pairs
            $query = explode("&", $parts[1]);
            for ($a = 0; $a < sizeof($query); $a++) {
                // split each pair, into no more than two parts
                $data = explode("=", $query[$a], 2);
                // decode the key
                $key = rawurldecode($data[0]);
                // decode the value if there is one
                $value = "";
                if (isset($data[1])) {
                    $value = rawurldecode($data[1]);
                }
                // add the pair to the query output, overwriting earlier values for the same key, is present
                $out['query'][$key] = $value;
            }
        }
        // also include the path as a set of decoded elements
        // if the path is an empty string or just / nothing needs be done
        if (!in_array($out['path'], ["/",""])) {
            $paths = explode("/", $out['path']);
            // remove the first and last empty elements, if present (they are artefacts of the splitting; others should remain)
            if (!strlen($paths[0])) {
                array_shift($paths);
            }
            if (!strlen($paths[sizeof($paths)-1])) {
                array_pop($paths);
            }
            // %-decode each path element
            $paths = array_map(function ($v) {
                return rawurldecode($v);
            }, $paths);
            $out['paths'] = $paths;
        }
        return $out;
    }
}
