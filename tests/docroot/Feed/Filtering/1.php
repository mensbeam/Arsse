<?php return [
    'mime'    => "application/atom+xml",
    'content' => <<<MESSAGE_BODY
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>Example feed title</title>
    <id>urn:uuid:0fd8f6d8-43df-11e7-8511-9b59a0324eb8</id>
    <link rel="alternate" type="text/html" href="http://localhost:8000/"/>

    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89790</id>
        <title>A</title>
        <category>Z</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89791</id>
        <title>B</title>
        <category>Y</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89792</id>
        <title>C</title>
        <category>X</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89793</id>
        <title>D</title>
        <category>W</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89794</id>
        <title>E</title>
        <category>V</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89795</id>
        <title>F</title>
        <category>U</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89796</id>
        <title>G</title>
        <category>T</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89797</id>
        <title>H</title>
        <category>S</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89798</id>
        <title>I</title>
        <category>R</category>
    </entry>
    <entry>
        <id>urn:uuid:6d4c7964-43e1-11e7-92bd-4fed65d89799</id>
        <title>J</title>
        <category>Q</category>
    </entry>
</feed>
MESSAGE_BODY
];
