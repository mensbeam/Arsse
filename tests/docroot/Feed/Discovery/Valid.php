<?php return [
    'mime'    => "text/html",
    'content' => <<<MESSAGE_BODY
<html>
<title>Example article</title>
<link rel="alternate" type="application/rss+xml" href="http://localhost:8000/Feed/Discovery/Feed">
<link rel="alternate" type="application/rss+xml" href="http://localhost:8000/Feed/Discovery/Missing">
</html>
MESSAGE_BODY
];
