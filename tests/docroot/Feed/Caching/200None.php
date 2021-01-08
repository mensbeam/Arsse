<?php return [
    'code'    => 200,
    'cache'   => false,
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0">
<channel>
    <title>Test feed</title>
    <link>http://localhost:8000/</link>
    <description>A basic feed for testing</description>

    <item>
        <description>Sample article 1</description>
    </item>
    <item>
        <description>Sample article 2</description>
    </item>
    <item>
        <description>Sample article 3</description>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
