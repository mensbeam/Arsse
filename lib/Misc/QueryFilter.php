<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class QueryFilter {
    protected $qWhere = []; // WHERE clause components
    protected $tWhere = []; // WHERE clause type bindings
    protected $vWhere = []; // WHERE clause binding values
    protected $qWhereNot = []; // WHERE NOT clause components
    protected $tWhereNot = []; // WHERE NOT clause type bindings
    protected $vWhereNot = []; // WHERE NOT clause binding values
    protected $filterRestrictive = true; // Whether to glue WHERE conditions with OR (false) or AND (true)

    public function setWhere(string $where, $types = null, $values = null): self {
        $this->qWhere[] = $where;
        if (!is_null($types)) {
            $this->tWhere[] = $types ?? [];
            $this->vWhere[] = $values;
        }
        return $this;
    }

    public function setWhereNot(string $where, $types = null, $values = null): self {
        $this->qWhereNot[] = $where;
        if (!is_null($types)) {
            $this->tWhereNot[] = $types;
            $this->vWhereNot[] = $values;
        }
        return $this;
    }

    public function setWhereGroup(self $filter): self {
        $this->qWhere[] = "(".$filter->buildWhereBody().")";
        $this->tWhere[] = $filter->getWhereTypes();
        $this->vWhere[] = $filter->getWhereValues();
        return $this;
    }

    public function setWhereRestrictive(bool $restrictive): self {
        $this->filterRestrictive = $restrictive;
        return $this;
    }

    protected function getWhereTypes(): array {
        return ValueInfo::flatten([$this->tWhere, $this->tWhereNot]);
    }

    protected function getWhereValues(): array {
        return ValueInfo::flatten([$this->vWhere, $this->vWhereNot]);
    }

    public function getTypes(): array {
        return $this->getWhereTypes();
    }

    public function getValues(): array {
        return $this->getWhereValues();
    }

    protected function buildWhereBody(): string {
        $glue = $this->filterRestrictive ? " AND " : " OR ";
        $where = implode($glue, $this->qWhere);
        $whereNot = implode(" OR ", $this->qWhereNot);
        $whereNot = strlen($whereNot) ? "NOT ($whereNot)" : "";
        return implode($glue, array_filter([$where, $whereNot]));
    }

    public function __toString() {
        return $this->buildWhereBody();
    }
}
