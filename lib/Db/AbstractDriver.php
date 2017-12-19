<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

use JKingWeb\Arsse\Arsse;

abstract class AbstractDriver implements Driver {
    protected $locked = false;
    protected $transDepth = 0;
    protected $transStatus = [];

    protected abstract function getError(): string;

    /** @codeCoverageIgnore */
    public function schemaVersion(): int {
        // FIXME: generic schemaVersion() will need to be covered for database engines other than SQLite
        try {
            return (int) $this->query("SELECT value from arsse_meta where key is schema_version")->getValue();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function schemaUpdate(int $to, string $basePath = null): bool {
        $ver = $this->schemaVersion();
        if (!Arsse::$conf->dbAutoUpdate) {
            throw new Exception("updateManual", ['version' => $ver, 'driver_name' => $this->driverName()]);
        } elseif ($ver >= $to) {
            throw new Exception("updateTooNew", ['difference' => ($ver - $to), 'current' => $ver, 'target' => $to, 'driver_name' => $this->driverName()]);
        }
        $sep = \DIRECTORY_SEPARATOR;
        $path = ($basePath ?? \JKingWeb\Arsse\BASE."sql").$sep.static::schemaID().$sep;
        // lock the database
        $this->savepointCreate(true);
        for ($a = $this->schemaVersion(); $a < $to; $a++) {
            $this->savepointCreate();
            try {
                $file = $path.$a.".sql";
                if (!file_exists($file)) {
                    throw new Exception("updateFileMissing", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                } elseif (!is_readable($file)) {
                    throw new Exception("updateFileUnreadable", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                }
                $sql = @file_get_contents($file);
                if ($sql===false) {
                    throw new Exception("updateFileUnusable", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]); // @codeCoverageIgnore
                } elseif ($sql==="") {
                    throw new Exception("updateFileIncomplete", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                }
                try {
                    $this->exec($sql);
                } catch (\Throwable $e) {
                    throw new Exception("updateFileError", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a, 'message' => $this->getError()]);
                }
                if ($this->schemaVersion() != $a+1) {
                    throw new Exception("updateFileIncomplete", ['file' => $file, 'driver_name' => $this->driverName(), 'current' => $a]);
                }
            } catch (\Throwable $e) {
                // undo any partial changes from the failed update
                $this->savepointUndo();
                // commit any successful updates if updating by more than one version
                $this->savepointRelease();
                // throw the error received
                throw $e;
            }
            $this->savepointRelease();
        }
        $this->savepointRelease();
        return true;
    }

    public function begin(bool $lock = false): Transaction {
        return new Transaction($this, $lock);
    }
    
    public function savepointCreate(bool $lock = false): int {
        if ($lock && !$this->transDepth) {
            $this->lock();
            $this->locked = true;
        }
        $this->exec("SAVEPOINT arsse_".(++$this->transDepth));
        $this->transStatus[$this->transDepth] = self::TR_PEND;
        return $this->transDepth;
    }

    public function savepointRelease(int $index = null): bool {
        $index = $index ?? $this->transDepth;
        if (array_key_exists($index, $this->transStatus)) {
            switch ($this->transStatus[$index]) {
                case self::TR_PEND:
                    $this->exec("RELEASE SAVEPOINT arsse_".$index);
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $a = $index;
                    while (++$a && $a <= $this->transDepth) {
                        if ($this->transStatus[$a] <= self::TR_PEND) {
                            $this->transStatus[$a] = self::TR_PEND_COMMIT;
                        }
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
                case self::TR_ROLLBACK: //@codeCoverageIgnore
                    throw new Exception("savepointStale", ['action' => "commit", 'index' => $index]);
                default:
                    throw new Exception("savepointStatusUnknown", $this->transStatus[$index]); // @codeCoverageIgnore
            }
            if ($index==$this->transDepth) {
                while ($this->transDepth > 0 && $this->transStatus[$this->transDepth] > self::TR_PEND) {
                    array_pop($this->transStatus);
                    $this->transDepth--;
                }
            }
            if (!$this->transDepth && $this->locked) {
                $this->unlock();
                $this->locked = false;
            }
            return $out;
        } else {
            throw new Exception("savepointInvalid", ['action' => "commit", 'index' => $index]);
        }
    }

    public function savepointUndo(int $index = null): bool {
        $index = $index ?? $this->transDepth;
        if (array_key_exists($index, $this->transStatus)) {
            switch ($this->transStatus[$index]) {
                case self::TR_PEND:
                    $this->exec("ROLLBACK TRANSACTION TO SAVEPOINT arsse_".$index);
                    $this->exec("RELEASE SAVEPOINT arsse_".$index);
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $a = $index;
                    while (++$a && $a <= $this->transDepth) {
                        if ($this->transStatus[$a] <= self::TR_PEND) {
                            $this->transStatus[$a] = self::TR_PEND_ROLLBACK;
                        }
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
                case self::TR_ROLLBACK: //@codeCoverageIgnore
                    throw new Exception("savepointStale", ['action' => "rollback", 'index' => $index]);
                default:
                    throw new Exception("savepointStatusUnknown", $this->transStatus[$index]); // @codeCoverageIgnore
            }
            if ($index==$this->transDepth) {
                while ($this->transDepth > 0 && $this->transStatus[$this->transDepth] > self::TR_PEND) {
                    array_pop($this->transStatus);
                    $this->transDepth--;
                }
            }
            if (!$this->transDepth && $this->locked) {
                $this->unlock(true);
                $this->locked = false;
            }
            return $out;
        } else {
            throw new Exception("savepointInvalid", ['action' => "rollback", 'index' => $index]);
        }
    }

    public function prepare(string $query, ...$paramType): Statement {
        return $this->prepareArray($query, $paramType);
    }
}
