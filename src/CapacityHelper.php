<?php

namespace App;

class CapacityHelper
{
    public static function getMB($capacity, $unit)
    {
        if (
            is_null($capacity) ||
            is_null($unit)
        ) {
            return;
        }

        switch ($unit) {
            case 'MB':
                return $capacity * 1;
            case 'GB':
                return $capacity * 1000;
            case 'TB':
                return $capacity * 1000000;
            default:
                return;
        }
    }
}
