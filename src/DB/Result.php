<?php
namespace rollingWolf\QueryablePHP\DB;

use rollingWolf\QuyeryPHP\Exception\QueryablePHP;
use rollingWolf\QueryablePHP\Helper;

class Result
{
    public $length = 0;
    public $rows = [];

    function __construct()
    {
        foreach (func_get_args() as $arg) {
            $this->push($arg);
        }
    }
    function cmp($a, $b)
    {
        if (!array_key_exists($this->_sortKey, $a)) {
            return -$this->_sortVal;
        } elseif (!array_key_exists($this->_sortKey, $b)) {
            return -$this->_sortVal;
        }
        if (is_string($a[$this->_sortKey]) && is_string($b[$this->_sortKey])) {
            return strcoll($a[$this->_sortKey], $b[$this->_sortKey]) * $this->_sortVal;
        }
        else {
            return $a[$this->_sortKey] > $b[$this->_sortKey] ? $this->_sortVal : -$this->_sortVal;
        }
    }
    function push($object)
    {
        if (is_object($object) || is_array($object))
        {
            $this->rows = Helper::jsonDecode(json_encode($object), true);
        }
        $this->length = count($this->rows);
        return $this;
    }
    function sort($object)
    {
        $object = Helper::jsonDecode($object, true);

        $this->_sortKey = Helper::firstKey($object);
        $this->_sortVal = $object[$this->_sortKey];

        usort($this->rows, array('\rollingWolf\QueryablePHP\DB\Result', 'cmp'));

        return $this;
    }
    function limit($_l)
    {
        $lim = intval($_l);
        if (!is_numeric($lim))
            return $this;
        $this->rows = array_splice($this->rows, $lim, count($this->rows) - $lim);
        $this->length = count($this->rows);

        return $this;
    }
    function skip($s)
    {
        $skp = intval($s);
        if (!is_numeric($skp))
            return $this;
        $this->rows = array_splice($this->rows, 0, $skp);
        $this->length = count($this->rows);

        return $this;
    }
    function count()
    {
        return count($this->rows);
    }
    function getArray()
    {
        return $this->rows;
    }
    function getJSON()
    {
        return json_encode($this->rows);
    }
}
