<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_Arr extends Kohana_Arr
{

    /**
     * check is 2D array
     *
     * @param   array    array
     * @return  boolean
     */
    public static function is_2d($array)
    {
        if (!is_array($array)) {
            return FALSE;
        }

        foreach ($array as $val) {
            if (!is_array($val)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * Rotates a 2D array clockwise.
     * Example, turns a 2x3 array into a 3x2 array.
     *
     * @param   array    array to rotate
     * @param   boolean  keep the keys in the final rotated array. the sub arrays of the source array need to have the same key values.
     *                   if your subkeys might not match, you need to pass FALSE here!
     * @return  array
     */
    public static function rotate($source_array, $keep_keys = TRUE)
    {
        $new_array = array();

        if (!self::is_2d($source_array)) {
            return $source_array;
        };

        foreach ($source_array as $key => $value) {
            $value = ($keep_keys === TRUE) ? $value : array_values($value);
            foreach ($value as $k => $v) {
                $new_array[$k][$key] = $v;
            }
        }

        return $new_array;
    }

    /**
     * Get a value from the array, and remove it.
     *
     * @param array $array
     * @param string $key
     * @return mixed
     */
    public static function pull(array &$array, $key = null)
    {
        $value = Arr::get($array, $key);
        Arr::delete($array, $key);
        return $value;
    }

    /**
     * Unsets dot-notated key from an array
     *
     * @param   array   $array    The search array
     * @param   mixed   $key      The dot-notated key or array of keys
     * @return  mixed
     */
    public static function delete(&$array, $key)
    {
        if (is_null($key)) {
            return false;
        }

        if (is_array($key)) {
            $return = array();
            foreach ($key as $k) {
                $return[$k] = static::delete($array, $k);
            }
            return $return;
        }

        $key_parts = explode('.', $key);

        if (!is_array($array) or ! array_key_exists($key_parts[0], $array)) {
            return false;
        }

        $this_key = array_shift($key_parts);

        if (!empty($key_parts)) {
            $key = implode('.', $key_parts);
            return static::delete($array[$this_key], $key);
        } else {
            unset($array[$this_key]);
        }

        return true;
    }

    /**
     * The in_arrayi function is a case-insensitive version of in_array.
     *
     * @param string  $needle
     * @param array  $haystack
     * @param boolean $case_insensitive
     * @return boolean
     */
    public static function in_array($needle, $haystack, $case_insensitive)
    {
        return in_array(strtolower($needle), array_map('strtolower', $haystack));
    }

    /**
     * The in_arrayi function is a case-insensitive version of in_array.
     *
     * @param string  $needle
     * @param array  $haystack
     * @param boolean $case_insensitive
     * @return boolean
     */
    public static function column($input = null, $columnKey = null, $indexKey = null)
    {
        if (function_exists('array_column')) {
            return array_column($input, $columnKey, $indexKey);
        }
        // Using func_get_args() in order to check for proper number of
        // parameters and trigger errors exactly as the built-in array_column()
        // does in PHP 5.5.
        $argc = func_num_args();
        $params = func_get_args();

        if ($argc < 2) {
            trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
            return null;
        }

        if (!is_array($params[0])) {
            trigger_error('array_column() expects parameter 1 to be array, ' . gettype($params[0]) . ' given', E_USER_WARNING);
            return null;
        }

        if (!is_int($params[1]) && !is_float($params[1]) && !is_string($params[1]) && $params[1] !== null && !(is_object($params[1]) && method_exists($params[1], '__toString'))
        ) {
            trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
            return false;
        }

        if (isset($params[2]) && !is_int($params[2]) && !is_float($params[2]) && !is_string($params[2]) && !(is_object($params[2]) && method_exists($params[2], '__toString'))
        ) {
            trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
            return false;
        }

        $paramsInput = $params[0];
        $paramsColumnKey = ($params[1] !== null) ? (string) $params[1] : null;

        $paramsIndexKey = null;
        if (isset($params[2])) {
            if (is_float($params[2]) || is_int($params[2])) {
                $paramsIndexKey = (int) $params[2];
            } else {
                $paramsIndexKey = (string) $params[2];
            }
        }

        $resultArray = array();

        foreach ($paramsInput as $row) {

            $key = $value = null;
            $keySet = $valueSet = false;

            if ($paramsIndexKey !== null && array_key_exists($paramsIndexKey, $row)) {
                $keySet = true;
                $key = (string) $row[$paramsIndexKey];
            }

            if ($paramsColumnKey === null) {
                $valueSet = true;
                $value = $row;
            } elseif (is_array($row) && array_key_exists($paramsColumnKey, $row)) {
                $valueSet = true;
                $value = $row[$paramsColumnKey];
            }

            if ($valueSet) {
                if ($keySet) {
                    $resultArray[$key] = $value;
                } else {
                    $resultArray[] = $value;
                }
            }
        }

        return $resultArray;
    }

}
