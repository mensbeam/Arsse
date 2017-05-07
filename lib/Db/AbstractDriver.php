<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;
use JKingWeb\DrUUID\UUID as UUID;

abstract class AbstractDriver implements Driver {
    protected $transDepth = 0;
    protected $transStatus = [];

    public function schemaVersion(): int {
        try {
            return (int) $this->query("SELECT value from arsse_settings where key is schema_version")->getValue();
        } catch(Exception $e) {
            return 0;
        }
    }

    public function begin(): Transaction {
        return new Transaction($this);
    }
    
    public function savepointCreate(): int {
        $this->exec("SAVEPOINT arsse_".(++$this->transDepth));
        $this->transStatus[$this->transDepth] = self::TR_PEND;
        return $this->transDepth;
    }

    public function savepointRelease(int $index = null): bool {
        if(is_null($index)) $index = $this->transDepth;
        if(array_key_exists($index, $this->transStatus)) {
            switch($this->transStatus[$index]) {
                case self::TR_PEND:
                    $this->exec("RELEASE SAVEPOINT arsse_".$index);
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $a = $index;
                    while(++$a && $a <= $this->transDepth) {
                        if($this->transStatus[$a] <= self::TR_PEND) $this->transStatus[$a] = self::TR_PEND_COMMIT;
                    }
                    $out = true;
                    break;
                case self::TR_PEND_COMMIT:
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $out = true;
                    break;
                case self::TR_PEND_ROLLBACK:
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $out = false;
                    break;
                case self::TR_COMMIT:
                case self::TR_ROLLBACK:
                    throw new ExceptionSavepoint("stale", ['action' => "commit", 'index' => $index]);
                default:
                    throw new Exception("unknownSavepointStatus", $this->transStatus[$index]);
            }
            if($index==$this->transDepth) {
                while($this->transDepth > 0 && $this->transStatus[$this->transDepth] > self::TR_PEND) {
                    array_pop($this->transStatus);
                    $this->transDepth--;
                }
            }
            return $out;
        } else {
            throw new ExceptionSavepoint("invalid", ['action' => "commit", 'index' => $index]);
        }
    }

    public function savepointUndo(int $index = null): bool {
        if(is_null($index)) $index = $this->transDepth;
        if(array_key_exists($index, $this->transStatus)) {
            switch($this->transStatus[$index]) {
                case self::TR_PEND:
                    $this->exec("ROLLBACK TRANSACTION TO SAVEPOINT arsse_".$index);
                    $this->exec("RELEASE SAVEPOINT arsse_".$index);
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $a = $index;
                    while(++$a && $a <= $this->transDepth) {
                        if($this->transStatus[$a] <= self::TR_PEND) $this->transStatus[$a] = self::TR_PEND_ROLLBACK;
                    }
                    $out = true;
                    break;
                case self::TR_PEND_COMMIT:
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $out = false;
                    break;
                case self::TR_PEND_ROLLBACK:
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $out = true;
                    break;
                case self::TR_COMMIT:
                case self::TR_ROLLBACK:
                    throw new ExceptionSavepoint("stale", ['action' => "rollback", 'index' => $index]);
                default:
                    throw new Exception("unknownSavepointStatus", $this->transStatus[$index]);
            }
            if($index==$this->transDepth) {
                while($this->transDepth > 0 && $this->transStatus[$this->transDepth] > self::TR_PEND) {
                    array_pop($this->transStatus);
                    $this->transDepth--;
                }
            }
            return $out;
        } else {
            throw new ExceptionSavepoint("invalid", ['action' => "rollback", 'index' => $index]);
        }
    }

    public function lock(): bool {
        if($this->schemaVersion() < 1) return true;
        if($this->isLocked()) return false;
        $uuid = UUID::mintStr();
        try {
            $this->prepare("INSERT INTO arsse_settings(key,value) values(?,?)", "str", "str")->run("lock", $uuid);
        } catch(ExceptionInput $e) {
            return false;
        }
        sleep(1);
        return ($this->query("SELECT value from arsse_settings where key is 'lock'")->getValue() == $uuid);
    }

    public function unlock(): bool {
        if($this->schemaVersion() < 1) return true;
        $this->exec("DELETE from arsse_settings where key is 'lock'");
        return true;
    }

    public function isLocked(): bool {
        if($this->schemaVersion() < 1) return false;
        return ($this->query("SELECT count(*) from arsse_settings where key is 'lock'")->getValue() > 0);
    }

    public function prepare(string $query, ...$paramType): Statement {
        return $this->prepareArray($query, $paramType);
    }
}