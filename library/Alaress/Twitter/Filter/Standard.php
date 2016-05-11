<?php
/**
 * Created by Steve Chan.
 *
 * User: Steve
 * Date: 19/07/11
 * Time: 3:01 PM
 *
 * Twitter_Filter_Standard
 *
 * Performs replacements, regexes etc. on the tweet Description and PubDate
 * fields. This is where content gets modified before being passed to frontend,
 * e.g. removing username from the tweet prefix, changing the date format etc.
 */
class Alaress_Twitter_Filter_Standard implements Alaress_Twitter_Filter_Interface {
	private $user;
	private $pattern;
	private $replace;
	private $removeUserPrefix = false;
	private $dateFormat = 'F jS Y, H:i';

	/**
	 * Regexes. Activate links and stuff by default
	 */
	public function __construct() {
		$this->setReplacements();
	}

	/**
	 * Replacements to perform on each tweet
	 *
	 * @param  $text
	 * @return mixed|string
	 */
	public function filterText($text) {
		$text = preg_replace($this->getPattern(), $this->getReplace(), $text);
		$text = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $text);

		/* Remove the username from the tweet prefix?
		 */
		if ($this->getRemoveUserPrefix()) {
			$text = substr($text, strlen($this->getUser())+2);
		}

		return $text;
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

	/**
	 * Set up regex for tweet contents
	 *
	 * @return void
	 */
	protected function setReplacements() {

		/* Set up regular expressions
		 */
		$pattern = array();
		$replace = array();

		/* External urls in tweets
		 */
		$pattern[] = "/(http:\/\/)(.*?)\/([\w\.\/\&\=\?\-\,\:\;\#\_\~\%\+]*)/";
		$replace[] = "<a href=\"\\0\">\\0</a>";

		/* Reference to some other twitter user
		 */
		$pattern[] = "(@([a-zA-Z0-9\_]+))";
		$replace[] = "<a href=\"http://www.twitter.com/\\1\">\\0</a>";

		/* Search pattern used by #term syntax
		 */
		$pattern[] = '/(^|\s)#(\w+)/';
		$replace[] = '\1<a href="http://search.twitter.com/search?q=%23\2">#\2</a>';

		$this->setPattern($pattern);
		$this->setReplace($replace);
	}

	/**
	 * Set the pattern for replacements in each tweet's content
	 *
	 * @param  $pattern
	 * @return void
	 */
	protected function setPattern($pattern) {
		$this->pattern = $pattern;
	}

	/**
	 * Gets the pattern for replacements in each tweet's content
	 *
	 * @return
	 */
	protected function getPattern() {
		return $this->pattern;
	}

	/**
	 * Set the replacements for each tweet's content
	 *
	 * @param  $replace
	 * @return void
	 */
	protected function setReplace($replace) {
		$this->replace = $replace;
	}

	/**
	 * Get the replacements for each tweet's content
	 *
	 * @return
	 */
	protected function getReplace() {
		return $this->replace;
	}

	/**
	 * Set the option to show/hide the username as a prefix for each tweet
	 *
	 * Chainable method
	 *
	 * @param  $prefix
	 * @return TwitterFilter
	 */
	public function setRemoveUserPrefix($prefix) {
		$this->removeUserPrefix = $prefix;

		return $this;
	}

	/**
	 * Get the option to show/hide the username as a prefix for each tweet
	 *
	 * @return bool
	 */
	protected function getRemoveUserPrefix() {
		return $this->removeUserPrefix;
	}

	/**
	 * Set the date format to use for tweet dates
	 *
	 * Chainable method
	 *
	 * @param  $format
	 * @return TwitterFilter
	 */
	public function setDateFormat($format) {
		$this->dateFormat = $format;

		return $this;
	}

	/**
	 * Find out what date format to use for tweet dates
	 *
	 * @return string
	 */
	public function getDateFormat() {
		return $this->dateFormat;
	}

	/**
	 * Set the user whose tweets you are loading
	 *
	 * @param  $user
	 * @return void
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get the user whose tweets you are loading
	 *
	 * @return
	 */
	public function getUser() {
		return $this->user;
	}
}

