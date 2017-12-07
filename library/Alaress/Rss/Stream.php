<?php
/**
 * Alaress_Rss_Stream
 *
 * Streams an RSS url. Can detect whether it's ATOM or RSS 2.0
 * specification, and chooses a Strategy accordingly
 *
 * The Alaress_Rss_Stream constructor takes a source url, but it
 * can be overridden by using setSource() after instantiation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Alaress_Rss_Stream {
	private $source = null;
	private $limit = 6;
	private $output = array();

	/* This can be dynamically chosen based on structure
	 * of input data. Choose strategy in the setup
	 */
	private $rssStrategy = null;

	/**
	 * Constructor
	 *
	 * Optionally takes a source url as an input. You can override
	 * this by using setSource() after instantiation
	 *
	 * @param null $source
	 */
	public function __construct($source = null) {
		if ($source !== null) {
			$this->setSource($source);
		}
	}

	/**
	 * Configure the class which manages interpretation of RSS feeds
	 *
	 * @return void
	 */
	public function setup() {
		if ($this->getSource() != null) {

			/* The Xml class already uses caching
			 */
			$this->setInput(new Xml($this->getSource()));

			/* If there was data, try to interpret it
			 */
			if (count($this->getInput()->xml)) {

				/* Decide if the feed is an ATOM feed or a regular RSS 2.0
				 */
				if ($this->isInputAtom()) {
					$this->setRssStrategy(new Alaress_Rss_Strategy_Atom());
				}
				else {
					$this->setRssStrategy(new Alaress_Rss_Strategy_Rss20());
				}
			}

			return false;
		}
	}

	/**
	 * Decide if the feed is an ATOM feed
	 *
	 * @return bool
	 */
	private function isInputAtom() {
		foreach($this->getInput()->xml->link as $currentLink) {
			if (strstr($currentLink->attributes()->type, 'atom+xml')) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get an RSS stream if a source was provided
	 *
	 * @return void
	 */
	public function stream() {
		if ($this->getSource() != null) {
			$this->setup();

			/* If a strategy was devised, stream it
			 */
			if ($this->getRssStrategy() != null) {
				$this->setOutput($this->getRssStrategy()->get($this->getInput(), $this->getLimit()));
			}
		}
	}

	/**
	 * Get an RSS stream title
	 *
	 * @return string
	 */
	public function getTitle() {
		if (($this->getSource() != null) && ($this->getRssStrategy() != null)) {
			return $this->getRssStrategy()->getTitle($this->getInput());
		}

		return 'Please enter a feed URL';
	}

	/**
	 * Get an RSS stream link
	 *
	 * @return string
	 */
	public function getLink() {
		if (($this->getSource() != null) && ($this->getRssStrategy() != null)) {
			return $this->getRssStrategy()->getLink($this->getInput());
		}

		return '';
	}

	/* Getters/Setters
	 */

	/**
	 * Set output contents
	 *
	 * @param  $val
	 * @return void
	 */
	private function setOutput($val) {
		$this->output = $val;
	}

	/**
	 * Get output contents
	 *
	 * @return array
	 */
	public function getOutput() {
		return $this->output;
	}

	/**
	 * Set RSS handling class
	 *
	 * @param  $val
	 * @return void
	 */
	private function setRssStrategy($val) {
		$this->rssStrategy = $val;
	}

	/**
	 * Get RSS handling class
	 *
	 * @return null
	 */
	private function getRssStrategy() {
		return $this->rssStrategy;
	}

	/**
	 * Set the limit
	 *
	 * @param  $val
	 * @return void
	 */
	public function setLimit($val) {
		$this->limit = $val;
	}

	/**
	 * Get the limit
	 *
	 * @return int
	 */
	private function getLimit() {
		return $this->limit;
	}

	/**
	 * Set source url
	 *
	 * Silently enforce http:// protocol
	 *
	 * @param  $val
	 * @return void
	 */
	public function setSource($val) {
		$source = str_replace('feed://', 'http://', $val);

		$this->source = $source;
	}

	/**
	 * Get source url
	 *
	 * @return string
	 */
	private function getSource() {
		return $this->source;
	}

	/**
	 * Set the contents of the input rss stream
	 *
	 * @param  $val
	 * @return void
	 */
	private function setInput($val) {
		$this->input = $val;
	}

	/**
	 * Get the contents of the input rss stream
	 *
	 * @return
	 */
	private function getInput() {
		return $this->input;
	}
}
