<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\REST;

abstract class AbstractHandler implements Handler {
    abstract function __construct();
    abstract function dispatch(Request $req): Response;

    protected function mapFieldNames(array $data, array $map, bool $overwrite = false): array {
        foreach($map as $from => $to) {
            if(array_key_exists($from, $data)) {
                if($overwrite || !array_key_exists($to, $data)) $data[$to] = $data[$from];
                unset($data[$from]);
            }
        }
        return $data;
    }    
    
    protected function mapFieldTypes(array $data, array $map): array {
        foreach($map as $key => $type) {
            if(array_key_exists($key, $data)) settype($data[$key], $type);
        }
        return $data;
    }

    protected function validateId($id):bool {
        try {
            $ch1 = strval(intval($id));
            $ch2 = strval($id);
        } catch(\Throwable $e) {
            return false;
        }
        return ($ch1 === $ch2);
    }

}