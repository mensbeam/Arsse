<?php return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>Example newsfeed title</description>

    <item>
        <guid>http://localhost:8000/Feed/Scraping/Document</guid>
        <title>Example article</title>
        <description>Partial content</description>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
