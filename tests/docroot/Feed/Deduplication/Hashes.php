<?php return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>A basic feed for testing</description>

    <item>
        <title>Article The First</title>
        <link>http://example.com/1</link> <!-- This is the correct item -->
        <description>Sample article 1</description>
    </item>
    <item>
        <title>Article The First</title>
        <link>http://example.com/1?</link>
        <description>Sample article 1</description>
    </item>
    <item>
        <title>Article The First</title>
        <link>http://example.com/1?ook=</link>
        <description>Sample article 1</description>
    </item>
    <item>
        <title>Article The Second</title>
        <link>http://example.com/2</link>
        <description>Sample article 4</description>
    </item>
</channel>
</rss>
MESSAGE_BODY
];