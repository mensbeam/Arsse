<?php return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0">
<channel>
    <title>Example title</title>
    <link>http://example.com</link>
    <description>Example description</description>

    <item>
        <pubDate>Sat, 27 May 2017 07:00:00 GMT</pubDate>
        <guid>http://example.com/1</guid>
    </item>
    <item>
        <pubDate>Sat, 27 May 2017 12:00:00 GMT</pubDate>
        <guid>http://example.com/2</guid>
    </item>
    <item>
        <pubDate>Sat, 27 May 2017 16:00:00 GMT</pubDate>
        <guid>http://example.com/3</guid>
    </item>
    <item>
        <pubDate>Sat, 27 May 2017 21:12:00 GMT</pubDate>
        <guid>http://example.com/4</guid>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
