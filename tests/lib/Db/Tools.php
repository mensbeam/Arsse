<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Test\Db;

trait Tools {
	function prime(\JKingWeb\Arsse\Db\Driver $drv, array $data): bool {
		$drv->begin();
		foreach($data as $table => $info) {
			$cols = implode(",", array_keys($info['columns']));
			$bindings = array_values($info['columns']);
			$params = implode(",", array_fill(0, sizeof($info['columns']), "?"));
			$s = $drv->prepareArray("INSERT INTO $table($cols) values($params)", $bindings);
			foreach($info['rows'] as $row) {
				$this->assertEquals(1, $s->runArray($row)->changes());
			}
		}
		$drv->commit();
		return true;
	}

	function compare(\JKingWeb\Arsse\Db\Driver $drv, array $expected): bool {
		foreach($expected as $table => $info) {
			$cols = implode(",", array_keys($info['columns']));
			foreach($drv->prepare("SELECT $cols from $table")->run() as $num => $row) {
				$row = array_values($row);
				$assertSame($expected[$table]['rows'][$num], $row, "Row $num of table $table does not match expectation.");
			}
		}
	}
}