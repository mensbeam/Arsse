<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

class Response {
    const T_JSON = "application/json";
    const T_XML  = "application/xml";
    const T_TEXT = "text/plain";

    public $code;
    public $payload;
    public $type;
    public $fields;


    function __construct(int $code, $payload = null, string $type = self::T_JSON, array $extraFields = []) {
        $this->code    = $code;
        $this->payload = $payload;
        $this->type    = $type;
        $this->fields  = $extraFields;
    }
}