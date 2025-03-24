<?php 
$body = htmlspecialchars($_SERVER['HTTP_USER_AGENT']);

return [
    'mime'    => "text/html",
    'content' => <<<MESSAGE_BODY
<body>$body</body>
MESSAGE_BODY
];
