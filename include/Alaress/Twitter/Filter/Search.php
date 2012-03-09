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
	private $keyword = '';

	/**
	 * Constructor
	 *
	 * @param $keyword
	 */
	public function __construct($keyword) {
		$this->setKeyword($keyword);
	}

	/**
	 * Replacements to perform on each tweet
	 *
	 * @param  $text
	 * @return mixed|string
	 */
	public function filterText($text) {
		$keys = array();

		/* Ensure each keyword starts with a hash
		 */
		$exploded = explode(' ', trim($this->getKeyword()));

		foreach($exploded as $current) {
			if ($current[0] != '#') {
				$keys[] = '#' . $current;
			}
			else {
				$keys[] = $current;
			}
		}

		/* Replace spaces with regex OR symbol
		 */
		$refined = implode('|', $keys);

		/* Search for multiple hashtags
		 */
		$pattern = "/($refined)/";

		if (preg_match($pattern, $text)) {
			return $text;
		}

		/* Allow rendering algorithm to skip null text fields
		 */
		return null;
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
