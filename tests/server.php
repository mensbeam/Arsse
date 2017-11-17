<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse;

require_once __DIR__."/bootstrap.php";

/*

This is a so-called router for the the internal PHP Web server:
<http://php.net/manual/en/features.commandline.webserver.php>

It is used to test feed parsing in a controlled environment,
answering specific requests used in tests with the data required
to pass the test.

The parameters of the responses are kept in separate files,
which include the following data:

- Response content
- Response code
- Content type
- Whether to send cache headers
- Last modified
- Any other headers

*/


ignore_user_abort(false);
$defaults = [ // default values for response
    'code'    => 200,
    'content' => "",
    'mime'    => "application/octet-stream",
    'lastMod' => time(),
    'cache'   => true,
    'fields'  => [],
];

$url = explode("?", $_SERVER['REQUEST_URI'])[0];
$base = BASE."tests".\DIRECTORY_SEPARATOR."docroot";
$test = $base.str_replace("/", \DIRECTORY_SEPARATOR, $url).".php";
if (!file_exists($test)) {
    $response = [
        'code'    => 499,
        'content' => "Test '$test' missing.",
        'mime'    => "application/octet-stream",
        'lastMod' => time(),
        'cache'   => true,
        'fields'  => [],
    ];
} else {
    $response = array_merge($defaults, (include $test));
}
// set the response code
http_response_code((int) $response['code']);
// if the response has a body, set the content type and (possibly) the ETag.
if (strlen($response['content'])) {
    header("Content-Type: ".$response['mime']);
    if ($response['cache']) {
        header('ETag: "'.md5($response['content']).'"');
    }
}
// if caching is enabled, set the last-modified date
if ($response['cache']) {
    header("Last-Modified: ".gmdate("D, d M Y H:i:s \G\M\T", $response['lastMod']));
}
// set any other specified fields verbatim
foreach ($response['fields'] as $h) {
    header($h);
}
// send the content
echo $response['content'];
