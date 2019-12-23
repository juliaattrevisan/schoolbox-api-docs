<?php

/**
 * Alaress_Twitter_Filter_Search
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Twitter_Filter_Search implements Alaress_Twitter_Filter_Interface
{
    /**
     * @var string
     */
    private $dateFormat = 'F jS Y, H:i';

    /**
     * Replacements to perform on each tweet
     *
     * @param  $text
     * @return mixed|string
     */
    public function filterText($text)
    {

        /* Set up regular expressions
        */
        $pattern = [];
        $replace = [];

        /* External urls in tweets
         */
        $pattern[] = "/(?<!href=[\"\'])(https?:\/\/[\w\-\.!~?&=+\*\'(),\/]+)/i";
        $replace[] = "<a href=\"\\0\">\\0</a>";

        /* Reference to some other twitter user
         */
        $pattern[] = "(@([a-zA-Z0-9\_]+))";
        $replace[] = "<a href=\"http://twitter.com/\\1\">\\0</a>";

        /* Search pattern used by #term syntax
         */
        $pattern[] = '/(^|\s)#([a-zA-Z0-9-_\.]+)/s';
        $replace[] = '\1<a href="http://twitter.com/search?q=%23\2&src=hash&tweet_mode=extended">#\2</a>';

        return preg_replace($pattern, $replace, $text);
    }

    /**
     * Change the date format of the tweet's publication date
     *
     * @param  $date
     * @return string
     */
    public function filterDate($date)
    {
        return gmdate($this->dateFormat, strtotime($date));
    }
}
