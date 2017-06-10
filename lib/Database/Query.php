<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Database;

class Query {
    protected $body = "";
    protected $qCTE = []; // Common table expression query components
    protected $tCTE = []; // Common table expression type bindings
    protected $vCTE = []; // Common table expression binding values
    protected $jCTE = []; // Common Table Expression joins
    protected $qWhere = []; // WHERE clause components
    protected $tWhere = []; // WHERE clause type bindings
    protected $vWhere = []; // WHERE clause binding values
    protected $order = []; // ORDER BY clause components
    protected $limit = 0;
    protected $offset = 0;


    function __construct(string $body, string $where = "", string $order = "", int $limit = 0, int $offset = 0) {
        if(strlen($body)) $this->body = $body;
        if(strlen($where)) $this->where[] = $where;
        if(strlen($order)) $this->order[] = $order;
        $this->limit = $limit;
        $this->offset = $offset;
    }

    function setCTE(string $body, $types = null, $values = null, string $join = ''): bool {
        if(!strlen($body)) return false;
        $this->qCTE[] = $body;
        if(!is_null($types)) {
            $this->tCTE[] = $types;
            $this->vCTE[] = $values;
        }
        if(strlen($join)) $this->jCTE[] = $join; // the CTE may only participate in subqueries rather than a join on the main query
        return true;
    }

    function setWhere(string $where, $types = null, $values = null): bool {
        if(!strlen($where)) return false;
        $this->qWhere[] = $where;
        if(!is_null($types)) {
            $this->tWhere[] = $types;
            $this->vWhere[] = $values;
        }
        return true;
    }

    function setOrder(string $oder, bool $prepend = false): bool {
        if(!strlen($order)) return false;
        if($prepend) {
            array_unshift($this->order, $order);
        } else {
            $this->order[] = $order;
        }
        return true;
    }

    function getQuery(bool $pretty = false): string {
        $cte = sizeof($this->qCTE);
        $out = "";
        if($cte) {
            // start with common table expressions
            $out .= "WITH RECURSIVE ".implode(", ", $this->qCTE)." ";
        }
        // add the body
        $out .= $this->body;
        if($cte) {
            // add any joins against CTEs
            $out .= " ".implode(" ", $this->jCTE);
        }
        // add any WHERE terms
        if(sizeof($this->qWhere)) {
            $out .= " WHERE ".implode(" AND ", $this->qWhere);
        }
        // add any ORDER BY terms
        if(sizeof($this->order)) {
            $out .= " ORDER BY ".implode(", ", $this->order);
        }
        // add LIMIT and OFFSET if the former is specified
        if($this->limit > 0) {
            $out .= " LIMIT ".$this->limit;
            if($this->offset > 0) {
                $out .= " OFFSET ".$this->offset;
            }
        }
        return $out;
    }

    function getWhereTypes(): array {
        return $this->tWhere;
    }

    function getWhereValues(): array {
        return $this->vWhere;
    }

    function getCTETypes(): array {
        return $this->tCTE;
    }

    function getCTEValues(): array {
        return $this->vCTE;
    }
}