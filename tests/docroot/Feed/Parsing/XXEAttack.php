<?php return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<!DOCTYPE test [
    <!ENTITY xxe SYSTEM "file:///etc/passwd">
]>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>&xxe;</description>

    <item>
        <dc:identifier>urn:uuid:43fb1908-42ec-11e7-b61b-2b118faca2f2</dc:identifier>
        <guid>http://example.com/1</guid>
        <atom:id>urn:uuid:4c8dbc84-42eb-11e7-9f61-6f83db96854f</atom:id> <!-- Correct ID -->
    </item>
    <item>
        <dc:identifier>urn:uuid:43fb1908-42ec-11e7-b61b-2b118faca2f2</dc:identifier>
        <guid>http://example.com/1</guid> <!-- Correct ID -->
    </item>
    <item>
        <dc:identifier>urn:uuid:43fb1908-42ec-11e7-b61b-2b118faca2f2</dc:identifier> <!-- Correct ID -->
    </item>
    <item>
        <link>http://example.com/2</link>
    </item>
    <item>
        <title>Example title</title>
    </item>
    <item>
        <description>Example content</description>
        <enclosure url="http://example.com/text" type="text/plain"/>
    </item>
</channel>
</rss>
MESSAGE_BODY
];
