<?php
if (($_SERVER['PHP_AUTH_USER'] ?? "") === "user" && ($_SERVER['PHP_AUTH_PW'] ?? "") === "pass") {
    return [
    'mime'    => "text/html",
    'content' => <<<MESSAGE_BODY
<html>
<title>Example article</title>
<body>
    <article>
        <p>Partial content, followed by more content<script>document.write('OOK');</script></p>
    </article>
</body>
</html>
MESSAGE_BODY
    ];
} else {
    return [
        'code'    => 401,
        'fields'  => [
            'WWW-Authenticate: Basic realm="Test"',
        ],
    ];
}