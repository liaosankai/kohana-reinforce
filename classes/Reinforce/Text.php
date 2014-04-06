<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_Text extends Kohana_Text {

    /**
     * 高亮顯示關鍵字
     *
     * @param type $string
     * @param type $term
     * @param type $class
     * @return type
     */
    public static function highlight($string, $term, $class = "highlight")
    {
        $term = preg_replace('/\s+/', ' ', trim($term));
        $words = explode(' ', $term);

        $highlighted = array();
        foreach ($words as $word) {
            $highlighted[] = '<span class="' . $class . '">' . $word . '</span>';
        }

        return str_replace($words, $highlighted, $string);
    }

    /**
     * substr
     *
     * @param   string    $str       required
     * @param   int       $start     required
     * @param   int|null  $length
     * @param   string    $encoding  default UTF-8
     * @return  string
     */
    public static function sub($str, $start, $length = null, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        // substr functions don't parse null correctly
        $length = is_null($length) ? (function_exists('mb_substr') ? mb_strlen($str, $encoding) : strlen($str)) - $start : $length;

        return function_exists('mb_substr') ? mb_substr($str, $start, $length, $encoding) : substr($str, $start, $length);
    }

    /**
     * strlen
     *
     * @param   string  $str       required
     * @param   string  $encoding  default UTF-8
     * @return  int
     */
    public static function length($str, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strlen') ? mb_strlen($str, $encoding) : strlen($str);
    }

    /**
     * lower
     *
     * @param   string  $str       required
     * @param   string  $encoding  default UTF-8
     * @return  string
     */
    public static function lower($str, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strtolower') ? mb_strtolower($str, $encoding) : strtolower($str);
    }

    /**
     * upper
     *
     * @param   string  $str       required
     * @param   string  $encoding  default UTF-8
     * @return  string
     */
    public static function upper($str, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strtoupper') ? mb_strtoupper($str, $encoding) : strtoupper($str);
    }

    /**
     * lcfirst
     *
     * Does not strtoupper first
     *
     * @param   string  $str       required
     * @param   string  $encoding  default UTF-8
     * @return  string
     */
    public static function lcfirst($str, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strtolower') ? mb_strtolower(mb_substr($str, 0, 1, $encoding), $encoding) .
                mb_substr($str, 1, mb_strlen($str, $encoding), $encoding) : lcfirst($str);
    }

    /**
     * ucfirst
     *
     * Does not strtolower first
     *
     * @param   string $str       required
     * @param   string $encoding  default UTF-8
     * @return   string
     */
    public static function ucfirst($str, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strtoupper') ? mb_strtoupper(mb_substr($str, 0, 1, $encoding), $encoding) .
                mb_substr($str, 1, mb_strlen($str, $encoding), $encoding) : ucfirst($str);
    }

    /**
     * ucwords
     *
     * First strtolower then ucwords
     *
     * ucwords normally doesn't strtolower first
     * but MB_CASE_TITLE does, so ucwords now too
     *
     * @param   string   $str       required
     * @param   string   $encoding  default UTF-8
     * @return  string
     */
    public static function ucwords($str, $encoding = null)
    {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_convert_case') ? mb_convert_case($str, MB_CASE_TITLE, $encoding) : ucwords(strtolower($str));
    }

}
