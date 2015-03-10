<?php
namespace rollingWolf\QueryablePHP;

class Helper
{
    public static function pathValidate()
    {
        $path = func_get_arg(0);
        if ($path && (is_dir($path) || mkdir($path, 0755, true))) {
            return $path;
        }

        return (func_num_args() === 2) ? func_get_arg(1) : false;
    }
    public static function jsonValidate($json)
    {
        if (is_string($json)) {
            $validJson = @json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (func_num_args() === 2 && func_get_arg(1) === true) {
                    return $validJson;
                }

                return $json;
            }
        }

        return false;
    }
    public static function jsonDecode($json)
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
            $json = preg_replace('/([{,]+)(\s*)([^"]+?)\s*:/', '$1"$3":', $json);
            if (false !== $json = self::jsonValidate($json, true)) {
                return $json;
            } else {
                throw new \rollingWolf\QueryablePHP\Exception\QueryablePHPException('Invalid JSON string');
            }
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
        ksort($object);
        reset($object);

        return key($object);
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
    public static function addToFront($object, $_key, $_value)
    {
        if (!is_array($object)) {
            return $object;
        }

        return array_merge(array($_key => $_value), $object);
    }
}
