<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

use JKingWeb\Arsse\Arsse;

class Response {
    const T_JSON = "application/json";
    const T_XML  = "application/xml";
    const T_TEXT = "text/plain";

    public $code;
    public $payload;
    public $type;
    public $fields;


    public function __construct(int $code, $payload = null, string $type = self::T_JSON, array $extraFields = []) {
        $this->code    = $code;
        $this->payload = $payload;
        $this->type    = $type;
        $this->fields  = $extraFields;
    }

    public function output() {
        if (!headers_sent()) {
            try {
                $statusText = Arsse::$lang->msg("HTTP.Status.".$this->code);
            } catch (\JKingWeb\Arsse\Lang\Exception $e) {
                $statusText = "";
            }
            header("Status: ".$this->code." ".$statusText);
            $body = "";
            if (!is_null($this->payload)) {
                header("Content-Type: ".$this->type);
                switch ($this->type) {
                    case self::T_JSON:
                        $body = (string) json_encode($this->payload, \JSON_PRETTY_PRINT);
                        break;
                    default:
                        $body = (string) $this->payload;
                        break;
                }
            }
            foreach ($this->fields as $field) {
                header($field);
            }
            echo $body;
        } else {
            throw new REST\Exception("headersSent");
        }
    }
}
