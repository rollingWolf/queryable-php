<?php
namespace rollingWolf\QueryablePHP;

use rollingWolf\QueryablePHP\Exception\QueryablePHPException;

class EmptyClass
{
    function __construct($object = false) {
        if (!$object)
            return;
        if (is_object($object) || is_array($object)) {
            $setFrom = $object;
        } elseif (is_string($object)) {
            $setFrom = json_decode($object);
        } else {
            throw new QueryablePHPException('Couldnt set default values');
        }

        foreach ($setFrom as $key => $value) {
            if (is_object($value) || is_array($value)) {
                $this->$key = json_decode(json_encode($value), true);
                continue;
            }
            $this->$key = $value;
        }
    }
}
