<?php return [
    'code'    => 200,
    'cache'   => false,
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>A basic feed for testing</description>

    <item>
        <description>Sample article</description>
        <pubDate>Sun, 19 May 2002 15:21:36 GMT</pubDate>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
