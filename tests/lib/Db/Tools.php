<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Db;

trait Tools {
	protected $drv;
	
	
	function primeDatabase(array $data): bool {
		$this->drv->begin();
		foreach($data as $table => $info) {
			$cols = implode(",", array_keys($info['columns']));
			$bindings = array_values($info['columns']);
			$params = implode(",", array_fill(0, sizeof($info['columns']), "?"));
			$s = $this->drv->prepareArray("INSERT INTO $table($cols) values($params)", $bindings);
			foreach($info['rows'] as $row) {
				$this->assertEquals(1, $s->runArray($row)->changes());
			}
		}
		$this->drv->commit();
		return true;
	}

	function compareExpectations(array $expected): bool {
		foreach($expected as $table => $info) {
			$cols = implode(",", array_keys($info['columns']));
			foreach($this->drv->prepare("SELECT $cols from $table")->run() as $num => $row) {
				$row = array_values($row);
				$this->assertSame($expected[$table]['rows'][$num], $row, "Row ".($num+1)." of table $table does not match expectations at array index $num.");
			}
		}
		return true;
	}

	function primeExpectations(array $source, array $tableSpecs = null): array {
		$out = [];
		foreach($tableSpecs as $table => $columns) {
			if(!isset($source[$table])) {
				$this->assertTrue(false, "Source for expectations does not contain requested table $table.");
				return [];
			}
			$out[$table] = [
				'columns' => [],
				'rows'    => [],
			];
			$transformations = [];
			foreach($columns as $target => $col) {
				if(!isset($source[$table]['columns'][$col])) {
					$this->assertTrue(false, "Source for expectations does not contain requested column $col of table $table."); 
					return [];
				}
				$found = array_search($col, array_keys($source[$table]['columns']));
				$transformations[$found] = $target;
				$out[$table]['columns'][$col] = $source[$table]['columns'][$col];
			}
			foreach($source[$table]['rows'] as $sourceRow) {
				$newRow = [];
				foreach($transformations as $from => $to) {
					$newRow[$to] = $sourceRow[$from];
				}
				$out[$table]['rows'][] = $newRow;
			}
		}
		return $out;
	}
}