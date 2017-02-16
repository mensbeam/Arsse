<?php
declare(strict_types=1);
namespace JKingWeb\NewsSync\Db;

Trait CommonSQLite3 {
    
    static public function driverName(): string {
        return "SQLite 3";
    }

    public function schemaVersion(): int {
        return $this->query("PRAGMA user_version")->getSingle();
    }

    public function update(int $to): bool {
        $ver = $this->schemaVersion();
        if(!$this->data->conf->dbSQLite3AutoUpd)  throw new Update\Exception("manual", ['version' => $ver, 'driver_name' => $this->driverName()]);
        if($ver >= $to) throw new Update\Exception("tooNew", ['difference' => ($ver - $to), 'current' => $ver, 'target' => $to, 'driver_name' => $this->driverName()]);
        $sep = \DIRECTORY_SEPARATOR;
        $path = \JKingWeb\NewsSync\BASE."sql".$sep."SQLite3".$sep;
        $this->lock();
        $this->begin();
        for($a = $ver; $a < $to; $a++) {
            $this->begin();
            try {
                $file = $path.$a.".sql";
                if(!file_exists($file)) throw new Update\Exception("fileMissing", ['file' => $file, 'driver_name' => $this->driverName()]);
                if(!is_readable($file)) throw new Update\Exception("fileUnreadable", ['file' => $file, 'driver_name' => $this->driverName()]);
                $sql = @file_get_contents($file);
                if($sql===false) throw new Update\Exception("fileUnusable", ['file' => $file, 'driver_name' => $this->driverName()]);
                $this->exec($sql);
            } catch(\Throwable $e) {
                // undo any partial changes from the failed update
                $this->rollback();
                // commit any successful updates if updating by more than one version
                $this->commit(true);
                // throw the error received
                throw $e;
            }
            $this->commit();
        }
        $this->unlock();
        $this->commit();
        return true;
    }

    public function exec(string $query): bool {
        return (bool) $this->db->exec($query);
    }
}