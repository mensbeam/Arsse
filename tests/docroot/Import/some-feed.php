<?php return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0">
<channel>
    <title>Some feed</title>
    <link>http://localhost:8000/</link>
    <description>Just a generic feed</description>

    <item>
        <guid>http://localhost:8000/Import/some-feed/some-article</guid>
        <title>Some article</title>
        <description>This feed is used only to demonstrate failure modes external to the feed itself</description>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
