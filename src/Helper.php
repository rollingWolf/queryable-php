<?php
namespace rollingWolf\QueryablePHP;

class Helper
{
    public static function jsonDecode($json, $assoc = false)
    {
        if (is_bool($json)) {
            return $json;
        }
        if (is_array($json)) {
            return $json;
        }
        if (is_object($json)) {
            $json = json_encode($json);
        }
        if (is_string($json)) {
            $json = str_replace(array("\n","\r"), '', $json);
            $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/','$1"$3":', $json);
            return json_decode($json, $assoc);
        }
        throw new \rollingWolf\QueryablePHP\Exception\QueryablePHPException('Unknown JSON source');
    }
    public static function right($str, $length)
    {
        return substr($str, -$length);
    }
    public static function clipAllLeading($string, $clip)
    {
        return preg_replace('~^'.preg_quote($clip).'~', '', $string);
    }
    public static function firstKey($object)
    {
        $object = self::jsonDecode($object, true);

        if (!is_array($object)) {
            return null;
        }
        foreach ($object as $key => $val) {
            if (isset($object[$key])) {
                return $key;
            }
        }
        return null;
    }
    public static function getKeys($object)
    {
        $keys = [];
        foreach ($object as $idx => $value) {
            $_val = (is_object($value)) ? self::getKeys($value) : $value;
            if (is_array($_val) && count($_val) === 1) {
                $_val = $_val[0];
            }
            $keys[] = array('key' => $idx, 'value' => $_val);
        }

        return $keys;
    }
    public static function sortObjectByKeys(& $object)
    {
        ksort($object);
    }
    public static function addToFront($object, $_key, $_value)
    {
        if (!is_array($object)) {
            return $object;
        }

        $newObj = array($_key => $_value);

        foreach ($object as $key => $value) {
            $newObj[$key] = $value;
        }

        return $newObj;
    }
    public static function sortArrayOfObjectsByKeys($array)
    {
        if (!is_array($array)) {
            return $array;
        }
        if (count($array) === 0) {
            return $array;
        }

        foreach ($array as $key => $value) {
            self::sortObjectByKeys($array[$key]);
        }

        return $array;
    }
}
