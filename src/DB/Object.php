<?php
namespace rollingWolf\QueryablePHP\DB;

use rollingWolf\QueryablePHP\Exception\QueryablePHPException;
use rollingWolf\QueryablePHP\Helper;
use rollingWolf\QueryablePHP\DB\Result;

class Object
{
    public $master = [];
    public $platform;
    public $dbPath;
    public $dbDir;
    public $dbName;
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

    function __construct($config)
    {
        $this->dbPath = realpath('.');
        $this->dbDir = realpath((isset($config['dbDir'])) ? $config['dbDir'] : '.');
        $this->dbName = (isset($config['dbName'])) ? $config['dbName'] : 'database.db';
        $this->dbFile = $this->dbDir.DIRECTORY_SEPARATOR.$this->dbName;
        $this->useGzip = (isset($config['useGzip'])) ? true : false;

        if (isset($config['data'])) {
            if (is_array($config['data'])) {
                $this->master = $config['data'];
            } elseif (is_string($config['data'])) {
                $this->master = Helper::jsonDecode($config['data'], true);
            } else {
                throw new QueryablePHPException('Could not determine input data type');
            }
        }
        if (Helper::right($this->dbName, 3) === '.gz') {
            $this->useGzip = true;
        }
        if (is_file($this->dbFile)) {
            try {
                $this->master = Helper::jsonDecode($this->_load(), true);
                $this->finishDBSetup();

                return;
            } catch (Exception $e) {
                throw new QueryablePHPException('Couldnt load '.$this->dbFile);
            }
        }
    }
    function finishDBSetup()
    {
        if (count($this->master) > 0) {
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
                foreach ($this->master as $key => $row) {
                    if (!array_key_exists('_id', $row)) {
                        $this->master[$key] = Helper::addToFront($this->master[$key], '_id', (++$highest));
                    }
                }
            }

            $this->_id = $highest;
        }
    }
    function save()
    {
        try {
            $this->_save();
        } catch (Exception $e) {
            throw new QueryablePHPException('Couldnt save to '.$this->dbFile);
        }
    }
    function insert($arg, $callback = false)
    {
        $numRows = 0;
        $arg = Helper::jsonDecode($arg, true);

        if (is_array($arg)) {
            foreach (func_get_args() as $row) {
                $numRows += $this->insertOne($row);
            }
        } else {
            throw new QueryablePHPException('Insert: accepts object or array of objects');
        }

        return $this->__return($numRows, $callback);
    }
    function insertOne($object)
    {
        $object = Helper::jsonDecode($object, true);

        if (!is_array($object)) {
            throw new QueryablePHPException('insert: row element must be object');
        }
        if (!isset($object['_id'])) {
            $object = Helper::addToFront($object, '_id', ++$this->_id);
        }
        $this->master[] = $object;

        return 1;
    }
    function update($query, $_update, $options = false, $callback = false)
    {
        $query = Helper::jsonDecode($query, true);
        $_update = Helper::jsonDecode($_update, true);
        $options = Helper::jsonDecode($options, true);

        if (func_num_args() < 2) {
            throw new QueryablePHPException('Usage: update(query, update, [options], [callback]');
        }
        if (!is_array($query) || !is_array($_update)) {
            throw new QueryablePHPException('Usage: update(query, update, [options], [callback]');
        }
        if (func_num_args() === 3 && !is_array($options)) {
            throw new QueryablePHPException("Usage: update(query, update, [options], [callback])");
        }
        if (!isset($_update['$set'])) {
            throw new QueryablePHPException("Usage: update(query, update, [options], [callback])");
        }


        $set = $_update['$set'];

        $res = $this->_doQuery($query);

        $doMulti = false;
        $doUpsert = false;

        if ($options) {
            $doMulti = (isset($options['multi'])) ? $options['multi'] : false;
            $doUpsert = (isset($options['upsert'])) ? $options['upsert'] : false;
        }

        if (count($res) === 0 && $doUpsert) {
            $this->insert($set);
            return $this->__return(1, $callback);
        }

        $rowsAltered = 0;

        foreach ($res as $row) {
            $didChange = false;
            foreach ($set as $key => $value) {
                if (!isset($row[$key]) || $row[$key] !== $value) {
                    $row[$key] = $value;
                    $didChange = true;
                }
            }
            if ($didChange) {
                $rowsAltered++;
                foreach ($this->master as $key => $value) {
                    if ($value['_id'] == $row['_id']) {
                        $this->master[$key] = $row;
                        break;
                    }
                }
            }
            if (!$doMulti) {
                break;
            }
        }

        return $this->__return($rowsAltered, $callback);
    }
    function find($match = false, $callback = false)
    {
        if (!$match) {
            $match = [];
        }
        $match = Helper::jsonDecode($match, true);
        if (!is_array($match)) {
            throw new QueryablePHPException('Find: usage: find([match], [callback])');
        }
        $res = $this->_doQuery($match);
        $dbRes = new Result($res);

        return $this->__return($dbRes, $callback);
    }
    function findOne($match = false, $callback = false)
    {
        if (!$match) {
            $match = [];
        }
        $match = Helper::jsonDecode($match, true);
        if (func_num_args() > 2 || !is_array($match)) {
            throw new QueryablePHPException('Find: usage: find([match], [callback])');
        }
        $res = $this->_doQuery($match);

        if (count($res)) {
            $dbRes = new Result($res[0]);

            return $this->__return($dbRes, $callback);
        } else {
            $dbRes = new Result($res);

            return $this->__return($dbRes, $callback);
        }
    }
    function distinct($str, $clause = false, $callback = false)
    {
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
            if (isset($res[$i][$str])) {
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
    function remove($constraints = false, $callback = false)
    {
        if (!$constraints) {
            $constraints = [];
        }
        $constraints = Helper::jsonDecode($constraints, true);
        if (!is_array($constraints)) {
            throw new QueryablePHPException('usage: remove([constraints], [callback])');
        }

        $rows = $this->_doQuery($constraints);
        if (count($rows) === 0) {
            return $this->__return(0, $callback);
        }

        $rmids = [];
        for ($i = 0; $i < count($rows); $i++) {
            if (!isset($rows[$i]['_id'])) {
                continue;
            }
            $rmids[] = $rows[$i]['_id'];
        }

        if (count($rmids) === 0) {
            return $this->__return(0, $callback);
        }
        $rowsAltered = 0;
        $rowsAltered = $this->_filter($rmids);

        if ($rowsAltered > 0) {
            $this->master = $this->newMaster;
        }

        return $this->__return($rowsAltered, $callback);
    }
    function getJSON()
    {
        return json_encode($this->master);
    }
    function now()
    {
        return date('Y-m-d H:i:s.000\Z');
    }
    function todate($isostring)
    {
        return date($isostring);
    }
    function count()
    {
        return count($this->master);
    }
    function renormalize()
    {
        for ($i = 0; $i < count($this->master); $i++) {
            Helper::sortObjectByKeys($this->master[$i]);
        }

        ksort($this->master);

        for ($i = 0; $i < count($this->master); $i++) {
            $this->master[$i]['_id'] = 1 + $i;
        }
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
    private function _matchingRowsNormal($test, $rows)
    {
        $res = [];
        $test = Helper::jsonDecode($test, true);

        foreach ($rows as $i => $row) {
            foreach ($row as $key => $value) {
                if (isset($breakOut[$i]) && $breakOut[$i] === true) {
                    break;
                }
                if ($key == $test['key']) {
                    if (preg_match(self::REGEXP_STRING, $test['value'])) {
                        $sval = $row[$key];
                        if (preg_match($test['value'], $sval)) {
                            $res[] = $row;
                            $breakOut[$i] = true;
                            continue;
                        }
                    } else {
                        if ($row[$key] === $test['value']) {
                            $res[] = $row;
                            $breakOut[$i] = true;
                            continue;
                        }
                    }
                }
            }
        }

        return $res;
    }
    private function _matchingRowsConditional($test, $rows)
    {
        $res = [];
        $test = Helper::jsonDecode($test, true);
        $cond = Helper::firstKey($test['value']);

        foreach ($rows as $i => $row) {
            if (!array_key_exists($test['key'], $row)) {
                continue;
            }
            if ($cond === '$exists') {
                if ($test['value'][$cond]) {
                    if ($row[$test['key']]) {
                        $res[] = $row;
                        continue;
                    }
                } else {
                    if (!$row[$test['key']]) {
                        $res[] = $row;
                        continue;
                    }
                }
                continue;
            }

            if ($cond === '$ne') {
                if (!$row[$test['key']]) {
                    $res[] = $row;
                    continue;
                } elseif ($row[$test['key']] !== $test['value']['$ne']) {
                    $res[] = $row;
                    continue;
                }
            }
            foreach ($row as $key => $value) {
                if (isset($breakOut[$i]) && $breakOut[$i] === true) {
                    break;
                }
                if (array_key_exists($key, $row) && $key == $test['key']) {
                    switch ($cond) {
                        case '$lt':
                            if ($value < $test['value'][$cond]) {
                                $res[] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$lte':
                            if ($value <= $test['value'][$cond]) {
                                $res[] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$gt':
                            if ($value > $test['value'][$cond]) {
                                $res[] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$gte':
                            if ($value >= $test['value'][$cond]) {
                                $res[] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                            break;
                        case '$ne':
                            if ($value !== $test['value'][$cond]) {
                                $res[] = $row;
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

        foreach ($rows as $i => $row) {
            for ($j = 0; $j < count($array); $j++) {
                if (isset($breakOut[$i]) && $breakOut[$i] === true) {
                    break;
                }
                $eltkey = Helper::firstKey($array[$j]);
                $eltval = $array[$j][$eltkey];
                $test = [];
                $test['key'] = $eltkey;
                $test['value'] = $eltval;

                $clausetype = $this->_detectClauseType($eltkey, $eltval);
                switch ($clausetype) {
                    case self::CLAUSE_NORMAL:
                        if (preg_match(self::REGEXP_STRING, $test['value'])) {
                            if (isset($row[$test['key']]) && preg_match($test['value'], $row[$test['key']])) {
                                $res[] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                        } else {
                            if ($row[$test['key']] === $test['value']) {
                                $res[] = $row;
                                $breakOut[$i] = true;
                                continue;
                            }
                        }
                        break;
                    case self::CLAUSE_CONDITIONAL:
                        switch (Helper::firstKey($test['value'])) {
                            case '$gt':
                                if ($row[$test['key']] > $test['value']['$gt']) {
                                    $res[] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$gte':
                                if ($row[$test['key']] >= $test['value']['$gt']) {
                                    $res[] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$lt':
                                if ($row[$test['key']] < $test['value']['$gt']) {
                                    $res[] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$lte':
                                if ($row[$test['key']] <= $test['value']['$gt']) {
                                    $res[] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                }
                                break;
                            case '$exists':
                                if (isset($row[$test['key']]) && $test['value']['$exists']) {
                                    $res[] = $row;
                                    $breakOut[$i] = true;
                                    continue;
                                } elseif (!isset($row[$test['key']]) && !$test['value']['$exists']) {
                                    $res[] = $row;
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
    private function _doQuery($clauses = false)
    {
        $result = $this->master;
        if (!$clauses || (is_array($clauses) && Helper::firstKey($clauses) === null)) {
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
    private function _sortMaster()
    {
        Helper::sortArrayOfObjectsByKeys($this->master);
    }
    private function __return($arg, $callback = false)
    {
        if (is_callable($callback)) {
            return $callback($arg);
        }

        return $arg;
    }
    private function _filter($rmids)
    {
        $this->newMaster = [];
        $rowsAltered = 0;

        foreach ($this->master as $idx => $row) {
            if (!in_array($row['_id'], $rmids)) {
                $this->newMaster[$idx] = $row;
                $rowsAltered++;
            }
        }

        return $rowsAltered;
    }
    private function _save()
    {
        if (!is_dir(dirname($this->dbDir))) {
            mkdir(dirname($this->dbDir), 0755, true);
        }
        if ($this->useGzip) {
            file_put_contents($this->dbFile, gzdeflate(json_encode($this->master)));
        } else {
            file_put_contents($this->dbFile, json_encode($this->master));
        }
    }
    private function _load()
    {
        if ($this->useGzip) {
            return gzinflate(file_get_contents($this->dbFile));
        }

        return file_get_contents($this->dbFile);
    }
}
