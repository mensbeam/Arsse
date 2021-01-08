<?php return [
    'code'    => 200,
    'cache'   => false,
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>Test feed</title>
    <link>http://localhost:8000/</link>
    <description>A basic feed for testing</description>

    <item>
        <description>Sample article</description>
        <pubDate>Sun, 18 May 2002 15:21:36 GMT</pubDate>
        <atom:updated>2002-05-19T15:21:36Z</atom:updated>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
