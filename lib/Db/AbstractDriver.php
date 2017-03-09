<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;
use JKingWeb\DrUUID\UUID as UUID;

abstract class AbstractDriver implements Driver {
    protected $transDepth = 0;

    public function schemaVersion(): int {
        try {
            return (int) $this->query("SELECT value from newssync_settings where key is schema_version")->getValue();
        } catch(Exception $e) {
            return 0;
        }
    }

    public function begin(): bool {
        $this->exec("SAVEPOINT newssync_".(++$this->transDepth));
        return true;
    }

    public function commit(bool $all = false): bool {
        if($this->transDepth==0) return false;
        if(!$all) {
            $this->exec("RELEASE SAVEPOINT newssync_".($this->transDepth--));
        } else {
            $this->exec("COMMIT TRANSACTION");
            $this->transDepth = 0;
        }
        return true;
    }

    public function rollback(bool $all = false): bool {
        if($this->transDepth==0) return false;
        if(!$all) {
            $this->exec("ROLLBACK TRANSACTION TO SAVEPOINT newssync_".($this->transDepth));
            // rollback to savepoint does not collpase the savepoint
            $this->exec("RELEASE SAVEPOINT newssync_".($this->transDepth--));
        } else {
            $this->exec("ROLLBACK TRANSACTION");
            $this->transDepth = 0;
        }
        return true;
    }

    public function lock(): bool {
        if($this->schemaVersion() < 1) return true;
        if($this->isLocked()) return false;
        $uuid = UUID::mintStr();
        try {
            $this->prepare("INSERT INTO newssync_settings(key,value) values(?,?)", "str", "str")->run("lock", $uuid);
        } catch(ExceptionInput $e) {
            return false;
        }
        sleep(1);
        return ($this->query("SELECT value from newssync_settings where key is 'lock'")->getValue() == $uuid);
    }

    public function unlock(): bool {
        $this->exec("DELETE from newssync_settings where key is 'lock'");
        return true;
    }

    public function isLocked(): bool {
        if($this->schemaVersion() < 1) return false;
        return ($this->query("SELECT count(*) from newssync_settings where key is 'lock'")->getValue() > 0);
    }

    public function prepare(string $query, string ...$paramType): Statement {
        return $this->prepareArray($query, $paramType);
    }
}