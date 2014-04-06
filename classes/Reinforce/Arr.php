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

        if (!is_array($array) or !array_key_exists($key_parts[0], $array)) {
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

}
