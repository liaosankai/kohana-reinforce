<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_Arr extends Kohana_Arr {

    /**
     * 插入元素到指定 key 之後
     *
     * @param type $arr
     * @param type $insert_key
     * @param type $key
     * @param type $value
     * @return type
     */
    public static function insert_after(&$arr, $insert_key, $key, $value) {
        $keys = array_keys($arr);
        $vals = array_values($arr);

        $insert_after = array_search($insert_key, $keys) + 1;

        $keys2 = array_splice($keys, $insert_after);
        $vals2 = array_splice($vals, $insert_after);

        $keys[] = $key;
        $vals[] = $value;

        return array_merge(array_combine($keys, $vals), array_combine($keys2, $vals2));
    }

    /**
     * check is 2D array
     *
     * @param array array
     * @return boolean
     */
    public static function is_2d($array) {
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
     * 從陣列中挑出符合列舉陣列中的元素
     *
     * @example
     *
     *   $arr = array('a', 'b', 'b', 'c', 'd', 'e', 'e', 'f');
     *   Arr::match_enum($arr, array('a', 'c', 'd')); // 結果 array('a','c','d');
     *   Arr::match_enum($arr, array('a', 'b', 'c')); // 結果 array('a','b','b','c');
     *   Arr::match_enum($arr, array('a', 'b', 'e')); // 結果 array('a','b','b','e','e');
     *   Arr::match_enum($arr, array('a', 'f', 'g')); // 結果 array('a','f');
     *
     * @param array $enum_array
     * @param array $array
     * @return array
     */
    public static function match_enum($array, $enum_array) {
        return array_diff($array, array_diff($array, $enum_array));
    }

    /**
     * 扁平化陣列
     *
     * @example
     *
     *   $arr = array(
     *     'Sam' => array(
     *         'age' => 31,
     *         'height' => 168,
     *     )
     *   );
     *
     *   Arr::flatten($arr);
     *   結果：
     *   Array
     *   (
     *       [Sam.age] => 31
     *       [Sam.height] => 168
     *   )
     *
     *   Arr::flatten($arr, '#');
     *   結果：
     *   (
     *       [#Sam.age] => 31
     *       [#Sam.height] => 168
     *   )
     *
     *   Arr::flatten($arr, '#', '_');
     *   結果：
     *   Array
     *   (
     *       [#Sam_age] => 31
     *       [#Sam_height] => 168
     *   )
     *
     *
     * @param array $array
     * @param string $prefix
     * @param string $glue
     * @return array
     */
    public static function flatten($array, $prefix = '', $glue = '.') {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, Arr::flatten($value, $prefix . $key . $glue));
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    /**
     * 反解被扁平化的陣列
     *
     * @param array $array  flattened array
     * @param string $glue   glue used in flattening
     * @return array
     */
    public static function reverse_flatten($array, $glue = '.') {
        $return = array();

        foreach ($array as $key => $value) {
            if (stripos($key, $glue) !== false) {
                $keys = explode($glue, $key);
                $temp = & $return;
                while (count($keys) > 1) {
                    $key = array_shift($keys);
                    $key = is_numeric($key) ? (int) $key : $key;
                    if (!isset($temp[$key]) or ! is_array($temp[$key])) {
                        $temp[$key] = array();
                    }
                    $temp = & $temp[$key];
                }

                $key = array_shift($keys);
                $key = is_numeric($key) ? (int) $key : $key;
                $temp[$key] = $value;
            } else {
                $key = is_numeric($key) ? (int) $key : $key;
                $return[$key] = $value;
            }
        }

        return $return;
    }

    /**
     * 翻轉一個二維陣列
     *
     * @example
     *
     *   $arr = array(
     *     'id' => array(1,2,3),
     *     'name' => array('Joe','Bill','Mary'),
     *     'age' => array(18,33,24),
     *   );
     *
     *   $arr2 = Arr::rotate($arr);
     *
     *   結果：
     *
     *   array(
     *     array('id'=> 1, 'name'=> 'Joe', 'age' => 18),
     *     array('id'=> 2, 'name'=> 'Bill', 'age' => 33),
     *     array('id'=> 3, 'name'=> 'Mary', 'age' => 24),
     *   );
     *
     *
     * @param array array to rotate
     * @param boolean keep the keys in the final rotated array. the sub arrays of the source array need to have the same key values.
     *                if your subkeys might not match, you need to pass FALSE here!
     * @return array
     */
    public static function rotate($source_array, $keep_keys = TRUE) {
        $new_array = array();

        foreach ($source_array as $key => $value) {
            $value = ($keep_keys === TRUE) ? $value : array_values($value);
            foreach ($value as $k => $v) {
                $new_array[$k][$key] = $v;
            }
        }

        return $new_array;
    }

    /**
     * 將指定索引的元素從陣列中拉出來(將會將陣列中移除)
     *
     * array_pop() 是拉出最後一個，並從陣列中移除
     * array_shift() 是拉出第一個，並從陣列中移除
     *
     * 此函式 Arr::pull() 是拉出指定索引那個，並從陣列中移除
     *
     * @example
     *
     *   $stack = array("orange", "banana", "apple", "raspberry");
     *   $fruit = Arr::pull($stack, 1);
     *   // $stack will be array("orange", "apple", "raspberry");
     *
     * @param array $array
     * @param string $key 可以是數字或字串索引
     * @return mixed
     */
    public static function pull(array &$array, $key = null) {
        $value = Arr::get($array, $key);
        Arr::delete($array, $key);
        return $value;
    }

    /**
     * 使用 `.` 符號索引來刪除深層陣列元素
     *
     * @example
     *
     *   $arr = array(
     *     'Sam' => array(
     *       'age' => 31,
     *       'height' => 168,
     *     ),
     *     'Tom' => array(
     *       'age' => 25,
     *       'height' => 174,
     *     ),
     *   );
     *
     *   Arr::delete($arr, 'Sam.height');
     *
     *   等同於
     *
     *   unset($arr['Sam']['height']);
     *
     * @param   array $array The search array
     * @param   mixed $key The dot-notated key or array of keys
     * @return  mixed
     */
    public static function delete(&$array, $key) {
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
     * 強化原生的 in_array()，可以額外給予是否略忽大小寫的參數
     *
     * @param string $needle
     * @param array $haystack
     * @param boolean $case_insensitive
     * @return boolean
     */
    public static function in_array($needle, $haystack, $strict = FALSE, $case_insensitive = TRUE) {
        $needle = $case_insensitive ? strtolower($needle) : $needle;
        $haystack = $case_insensitive ? array_map('strtolower', $haystack) : $haystack;
        return in_array($needle, $haystack, $strict);
    }

    /**
     * 一個模擬 php 5.5 才有的 array_column 函式
     *
     * Return the values from a single column in the input array
     *
     * @see http://php.net/manual/en/function.array-column.php
     * @param array A multi-dimensional array (record set) from which to pull a column of values.
     * @param mixed The column of values to return. This value may be the integer key of the column
     *              you wish to retrieve, or it may be the string key name for an associative array.
     *              It may also be NULL to return complete arrays (useful together with index_key to reindex the array).
     * @param mixed The column to use as the index/keys for the returned array.
     *              This value may be the integer key of the column, or it may be the string key name.
     * @return boolean
     */
    public static function column($array = null, $column_key = null, $index_key = null) {
        if (function_exists('array_column')) {
            return array_column($array, $column_key, $index_key);
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
