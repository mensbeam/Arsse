<?php return [
    'code'    => 200,
    'lastMod' => time() - 2000,
    'content' => <<<MESSAGE_BODY
<rss version="2.0">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>A basic feed for testing</description>
</channel>
</rss>
MESSAGE_BODY
];