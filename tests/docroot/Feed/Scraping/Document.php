<?php return [
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
