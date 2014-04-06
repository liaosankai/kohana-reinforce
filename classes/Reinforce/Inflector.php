<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_Inflector extends Kohana_Inflector {

    /**
     * Takes an underscored classname and uppercases all letters after the underscores.
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
     * Takes a CamelCased string and returns an underscore separated version.
     *
     * @param   string  the CamelCased word
     * @return  string  an underscore separated version of $camel_cased_word
     */
    public static function underscore($camel_cased_word)
    {
        return \Text::lower(preg_replace('/([A-Z]+)([A-Z])/', '\1_\2', preg_replace('/([a-z\d])([A-Z])/', '\1_\2', strval($camel_cased_word))));
    }

}
