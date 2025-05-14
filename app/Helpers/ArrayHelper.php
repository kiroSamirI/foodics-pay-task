<?php

namespace App\Helpers;

class ArrayHelper
{
    /**
     * Flattens a nested array into a single-level array
     *
     * @param array $array The array to flatten
     * @return array The flattened array
     */
    public static function flatten(array $array): array
    {
        $result = [];
        
        foreach ($array as $key => $item) {
            if (is_array($item)) {
                $result = array_merge($result, self::flatten($item));
            } else {
                $result[$key] = $item;
            }
        }
        return $result;
    }
} 