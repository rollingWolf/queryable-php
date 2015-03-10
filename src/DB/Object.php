<?php
namespace rollingWolf\QueryablePHP\DB;

use rollingWolf\QueryablePHP\Exception\QueryablePHPException;
use rollingWolf\QueryablePHP\Helper;
use rollingWolf\QueryablePHP\DB\Result;
use rollingWolf\QueryablePHP\DB\Master;

class Object
{
    protected $master;

    public $platform;
    public $dbDir;
    public $dbName;
    public $dbFile;
    public $useGzip = false;
    private $_id = 0;

    const CLAUSE_UNKNOWN = 0;
    const CLAUSE_NORMAL = 1;
    const CLAUSE_SUBDOCUMENT_MATCH = 2;
    const CLAUSE_CONDITIONAL = 3;
    const CLAUSE_SUBDOCUMENT = 4;
    const CLAUSE_OR = 5;
    const CLAUSE_ARRAY = 6;
    const REGEXP_STRING = '~^(?P<del>.).+(?P=del)([imsxeUu]+)?$~';

    public function __construct($config)
    {
        $this->master = new Master;
        $this->dbDir = $config['dbDir'];
        $this->dbName = $config['dbName'];
        $this->dbFile = $this->dbDir.DIRECTORY_SEPARATOR.$this->dbName;
        $this->useGzip = $config['useGzip'];

        if ($config['data']) {
            if (is_array($config['data'])) {
                $this->master->load($config['data']);
            } elseif (is_string($config['data'])) {
                $this->master->load(Helper::jsonDecode($config['data']));
            } else {
                throw new QueryablePHPException('Could not determine input data type');
            }
        }
        if (Helper::right($this->dbName, 3) === '.gz') {
            $this->useGzip = true;
        }
        if (is_file($this->dbFile)) {
            try {
                $this->load();
                $this->_finishDBSetup();

                return;
            } catch (Exception $e) {
                throw new QueryablePHPException('Couldnt load '.$this->dbFile);
            }
        }
    }
    public function load()
    {
        $this->master->load(Helper::jsonDecode($this->_load()));
    }
    private function _finishDBSetup()
    {
        if ($this->master->count() > 0) {
            $highest = 0;
            $anyMissing = false;
            foreach ($this->master as $row) {
                if (!array_key_exists('_id', $row)) {
                    $anyMissing = true;
                } elseif ($row['_id'] > $highest) {
                    $highest = $row['_id'];
                }
            }
            if ($anyMissing) {
                $this->master->setID($highest);
                foreach ($this->master as $key => $row) {
                    if (!array_key_exists('_id', $row)) {
                        $this->master->setRow($key, Helper::addToFront($row, '_id', $this->master->assignID()));
                    }
                }
            }
        }
    }
    public function save()
    {
        try {
            $this->master->save($this->dbFile, $this->useGzip);
        } catch (Exception $e) {
            throw new QueryablePHPException('Couldnt save to '.$this->dbFile);
        }
    }
    public function insert()
    {
        $numRows = 0;
        if (func_num_args() > 0) {
            foreach (func_get_args() as $row) {
                $row = Helper::jsonDecode($row);
                if (key($row) === 0) {
                    foreach ($row as $r) {
                        $numRows += $this->insertOne($r);
                    }
                } else {
                    if (is_string($row) || is_array($row)) {
                        $numRows += $this->insertOne($row);
                    }
                }
            }
        } else {
            throw new QueryablePHPException('Insert: accepts object or array of objects');
        }

        return $this->__return($numRows, func_get_args());
    }
    public function insertOne($object)
    {
        $object = Helper::jsonDecode($object);

        if (!is_array($object)) {
            throw new QueryablePHPException('insert: row element must be object');
        }
        $row = $object;
        if (!array_key_exists('_id', $row)) {
            $row = Helper::addToFront($row, '_id', $this->master->assignID());
        }
        $this->master->addRow($row);

        return 1;
    }
    public function update()
    {
        $query = @Helper::jsonDecode(func_get_arg(0));
        $_update = @Helper::jsonDecode(func_get_arg(1));
        $options = @Helper::jsonDecode(func_get_arg(2));
        $callback = @func_get_arg(3);

        if (func_num_args() < 2) {
            throw new QueryablePHPException('Usage: update(query, update, [options], [callback]');
        }
        if (!is_array($query) || !is_array($_update)) {
            throw new QueryablePHPException('Usage: update(query, update, [options], [callback]');
        }
        if ($options !== false && !is_array($options)) {
            throw new QueryablePHPException("Usage: update(query, update, [options], [callback])");
        }
        if ($_update !== false && !array_key_exists('$set', $_update)) {
            throw new QueryablePHPException("Usage: update(query, update, [options], [callback])");
        }

        $set = $_update['$set'];

        $res = $this->_doQuery($query);

        $doMulti = false;
        $doUpsert = false;

        if ($options) {
            $doMulti = (array_key_exists('multi', $options)) ? $options['multi'] : false;
            $doUpsert = (array_key_exists('upsert', $options)) ? $options['upsert'] : false;
        }

        if (count($res) === 0 && $doUpsert) {
            $this->insert($set);

            return $this->__return(1, $callback);
        }

        $rowsAltered = 0;
        foreach ($res as $idx => $row) {
            $didChange = false;
            foreach ($set as $key => $value) {
                if (!array_key_exists($key, $row) || $row[$key] !== $value) {
                    $row[$key] = $value;
                    $didChange = true;
                }
            }
            if ($didChange) {
                $rowsAltered++;
                $this->master->setROw($idx, $row);
            }
            if (!$doMulti) {
                break;
            }
        }

        return $this->__return($rowsAltered, $callback);
    }
    public function find()
    {
        if (false === $match = @func_get_arg(0)) {
            $match = [];
        }
        $match = Helper::jsonDecode($match);
        if (!is_array($match)) {
            throw new QueryablePHPException('Find: usage: find([match], [callback])');
        }
        $res = $this->_doQuery($match);
        $dbRes = new Result($res);

        return $this->__return($dbRes, func_get_args());
    }
    public function findOne()
    {
        if (false === $match = @func_get_arg(0)) {
            $match = [];
        }
        $match = Helper::jsonDecode($match);
        if (func_num_args() > 2 || !is_array($match)) {
            throw new QueryablePHPException('Find: usage: find([match], [callback])');
        }
        $res = $this->_doQuery($match);

        if (count($res)) {
            $dbRes = new Result($res[0]);

            return $this->__return($dbRes, func_get_args());
        } else {
            $dbRes = new Result($res);

            return $this->__return($dbRes, func_get_args());
        }
    }
    public function distinct()
    {
        $str = @func_get_arg(0);
        $clasue = @func_get_arg(1);
        $callback = @func_get_arg(2);

        if (!is_string($str)) {
            throw new QueryablePHPException('usabe: distinct(key, [clause], [callback])');
        }
        if ($clause) {
            $res = $this->_doQuery($clause);
        } else {
            $res = $this->_doQuery();
        }

        $setWo = [];
        $maybe = [];
        for ($i = 0; $i < count($res); $i++) {
            if (array_key_exists($str, $res[$i])) {
                $maybe[] = $res[$i];
            }
        }

        $distinctSet = [];
        for ($i = 0; $i < count($maybe); $i++) {
            if (!in_array($mayby[$i][$str], $distinctSet)) {
                $distinctSet[] = $mayby[$i];
            }
        }

        $dbRes = new Result($distinctSet);

        return $this->__return($dbRes, $callback);
    }
    public function remove()
    {
        if (false === $constraints = @func_get_arg(0)) {
            $constraints = [];
        }
        $constraints = Helper::jsonDecode($constraints);
        if (!is_array($constraints)) {
            throw new QueryablePHPException('usage: remove([constraints], [callback])');
        }

        $rows = $this->_doQuery($constraints);
        if (count($rows) === 0) {
            return $this->__return(0, $callback);
        }

        $rmids = [];
        foreach ($rows as $row) {
            if (!array_key_exists('_id', $row)) {
                continue;
            }
            $rmids[] = $row['_id'];
        }

        if (count($rmids) === 0) {
            return $this->__return(0, func_get_args());
        }
        $rowsAltered = 0;
        $rowsAltered = $this->master->filter($rmids);

        return $this->__return($rowsAltered, func_get_args());
    }
    public function getJSON()
    {
        return $this->master->getJSON();
    }
    public function now()
    {
        return date('Y-m-d H:i:s.000\Z');
    }
    public function todate($isostring)
    {
        return date($isostring);
    }
    public function count()
    {
        return $this->master->count();
    }
    public function getID()
    {
        return $this->master->getID();
    }
    private function _detectClauseType($key, $value)
    {
        switch (gettype($value)) {
            case 'boolean':
            case 'number':
            case 'string':
                return (strstr($key, '.')) ? self::CLAUSE_SUBDOCUMENT_MATCH : self::CLAUSE_NORMAL;
                break;
            case 'array':
                $fk = Helper::firstKey($value);
                switch ($fk) {
                    case '$gt':
                    case '$gte':
                    case '$lt':
                    case '$lte':
                    case '$exists':
                    case '$ne':
                        return self::CLAUSE_CONDITIONAL;
                        break;
                    case '$or':
                        return self::CLAUSE_OR;
                        break;
                    default:
                        return self::CLAUSE_ARRAY;
                        //return self::CLAUSE_SUBDOCUMENT;
                        break;
                }
                break;
            case 'array':
                return ($key === '$or') ? self::CLAUSE_OR : self::CLAUSE_ARRAY;
                break;
            default:
                break;
        }

        return self::CLAUSE_UNKNOWN;
    }
    private function _matchingRowsNormal()
    {
        $res = [];
        $breakOut = [];
        $test = @Helper::jsonDecode(func_get_arg(0));
        if (!is_array($test) && !(array_key_exists('value', $test) && array_key_exists('key', $test))) {
            return $res;
        }
        if (false === $rows = @func_get_arg(1)) {
            $rows = [];
        }

        foreach ($rows as $i => $row) {
            foreach (array_keys($row) as $key) {
                if (isset($breakOut[$i]) && $breakOut[$i] === true) {
                    break;
                }
                if ($key == $test['key']) {
                    if (preg_match(self::REGEXP_STRING, $test['value'])) {
                        $sval = $row[$key];
                        if (preg_match($test['value'], $sval)) {
                            $res[$row['_id']] = $row;
                            $breakOut[$i] = true;
                            continue;
                        }
                    } else {
                        if ($row[$key] === $test['value']) {
                            $res[$row['_id']] = $row;
                            $breakOut[$i] = true;
                            continue;
                        }
                    }
                }
            }
        }

        return $res;
    }
    private function _matchingRowsConditional()
    {
        $res = [];
        $breakOut = [];
        $test = @Helper::jsonDecode(func_get_arg(0));
        if (!is_array($test) && !(array_key_exists('value', $test) && array_key_exists('key', $test))) {
            return $res;
        }
        $cond = Helper::firstKey($test['value']);
        if (false === $rows = @func_get_arg(1)) {
            $rows = [];
        }
        foreach ($rows as $i => $row) {
            if ($cond === '$exists') {
                if ($test['value'][$cond]) {
                    if (array_key_exists($test['key'], $row)) {
                        $res[$row['_id']] = $row;
                        continue;
                    }
                } else {
                    if (!array_key_exists($test['key'], $row)) {
                        $res[$row['_id']] = $row;
                        continue;
                    }
                }
                continue;
            }

            if ($cond === '$ne') {
                if (!array_key_exists($test['key'], $row)) {
                    $res[$row['_id']] = $row;
                    continue;
                } elseif ($row[$test['key']] !== $test['value']['$ne']) {
                    $res[$row['_id']] = $row;
                    continue;
                }
            }
            foreach ($row as $key => $value) {
                if (isset($breakOut[$i]) && $breakOut[$i] === true) {
                    break;
                }
                if ($key === $test['key']) {
                    switch ($cond) {
                        case '$lt':
                            if ($value < $test['value'][$cond]) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$lte':
                            if ($value <= $test['value'][$cond]) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$gt':
                            if ($value > $test['value'][$cond]) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$gte':
                            if ($value >= $test['value'][$cond]) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$ne':
                            if ($value !== $test['value'][$cond]) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        return $res;
    }
    private function _matchingRowsOr($array, $rows)
    {
        $res = [];
        $breakOut = [];

        foreach ($rows as $i => $row) {
            foreach ($array as $j => $av) {
                if (isset($breakOut[$i]) && $breakOut[$i] === true) {
                    break;
                }
                $eltkey = Helper::firstKey($av);
                $eltval = $av[$eltkey];
                $test = [];
                $test['key'] = $eltkey;
                $test['value'] = $eltval;

                $clausetype = $this->_detectClauseType($eltkey, $eltval);
                switch ($clausetype) {
                    case self::CLAUSE_NORMAL:
                        if (preg_match(self::REGEXP_STRING, $test['value'])) {
                            if (array_key_exists($test['key'], $row) && preg_match($test['value'], $row[$test['key']])) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                        } else {
                            if ($row[$test['key']] === $test['value']) {
                                $res[$row['_id']] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                        }
                        break;
                    case self::CLAUSE_CONDITIONAL:
                        switch (Helper::firstKey($test['value'])) {
                            case '$gt':
                                if ($row[$test['key']] > $test['value']['$gt']) {
                                    $res[$row['_id']] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$gte':
                                if ($row[$test['key']] >= $test['value']['$gt']) {
                                    $res[$row['_id']] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$lt':
                                if ($row[$test['key']] < $test['value']['$gt']) {
                                    $res[$row['_id']] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$lte':
                                if ($row[$test['key']] <= $test['value']['$gt']) {
                                    $res[$row['_id']] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$exists':
                                if (isset($row[$test['key']]) && $test['value']['$exists']) {
                                    $res[$row['_id']] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                } elseif (!isset($row[$test['key']]) && !$test['value']['$exists']) {
                                    $res[$row['_id']] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                        }
                        break;
                    default:
                        break;
                }
            }
        }

        return $res;
    }
    private function _doQuery()
    {
        $result = $this->master->getRows();
        $clauses = @Helper::jsonDecode(func_get_arg(0));

        if ((is_array($clauses) && Helper::firstKey($clauses) === null)) {
            return $result;
        }

        foreach ($clauses as $key => $clause) {
            $clausetype = $this->_detectClauseType($key, $clause);
            switch ($clausetype) {
                case self::CLAUSE_NORMAL:
                    $result = $this->_matchingRowsNormal('{"key": "'.$key.'", "value": "'.$clause.'"}', $result);
                    break;
                case self::CLAUSE_CONDITIONAL:
                    $result = $this->_matchingRowsConditional('{"key": "'.$key.'", "value": '.json_encode($clause).'}', $result);
                    break;
                case self::CLAUSE_OR:
                    $result = $this->_matchingRowsOr($clause, $result);
                    break;
                default:
                    break;
            }
        }

        return $result;
    }
    private function __return($arg, $callback)
    {
        if (is_callable($callback)) {
            if (is_callable($callback)) {
                return $callback($arg);
            }
            if (is_array($callback) && is_callable($callback = array_pop($callback))) {
                return $callback($arg);
            }
        }

        return $arg;
    }
    private function _load()
    {
        if ($this->useGzip) {
            return gzinflate(file_get_contents($this->dbFile));
        }

        return file_get_contents($this->dbFile);
    }
}
