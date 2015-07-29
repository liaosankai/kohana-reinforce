<?php

defined('SYSPATH') OR die('No direct script access.');

class Reinforce_Text extends Kohana_Text {

    /**
     * Determine if a given string contains a given substring.
     *
     * @param  string  $haystack
     * @param  string|array  $needles
     * @return bool
     */
    public static function contains($haystack, $needles) {
        foreach ((array) $needles as $needle) {
            if ($needle != '' && strpos($haystack, $needle) !== false)
                return true;
        }
        return false;
    }

    static public function starts_with($haystack, $needle) {
        // search backwards starting from haystack length characters from the end
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
    }

    static public function ends_with($haystack, $needle) {
        // search forward starting from end minus needle length characters
        return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
    }

    /**
     * ※ 修正捕捉 IE11 錯誤的問的
     *
     * Returns information about the client user agent.
     *
     *     // Returns "Chrome" when using Google Chrome
     *     $browser = Text::user_agent('browser');
     *
     * Multiple values can be returned at once by using an array:
     *
     *     // Get the browser and platform with a single call
     *     $info = Text::user_agent(array('browser', 'platform'));
     *
     * When using an array for the value, an associative array will be returned.
     *
     * @param   mixed $value array or string to return: browser, version, robot, mobile, platform
     * @return  mixed   requested information, FALSE if nothing is found
     * @uses    Kohana::$config
     */
    public static function user_agent($agent, $value) {
        if (is_array($value)) {
            $data = array();
            foreach ($value as $part) {
                // Add each part to the set
                $data[$part] = Text::user_agent($agent, $part);
            }
            return $data;
        }

        if ($value === 'browser' OR $value == 'version') {
            // Extra data will be captured
            $info = array();

            // Load browsers
            $browsers = Kohana::$config->load('user_agents')->browser;

            // hack IE 11
            if (strpos($agent, 'Trident/7.0; rv:11.0') !== false) {
                $info['browser'] = 'Internet Explorer';
                $info['version'] = '11.0';
                return $info[$value];
            }

            foreach ($browsers as $search => $name) {
                if (stripos($agent, $search) !== FALSE) {
                    // Set the browser name
                    $info['browser'] = $name;

                    if (preg_match('#' . preg_quote($search) . '[^0-9.]*+([0-9.][0-9.a-z]*)#i', Request::$user_agent, $matches)) {
                        // Set the version number
                        $info['version'] = $matches[1];
                    } else {
                        // No version number found
                        $info['version'] = FALSE;
                    }

                    return $info[$value];
                }
            }
        } else {
            // Load the search group for this type
            $group = Kohana::$config->load('user_agents')->$value;

            foreach ($group as $search => $name) {
                if (stripos($agent, $search) !== FALSE) {
                    // Set the value name
                    return $name;
                }
            }
        }

        // The value requested could not be found
        return FALSE;
    }

    /**
     * 高亮顯示關鍵字
     *
     * @param string $string 從字串中
     * @param string $search 搜尋關鍵字
     * @param bool $case_insensitive 忽略大小寫
     * @param type $class
     * @return type
     */
    public static function highlight($string, $search, $case_insensitive = FALSE, $class = "highlight") {
        // 先將所有字元用 ' '
        $search = mb_ereg_replace('/\s+/', ' ', trim($search));
        $words = explode(' ', $search);

        $highlighted = array();
        foreach ($words as $word) {
            $highlighted[] = '<span class="' . $class . '">' . $word . '</span>';
        }
        if ($case_insensitive) {
            return str_ireplace($words, $highlighted, $string);
        } else {
            return str_replace($words, $highlighted, $string);
        }
    }

    /**
     * substr
     *
     * @param   string $str required
     * @param   int $start required
     * @param   int|null $length
     * @param   string $encoding default UTF-8
     * @return  string
     */
    public static function sub($str, $start, $length = null, $encoding = null) {
        $encoding or $encoding = \Kohana::$charset;

        // substr functions don't parse null correctly
        $length = is_null($length) ? (function_exists('mb_substr') ? mb_strlen($str, $encoding) : strlen($str)) - $start : $length;

        return function_exists('mb_substr') ? mb_substr($str, $start, $length, $encoding) : substr($str, $start, $length);
    }

    /**
     * strlen
     *
     * @param   string $str required
     * @param   string $encoding default UTF-8
     * @return  int
     */
    public static function length($str, $encoding = null) {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strlen') ? mb_strlen($str, $encoding) : strlen($str);
    }

    /**
     * lower
     *
     * @param   string $str required
     * @param   string $encoding default UTF-8
     * @return  string
     */
    public static function lower($str, $encoding = null) {
        $encoding or $encoding = \Kohana::$charset;

        return function_exists('mb_strtolower') ? mb_strtolower($str, $encoding) : strtolower($str);
    }

    /**
     * upper
     *
     * @param   string $str required
     * @param   string $encoding default UTF-8
     * @return  string
     */
    public static function upper($str, $encoding = null) {
        $encoding or $encoding = Kohana::$charset;

        return function_exists('mb_strtoupper') ? mb_strtoupper($str, $encoding) : strtoupper($str);
    }

    /**
     * lcfirst
     *
     * Does not strtoupper first
     *
     * @param   string $str required
     * @param   string $encoding default UTF-8
     * @return  string
     */
    public static function lcfirst($str, $encoding = null) {
        $encoding or $encoding = Kohana::$charset;

        return function_exists('mb_strtolower') ? mb_strtolower(mb_substr($str, 0, 1, $encoding), $encoding) .
                mb_substr($str, 1, mb_strlen($str, $encoding), $encoding) : lcfirst($str);
    }

    /**
     * ucfirst
     *
     * Does not strtolower first
     *
     * @param   string $str required
     * @param   string $encoding default UTF-8
     * @return   string
     */
    public static function ucfirst($str, $encoding = null) {
        $encoding or $encoding = Kohana::$charset;

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
     * @param   string $str required
     * @param   string $encoding default UTF-8
     * @return  string
     */
    public static function ucwords($str, $encoding = null) {
        $encoding or $encoding = Kohana::$charset;

        return function_exists('mb_convert_case') ? mb_convert_case($str, MB_CASE_TITLE, $encoding) : ucwords(strtolower($str));
    }

    /**
     * 移除字串中的 HTML 標籤
     *
     * @param string $text 帶有 HTML 標籤的字串
     * @param string $tags 准許存在的標籤(意思就是不移除)
     * @param boolean $remove_content 是否同時移除標籤裡面的內容
     * @param boolean $no_spaces_eol 將多空白變成一個空白，並移除換行符號
     * @return string 移除 HTML 後的字串
     */
    public static function strip_tags($text, $tags, $remove_content = FALSE, $no_spaces_eol = FALSE) {
        if ($no_spaces_eol) {
            $string = trim($string);
            $string = preg_replace('/\s+/', ' ', $string); // 多個空白變成一個空白
            $string = str_replace("\r", '', $string);    // --- 將 \r 取代成一個空字串
            $string = str_replace("\n", ' ', $string);   // --- 將 \n 取代成一個空白
            $string = str_replace("\t", ' ', $string);   // --- 將 \t 取代成一個空白
        }

        if ($remove_content) {
            preg_match_all('/<(.+?)[\s]*\/?[\s]*>/si', trim($tags), $tags);
            $tags = array_unique($tags[1]);
            if (is_array($tags) AND count($tags) > 0) {
                return preg_replace('@<(?!(?:' . implode('|', $tags) . ')\b)(\w+)\b.*?>.*?</\1>@si', '', $text);
            } else {
                return preg_replace('@<(\w+)\b.*?>.*?</\1>@si', '', $text);
            }
            return $text;
        } else {
            return strip_tags($text, $tags);
        }
    }

}
