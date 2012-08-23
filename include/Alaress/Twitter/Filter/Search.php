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
		$finalText = $text;

		$pattern = "#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#iS";

		preg_match_all($pattern, $text, $matches);

		/* Uniqify result set to prevent nested replacement if multiple dupe urls were found
		 */
		if (is_array($matches) && count($matches)) {
			$allMatches = array_values(array_unique($matches[0]));

			foreach($allMatches as $current) {
				$finalText = str_replace($current, "<a href=\"$current\">$current</a>", $finalText);
			}
		}

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
