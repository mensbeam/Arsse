<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

abstract class AbstractHandler implements Handler {
	abstract function __construct(\JKingWeb\Arsse\RuntimeData $data);
	abstract function dispatch(Request $req): Response;

	protected function parseURL(string $url): array {
		// split the query string from the path
		$parts = explode("?", $url);
		$out = ['path' => $parts[0], 'query' => []];
		// if there is a query string, parse it
		if(isset($parts[1])) {
			// split along & to get key-value pairs
			$query = explode("&", $parts[1]);
			for($a = 0; $a < sizeof($query); $a++) {
				// split each pair, into no more than two parts
				$data = explode("=", $query[$a], 2);
				// decode the key
				$key = rawurldecode($data[0]);
				// decode the value if there is one
				$value = "";
				if(isset($data[1])) {
					$value = rawurldecode($data[1]);
				}
				// add the pair to the query output, overwriting earlier values for the same key, is present
				$out['query'][$key] = $value;
			}
		}
		return $out;
	}
}