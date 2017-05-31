<?php return [
    'mime'    => "application/atom+xml",
    'content' => <<<MESSAGE_BODY
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Example feed title</title>
    <id>urn:uuid:0fd8f6d8-43df-11e7-8511-9b59a0324eb8</id>
    <link rel="alternate" type="text/html" href="http://example.com/"/>

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
    <entry>
        <id>urn:uuid:b0b9698c-43e6-11e7-85b4-53a6b351844b</id>
        <link rel="alternate" type="text/html" href="http://example.com/6"/>
        <title>Article title 6</title>
        <content>Article content 6</content>
        <published>2000-01-06T00:00:00Z</published>
        <updated>2000-01-06T00:00:00Z</updated>
    </entry>
    <entry>
        <id>urn:uuid:7017ed6a-43ee-11e7-b2db-09225eb114d1</id>
        <link rel="alternate" type="text/html" href="http://example.com/7"/>
        <title>Article title 7</title>
        <content>Article content 7</content>
        <published>2000-01-07T00:00:00Z</published>
        <updated>2000-01-07T00:00:00Z</updated>
    </entry>
    <entry>
        <id>urn:uuid:845a98fe-43ee-11e7-a252-cde4cbf755f3</id>
        <link rel="alternate" type="text/html" href="http://example.com/8"/>
        <title>Article title 8</title>
        <content>Article content 8</content>
        <published>2000-01-08T00:00:00Z</published>
        <updated>2000-01-08T00:00:00Z</updated>
    </entry>
</feed>
MESSAGE_BODY
];