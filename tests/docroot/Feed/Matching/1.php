<?php return [
    'mime'    => "application/atom+xml",
    'content' => <<<MESSAGE_BODY
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Example feed title</title>
    <id>urn:uuid:0fd8f6d8-43df-11e7-8511-9b59a0324eb8</id>
    <link rel="alternate" type="text/html" href="http://example.com/"/>

    <entry>
        <id>urn:uuid:df329114-43df-11e7-9f23-a938604d62f8</id>
        <link rel="alternate" type="text/html" href="http://example.com/1"/>
        <title>Article title 1</title>
        <content>Article content 1</content>
        <published>2000-01-01T00:00:00Z</published>
        <updated>2000-01-01T00:00:00Z</updated>
    </entry>
    <entry>
        <id>urn:uuid:24382fa8-43e0-11e7-bd9c-559df0ea4b9b</id>
        <link rel="alternate" type="text/html" href="http://example.com/2"/>
        <title>Article title 2</title>
        <content>Article content 2</content>
        <published>2000-01-02T00:00:00Z</published>
        <updated>2000-01-02T00:00:00Z</updated>
    </entry>
    <entry>
        <id>urn:uuid:03b9f558-43e1-11e7-87c5-ebaab4fd4cd1</id>
        <link rel="alternate" type="text/html" href="http://example.com/3"/>
        <title>Article title 3</title>
        <content>Article content 3</content>
        <published>2000-01-03T00:00:00Z</published>
        <updated>2000-01-03T00:00:00Z</updated>
    </entry>
    <entry>
        <id>urn:uuid:3d5f5154-43e1-11e7-ba11-1dcae392a974</id>
        <link rel="alternate" type="text/html" href="http://example.com/4"/>
        <title>Article title 4</title>
        <content>Article content 4</content>
        <published>2000-01-04T00:00:00Z</published>
        <updated>2000-01-04T00:00:00Z</updated>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89793</id>
        <link rel="alternate" type="text/html" href="http://example.com/5"/>
        <title>Article title 5</title>
        <content>Article content 5</content>
        <published>2000-01-05T00:00:00Z</published>
        <updated>2000-01-05T00:00:00Z</updated>
    </entry>
</feed>
MESSAGE_BODY
];
