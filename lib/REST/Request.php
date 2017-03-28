<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

class Request {
	public $method = "GET";
	public $url = "";
	public $type ="";
	public $stream = "php://input";

	function __construct(string $method = null, string $url = null, string $bodyStream = null, string $contentType = null) {
		if(is_null($method))      $method = $_SERVER['REQUEST_METHOD'];
		if(is_null($url))         $url = $_SERVER['REQUEST_URI'];
		if(is_null($bodyStream))  $bodyStream = "php://input";
		if(is_null($contentType)) {
			if(isset($_SERVER['HTTP_CONTENT_TYPE'])) $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
		}
		$this->method = strtoupper($method);
		$this->url = $url;
		$this->stream = $bodyStream;
		$this->type = $contentType;
	}
}