<?php

defined('SYSPATH') or die('No direct script access.');

class Kohana_Markdown {

    /**
     * @var  object  Swiftmailer instance
     */
    protected static $_parser;

    public static function parse($text)
    {
        if (!class_exists("Parsedown")) {
            require Kohana::find_file('vendor/parsedown', 'Parsedown');
        }
        Markdown::$_parser = new Parsedown();
        return Markdown::$_parser->text($text);
    }

}
