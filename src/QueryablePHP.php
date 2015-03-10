<?php
namespace rollingWolf\QueryablePHP;

use rollingWolf\QueryablePHP\DB\Object as QueryablePHPDB;

class QueryablePHP
{
    public static function open($config)
    {
        return new QueryablePHPDB($config);
    }
}
