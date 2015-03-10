<?php
namespace rollingWolf\QueryablePHP;

use rollingWolf\QueryablePHP\DB\Object as QueryablePHPDB;

class QueryablePHP
{
    public static function open($config)
    {
        $configValidation = array(
            'dbDir' => array(
                'filter' => FILTER_CALLBACK,
                'flags' => FILTER_NULL_ON_FAILURE,
                'options' => function ($path) {
                    return Helper::pathValidate($path, realpath('.'));
                }
                ),
            'dbName' => array(
                'filter' => FILTER_SANITIZE_STRING,
                'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW,
                'default' => 'database.db'
                ),
            'useGzip' => FILTER_VALIDATE_BOOLEAN,
            'data' => array(
                'filter' => FILTER_CALLBACK,
                'options' => function ($json) {
                    return Helper::jsonValidate($json, false);
                }
                ),
            );
        $newConfig = filter_var_array($config, $configValidation);

        return new QueryablePHPDB($newConfig);
    }
}
