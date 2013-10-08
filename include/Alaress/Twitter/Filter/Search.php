<?php
/**
 * Alaress_Twitter_Filter_Search
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Twitter_Filter_Search implements Alaress_Twitter_Filter_Interface {
	private $dateFormat = 'F jS Y, H:i';

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/**
	 * Replacements to perform on each tweet
	 *
	 * @param  $text
	 * @return mixed|string
	 */
	public function filterText($text) {

        /* Set up regular expressions
        */
        $pattern = array();
        $replace = array();

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
        $pattern[] = '/(^|\s)#(\w+)/';
        $replace[] = '\1<a href="http://twitter.com/search?q=%23\2&src=hash">#\2</a>';


        $finalText = preg_replace($pattern, $replace, $text);

		return $finalText;
	}

	/**
	 * Change the date format of the tweet's publication date
	 *
	 * @param  $date
	 * @return string
	 */
	public function filterDate($date) {
		return gmdate($this->getDateFormat(), strtotime($date));
	}

	/* Getters/Setters
	 */

	/**
	 * Find out what date format to use for tweet dates
	 *
	 * @return string
	 */
	private function getDateFormat() {
		return $this->dateFormat;
	}

	/**
	 * Set keyword to search for
	 *
	 * @param $keyword
	 */
	private function setKeyword($keyword) {
		$this->keyword = $keyword;
	}

	/**
	 * Get keyword to search for
	 *
	 * @return string
	 */
	private function getKeyword() {
		return $this->keyword;
	}
}
