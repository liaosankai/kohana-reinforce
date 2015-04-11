<?php

defined('SYSPATH') OR die('No direct script access.');

class Kohana_Markdown
{

    protected static $_parser;

    public static function parse($text)
    {
        if (!class_exists("Parsedown")) {
            require Kohana::find_file('vendor/parsedown', 'Parsedown');
        }
        if (!class_exists("ParsedownExtra")) {
            require Kohana::find_file('vendor/parsedown', 'ParsedownExtra');
        }
        Markdown::$_parser = new ParsedownExtra();
        return Markdown::$_parser->text($text);
    }

}
