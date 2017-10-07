<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class Query {
    protected $qBody = ""; // main query body
    protected $tBody = []; // main query parameter types
    protected $vBody = []; // main query parameter values
    protected $qCTE = []; // Common table expression query components
    protected $tCTE = []; // Common table expression type bindings
    protected $vCTE = []; // Common table expression binding values
    protected $jCTE = []; // Common Table Expression joins
    protected $qJoin = []; // JOIN clause components
    protected $tJoin = []; // JOIN clause type bindings
    protected $vJoin = []; // JOIN clause binding values
    protected $qWhere = []; // WHERE clause components
    protected $tWhere = []; // WHERE clause type bindings
    protected $vWhere = []; // WHERE clause binding values
    protected $order = []; // ORDER BY clause components
    protected $limit = 0;
    protected $offset = 0;


    public function __construct(string $body = "", $types = null, $values = null) {
        $this->setBody($body, $types, $values);
    }

    public function setBody(string $body = "", $types = null, $values = null): bool {
        $this->qBody = $body;
        if (!is_null($types)) {
            $this->tBody[] = $types;
            $this->vBody[] = $values;
        }
        return true;
    }

    public function setCTE(string $tableSpec, string $body, $types = null, $values = null, string $join = ''): bool {
        $this->qCTE[] = "$tableSpec as ($body)";
        if (!is_null($types)) {
            $this->tCTE[] = $types;
            $this->vCTE[] = $values;
        }
        if (strlen($join)) { // the CTE might only participate in subqueries rather than a join on the main query
            $this->jCTE[] = $join;
        }
        return true;
    }

    public function setJoin(string $join, $types = null, $values = null): bool {
        $this->qJoin[] = $join;
        if (!is_null($types)) {
            $this->tJoin[] = $types;
            $this->vJoin[] = $values;
        }
        return true;
    }

    public function setWhere(string $where, $types = null, $values = null): bool {
        $this->qWhere[] = $where;
        if (!is_null($types)) {
            $this->tWhere[] = $types;
            $this->vWhere[] = $values;
        }
        return true;
    }

    public function setOrder(string $order, bool $prepend = false): bool {
        if ($prepend) {
            array_unshift($this->order, $order);
        } else {
            $this->order[] = $order;
        }
        return true;
    }

    public function setLimit(int $limit, int $offset = 0): bool {
        $this->limit = $limit;
        $this->offset = $offset;
        return true;
    }

    public function pushCTE(string $tableSpec, string $join = ''): bool {
        // this function takes the query body and converts it to a common table expression, putting it at the bottom of the existing CTE stack
        // all WHERE, ORDER BY, and LIMIT parts belong to the new CTE and are removed from the main query
        $this->setCTE($tableSpec, $this->buildQueryBody(), [$this->tBody, $this->tWhere], [$this->vBody, $this->vWhere]);
        $this->jCTE = [];
        $this->tBody = [];
        $this->vBody = [];
        $this->qWhere = [];
        $this->tWhere = [];
        $this->vWhere = [];
        $this->qJoin = [];
        $this->tJoin = [];
        $this->vJoin = [];
        $this->order = [];
        $this->setLimit(0, 0);
        if (strlen($join)) {
            $this->jCTE[] = $join;
        }
        return true;
    }

    public function __toString(): string {
        $out = "";
        if (sizeof($this->qCTE)) {
            // start with common table expressions
            $out .= "WITH RECURSIVE ".implode(", ", $this->qCTE)." ";
        }
        // add the body
        $out .= $this->buildQueryBody();
        return $out;
    }

    public function getQuery(): string {
        return $this->__toString();
    }

    public function getTypes(): array {
        return [$this->tCTE, $this->tBody, $this->tJoin, $this->tWhere];
    }

    public function getValues(): array {
        return [$this->vCTE, $this->vBody, $this->vJoin, $this->vWhere];
    }

    public function getJoinTypes(): array {
        return $this->tJoin;
    }

    public function getJoinValues(): array {
        return $this->vJoin;
    }

    public function getWhereTypes(): array {
        return $this->tWhere;
    }

    public function getWhereValues(): array {
        return $this->vWhere;
    }

    public function getCTETypes(): array {
        return $this->tCTE;
    }

    public function getCTEValues(): array {
        return $this->vCTE;
    }

    protected function buildQueryBody(): string {
        $out = "";
        // add the body
        $out .= $this->qBody;
        if (sizeof($this->qCTE)) {
            // add any joins against CTEs
            $out .= " ".implode(" ", $this->jCTE);
        }
        // add any JOINs
        if (sizeof($this->qJoin)) {
            $out .= " ".implode(" ", $this->qJoin);
        }
        // add any WHERE terms
        if (sizeof($this->qWhere)) {
            $out .= " WHERE ".implode(" AND ", $this->qWhere);
        }
        // add any ORDER BY terms
        if (sizeof($this->order)) {
            $out .= " ORDER BY ".implode(", ", $this->order);
        }
        // add LIMIT and OFFSET if the former is specified
        if ($this->limit > 0) {
            $out .= " LIMIT ".$this->limit;
            if ($this->offset > 0) {
                $out .= " OFFSET ".$this->offset;
            }
        }
        return $out;
    }
}
