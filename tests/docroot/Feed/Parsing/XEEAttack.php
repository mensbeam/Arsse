<?php return [
    'mime'    => "application/rss+xml",
    'content' => <<<MESSAGE_BODY
<!DOCTYPE test [
    <!ENTITY xee0 "XEE">
    <!ENTITY xee1 "&xee0;&xee0;&xee0;&xee0;&xee0;&xee0;&xee0;&xee0;&xee0;&xee0;">
    <!ENTITY xee2 "&xee1;&xee1;&xee1;&xee1;&xee1;&xee1;&xee1;&xee1;&xee1;&xee1;">
    <!ENTITY xee3 "&xee2;&xee2;&xee2;&xee2;&xee2;&xee2;&xee2;&xee2;&xee2;&xee2;">
    <!ENTITY xee4 "&xee3;&xee3;&xee3;&xee3;&xee3;&xee3;&xee3;&xee3;&xee3;&xee3;">
    <!ENTITY xee5 "&xee4;&xee4;&xee4;&xee4;&xee4;&xee4;&xee4;&xee4;&xee4;&xee4;">
    <!ENTITY xee6 "&xee5;&xee5;&xee5;&xee5;&xee5;&xee5;&xee5;&xee5;&xee5;&xee5;">
    <!ENTITY xee7 "&xee6;&xee6;&xee6;&xee6;&xee6;&xee6;&xee6;&xee6;&xee6;&xee6;">
    <!ENTITY xee8 "&xee7;&xee7;&xee7;&xee7;&xee7;&xee7;&xee7;&xee7;&xee7;&xee7;">
    <!ENTITY xee9 "&xee8;&xee8;&xee8;&xee8;&xee8;&xee8;&xee8;&xee8;&xee8;&xee8;">
]>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/">
<channel>
    <title>Test feed</title>
    <link>http://example.com/</link>
    <description>Example newsfeed title</description>

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