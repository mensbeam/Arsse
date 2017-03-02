<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;
use JKingWeb\DrUUID\UUID as UUID;

Trait Common {
    protected $transDepth = 0;

    public function schemaVersion(): int {
        try {
            return $this->data->db->settingGet("schema_version");
        } catch(\Throwable $e) {
            return 0;
        }
    }

    public function begin(): bool {
        $this->exec("SAVEPOINT newssync_".($this->transDepth));
        $this->transDepth += 1;
        return true;
    }

    public function commit(bool $all = false): bool {
        if($this->transDepth==0) return false;
        if(!$all) {
            $this->exec("RELEASE SAVEPOINT newssync_".($this->transDepth - 1));
            $this->transDepth -= 1;
        } else {
            $this->exec("COMMIT TRANSACTION");
            $this->transDepth = 0;
        }
        return true;
    }

    public function rollback(bool $all = false): bool {
        if($this->transDepth==0) return false;
        if(!$all) {
            $this->exec("ROLLBACK TRANSACTION TO SAVEPOINT newssync_".($this->transDepth - 1));
            // rollback to savepoint does not collpase the savepoint
            $this->commit();
            $this->transDepth -= 1;
            if($this->transDepth==0) $this->exec("ROLLBACK TRANSACTION");
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
        if(!$this->data->db->settingSet("lock", $uuid)) return false;
        sleep(1);
        if($this->data->db->settingGet("lock") != $uuid) return false;
        return true;
    }

    public function unlock(): bool {
        return $this->data->db->settingRemove("lock");
    }

    public function isLocked(): bool {
        if($this->schemaVersion() < 1) return false;
        return ($this->query("SELECT count(*) from newssync_settings where key = 'lock'")->getSingle() > 0);
    }

    public function prepare(string $query, string ...$paramType): Statement {
        return $this->prepareArray($query, $paramType);
    }

    public static function formatDate($date, int $precision = self::TS_BOTH): string {
        // Force UTC.
        $timezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        // convert input to a Unix timestamp
        // FIXME: there are more kinds of date representations
        if(is_int($date)) {
            $time = $date;
        } else if($date===null) {
            $time = 0;
        } else {
            $time = strtotime($date);
        }
        // ISO 8601 with space in the middle instead of T.
        $date = date(self::TS_FORMAT[$precision], $time);
        date_default_timezone_set($timezone);
        return $date;
    }
}