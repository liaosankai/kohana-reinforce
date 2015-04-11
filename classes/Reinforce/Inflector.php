<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_Inflector extends Kohana_Inflector
{

    /**
     * 將蛇型字串的每個單字第一個字母大寫
     *
     * @example
     *
     *   Inflector::words_to_upper('fuel_users'); // returns Fuel_Users
     *   Inflector::words_to_upper('module::method', '::'); // returns Module::Method
     *
     * @param   string  classname
     * @param   string  separator
     * @return  string
     */
    public static function words_to_upper($class, $sep = '_')
    {
        return str_replace(' ', $sep, ucwords(str_replace($sep, ' ', $class)));
    }

    /**
     * 將駱峰式字串轉成蛇型字串
     *
     * @example
     *
     *   Arr::underscore('ApplesAndOranges'); // returns apples_and_oranges
     *
     * @param   string  the CamelCased word
     * @return  string  an underscore separated version of $camel_cased_word
     */
    public static function underscore($camel_cased_word)
    {
        return Text::lower(preg_replace('/([A-Z]+)([A-Z])/', '\1_\2', preg_replace('/([a-z\d])([A-Z])/', '\1_\2', strval($camel_cased_word))));
    }

}
