<?php return [
    'code'    => 200,
    'cache'   => false,
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>A basic feed for testing</description>

    <item>
        <description>Sample article 1</description>
        <pubDate>Sun, 18 May 1995 15:21:36 GMT</pubDate>
        <atom:updated>2002-02-19T15:21:36Z</atom:updated>
    </item>
    <item>
        <description>Sample article 2</description>
        <pubDate>Sun, 19 May 2002 15:21:36 GMT</pubDate>
        <atom:updated>2002-04-19T15:21:36Z</atom:updated>
    </item>
    <item>
        <description>Sample article 3</description>
        <pubDate>Sun, 18 May 2000 15:21:36 GMT</pubDate>
        <atom:updated>1999-05-19T15:21:36Z</atom:updated>
    </item>
</channel>
</rss>
MESSAGE_BODY
];