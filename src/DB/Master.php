<?php
namespace rollingWolf\QueryablePHP\DB;

use rollingWolf\QueryablePHP\Exception\QueryablePHPException;
use rollingWolf\QueryablePHP\Helper;

class Master implements \Iterator
{
    public $rows = [];
    private $ID = 0;

    public function load($data)
    {
        $rows = Helper::jsonDecode($data);
        foreach ($rows as $row) {
            if (array_key_exists('_id', $row)) {
                if ($row['_id'] > $this->ID) {
                    $this->setID($row['_id']);
                }
                $this->rows[$row['_id']] = $row;
            } else {
                $this->rows[++$this->ID] = $row;
            }
        }
    }
    public function getID()
    {
        return $this->ID;
    }
    public function setID($id)
    {
        $this->ID = $id;
    }
    public function assignID()
    {
        return ++$this->ID;
    }
    public function getRows()
    {
        return $this->rows;
    }
    public function row($index)
    {
        return $this->rows[$index];
    }
    public function setRow($index, $row)
    {
        $this->rows[$index] = $row;
    }
    public function addRow($row)
    {
        if (array_key_exists('_id', $row)) {
            $this->rows[$row['_id']] = $row;
        } else {
            $this->rows[++$this->ID] = $row;
        }
    }
    public function addData($index, $data)
    {
        $this->rows[$index] = array_merge($this->rows[$index], $data);
    }
    public function count()
    {
        return count($this->rows);
    }
    public function rowHas($key)
    {
        return array_key_exists($key, current($this));
    }
    public function sort()
    {
        ksort($this->rows);
    }
    public function filter($remove)
    {
        if (is_integer($remove) && array_key_exists($remove, $this->rows)) {
            unset($this->rows[$remove]);

            return 1;
        }
        if (is_array($remove)) {
            foreach ($remove as $removeID) {
                unset($this->rows[$removeID]);
            }

            return count($remove);
        }

        return 0;
    }
    public function rewind()
    {
        reset($this->rows);
    }

    public function current()
    {
        $row = current($this->rows);

        return $row;
    }

    public function key()
    {
        $key = key($this->rows);

        return $key;
    }

    public function next()
    {
        $row = next($this->rows);

        return $row;
    }

    public function valid()
    {
        $key = key($this->rows);
        $var = ($key !== null && $key !== false);

        return $var;
    }
    public function getJSON()
    {
        return json_encode($this->rows);
    }
    public function save($dbFile, $useGzip)
    {
        if (!is_dir(dirname($dbFile))) {
            mkdir(dirname($dbFile), 0755, true);
        }
        if ($useGzip) {
            file_put_contents($dbFile, gzdeflate($this->getJSON()));
        } else {
            file_put_contents($dbFile, $this->getJSON());
        }
    }

}
