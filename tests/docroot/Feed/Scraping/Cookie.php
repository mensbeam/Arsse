<?php 
$body = json_encode($_COOKIE);

return [
    'mime'    => "text/html",
    'content' => <<<MESSAGE_BODY
<body>$body</body>
MESSAGE_BODY
];
