<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Feed;

class Exception extends \JKingWeb\Arsse\AbstractException {
    public function __construct($url, \Throwable $e) {
        $className = get_class($e);
        // Convert the exception thrown by PicoFeed to the one to be thrown here.
        $msgID = preg_replace('/^PicoFeed\\\(?:Client|Parser|Reader)\\\([A-Za-z]+)Exception$/', '$1', $className);
        // If the message ID doesn't change then it's unknown.
        $msgID = ($msgID !== $className) ? lcfirst($msgID) : '';
        parent::__construct($msgID, ['url' => $url], $e);
    }
}