<?php
namespace rollingWolf\QueryablePHP\DB;

use rollingWolf\QuyeryPHP\Exception\QueryablePHP;
use rollingWolf\QueryablePHP\Helper;

class Result
{
    public $length = 0;
    public $rows = [];

    public function __construct()
    {
        foreach (func_get_args() as $arg) {
            if (method_exists($arg, 'getRows')) {
                $this->push($arg->getRows());
            } else {
                $this->push($arg);
            }
        }
    }
    private function cmp($aitem, $bitem)
    {
        if (!array_key_exists($this->_sortKey, $aitem)) {
            return -$this->_sortVal;
        } elseif (!array_key_exists($this->_sortKey, $bitem)) {
            return -$this->_sortVal;
        }
        if (is_string($aitem[$this->_sortKey]) && is_string($bitem[$this->_sortKey])) {
            return strcoll($aitem[$this->_sortKey], $bitem[$this->_sortKey]) * $this->_sortVal;
        } else {
            return $aitem[$this->_sortKey] > $bitem[$this->_sortKey] ? $this->_sortVal : -$this->_sortVal;
        }
    }
    public function push($object)
    {
        $this->rows = Helper::jsonDecode(json_encode($object), true);
        $this->length = $this->count();
        return $this;
    }
    public function sort($object)
    {
        $object = Helper::jsonDecode($object, true);
        $this->_sortKey = Helper::firstKey($object);
        $this->_sortVal = $object[$this->_sortKey];

        uasort($this->rows, array('\rollingWolf\QueryablePHP\DB\Result', 'cmp'));

        return $this;
    }
    public function limit($limit)
    {
        $lim = intval($limit);
        if (!is_numeric($lim)) {
            return $this;
        }
        //$this->rows = array_splice($this->rows, $lim, count($this->rows) - $lim);
        array_splice($this->rows, $lim, count($this->rows) - $lim);
        foreach ($this->rows as $row) {
            $newrows[$row['_id']] = $row;
        }
        $this->rows = $newrows;
        $this->length = $this->count();

        return $this;
    }
    public function skip($skipAmt)
    {
        $skip = intval($skipAmt);
        if (!is_numeric($skip)) {
            return $this;
        }
        //$this->rows = array_splice($this->rows, 0, $skip);
        array_splice($this->rows, 0, $skip);
        foreach ($this->rows as $row) {
            $newrows[$row['_id']] = $row;
        }
        $this->rows = $newrows;
        $this->length = $this->count();

        return $this;
    }
    public function count()
    {
        if (is_array($this->rows)) {
            return count($this->rows);
        }

        return false;
    }
    public function getArray()
    {
        return $this->rows;
    }
    public function getJSON()
    {
        return json_encode($this->rows);
    }
}
