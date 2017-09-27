<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

use JKingWeb\Arsse\Misc\Date;
use JKingWeb\Arsse\Misc\ValueInfo;

abstract class AbstractHandler implements Handler {
    abstract public function __construct();
    abstract public function dispatch(Request $req): Response;

    protected function fieldMapNames(array $data, array $map): array {
        $out = [];
        foreach ($map as $to => $from) {
            if (array_key_exists($from, $data)) {
                $out[$to] = $data[$from];
            }
        }
        return $out;
    }
    
    protected function fieldMapTypes(array $data, array $map, string $dateFormat = "sql"): array {
        foreach ($map as $key => $type) {
            if (array_key_exists($key, $data)) {
                if ($type=="datetime" && $dateFormat != "sql") {
                    $data[$key] = Date::transform($data[$key], $dateFormat, "sql");
                } else {
                    settype($data[$key], $type);
                }
            }
        }
        return $data;
    }

    protected function validateInt($id): bool {
        return (bool) (ValueInfo::int($id) & ValueInfo::VALID);
    }

    protected function NormalizeInput(array $data, array $types, string $dateFormat = null): array {
        $out = [];
        foreach ($data as $key => $value) {
            if (!isset($types[$key])) {
                $out[$key] = $value;
                continue;
            }
            if (is_null($value)) {
                $out[$key] = null;
                continue;
            }
            switch ($types[$key]) {
                case "int":
                    if ($this->validateInt($value)) {
                        $out[$key] = (int) $value;
                    }
                    break;
                case "string":
                    $out[$key] = (string) $value;
                    break;
                case "bool":
                    if (is_bool($value)) {
                        $out[$key] = $value;
                    } elseif ($this->validateInt($value)) {
                        $value = (int) $value;
                        if ($value > -1 && $value < 2) {
                            $out[$key] = $value;
                        }
                    } elseif (is_string($value)) {
                        $value = trim(strtolower($value));
                        if ($value=="false") {
                            $out[$key] = false;
                        }
                        if ($value=="true") {
                            $out[$key] = true;
                        }
                    }
                    break;
                case "float":
                    if (is_numeric($value)) {
                        $out[$key] = (float) $value;
                    }
                    break;
                case "datetime":
                    $t = Date::normalize($value, $dateFormat);
                    if ($t) {
                        $out[$key] = $t;
                    }
                    break;
                default:
                    throw new Exception("typeUnknown", $types[$key]);
            }
        }
        return $out;
    }
}
