<?php
$item = '
    <item>
        <description>'.str_repeat("0", 1024).'</description>
    </item>';
return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>Example newsfeed title</description>
$item
</channel>
</rss>
MESSAGE_BODY
];
