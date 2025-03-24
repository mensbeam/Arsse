<?php 
$title = $_SERVER['HTTP_USER_AGENT'];
$body = <<<MESSAGE_BODY
<rss version="2.0">
<channel>
    <title>$title</title>
    <link>http://localhost:8000/</link>
    <description>User agent test</description>
    <item>
        <guid>http://localhost:8000/Feed/Scraping/UserAgent</guid>
        <title>Example article</title>
        <description>Partial content</description>
    </item>
</channel>
</rss>
MESSAGE_BODY;

return [
    'mime'    => "application/rss+xml",
    'content' => $body
];