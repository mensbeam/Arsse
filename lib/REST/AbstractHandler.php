<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

abstract class AbstractHandler implements Handler {
    use \JKingWeb\Arsse\Misc\DateFormatter;

    abstract function __construct();
    abstract function dispatch(Request $req): Response;

    protected function fieldMapNames(array $data, array $map): array {
        $out = [];
        foreach($map as $to => $from) {
            if(array_key_exists($from, $data)) {
                $out[$to] = $data[$from];
            }
        }
        return $out;
    }    
    
    protected function fieldMapTypes(array $data, array $map, string $dateFormat = "sql"): array {
        foreach($map as $key => $type) {
            if(array_key_exists($key, $data)) {
                if($type=="datetime" && $dateFormat != "sql") {
                    $data[$key] = $this->dateTransform($data[$key], $dateFormat, "sql");
                } else {
                    settype($data[$key], $type);
                }
            }
        }
        return $data;
    }

    protected function validateInt($id): bool {
        try {
            $ch1 = strval(intval($id));
            $ch2 = strval($id);
        } catch(\Throwable $e) {
            return false;
        }
        return ($ch1 === $ch2);
    }

    protected function NormalizeInput(array $data, array $types, string $dateFormat = null): array {
        $out = [];
        foreach($data as $key => $value) {
            if(!isset($types[$key])) {
                $out[$key] = $value;
                continue;
            }
            if(is_null($value)) {
                $out[$key] = null;
                continue;
            }
            switch($types[$key]) {
                case "int":
                    if($this->validateInt($value)) $out[$key] = (int) $value;
                    break;
                case "string":
                    $out[$key] = (string) $value;
                    break;
                case "bool":
                    if(is_bool($value)) {
                        $out[$key] = $value;
                    } else if($this->validateInt($value)) {
                        $value = (int) $value;
                        if($value > -1 && $value < 2) $out[$key] = $value;
                    } else if(is_string($value)) {
                        $value = trim(strtolower($value));
                        if($value=="false") $out[$key] = false;
                        if($value=="true") $out[$key] = true;
                    }
                    break;
                case "float":
                    if(is_numeric($value)) $out[$key] = (float) $value;
                    break;
                case "datetime":
                    $t = $this->dateNormalize($value, $dateFormat);
                    if($t) $out[$key] = $t;
                    break;
                default:
                    throw new Exception("typeUnknown", $types[$key]);
            }
        }
        return $out;
    }
}