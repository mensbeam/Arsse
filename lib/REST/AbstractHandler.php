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
                    if (valueInfo::int($value) & ValueInfo::VALID) {
                        $out[$key] = (int) $value;
                    }
                    break;
                case "string":
                    if(is_bool($value)) {
                        $out[$key] = var_export($value, true);
                    } elseif (!is_scalar($value)) {
                        break;
                    } else {
                        $out[$key] = (string) $value;
                    }
                    break;
                case "bool":
                    $test = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                    if (!is_null($test)) {
                        $out[$key] = $test;
                    }
                    break;
                case "float":
                    $test = filter_var($value, \FILTER_VALIDATE_FLOAT);
                    if ($test !== false) {
                        $out[$key] = $test;
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
