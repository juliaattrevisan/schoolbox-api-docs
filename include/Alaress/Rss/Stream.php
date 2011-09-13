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
 * The default source url is the alaress feedburner url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Rss_Stream {
	private $source = 'http://feeds2.feedburner.com/alaress';
	private $limit = 6;

	/* This can be dynamically chosen based on structure
	 * of input data. Choose strategy in the setup
	 */
	private $rssStrategy = null;

	/**
	 * Constructor
	 *
	 * Optionally akes a source url as an input. You can override
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
	private function setup() {
		$this->setInput(new Xml($this->getSource(), RSS_cache_time));

		/* Decide if the feed is an ATOM feed or a regular RSS 2.0
		 */
		if ($this->isInputAtom()) {
			$rssStrategy = 'Alaress_Rss_Strategy_Atom';
		}
		else if ($this->isInputRss20()) {
			$rssStrategy = 'Alaress_Rss_Strategy_Rss20';
		}

		$this->setRssStrategy(new $rssStrategy());
	}

	/**
	 * Decide if the feed is an ATOM feed
	 *
	 * @param  $rss
	 * @return bool
	 */
	private function isInputAtom() {
		if (isset($this->getInput()->xml->link[0]) && strstr($this->getInput()->xml->link[0]->attributes()->type,'atom+xml')) {
			return true;
		}

		return false;
	}

	/**
	 * Decide if the feed is an RSS 2.0 feed
	 *
	 * @param  $rss
	 * @return bool
	 */
	private function isInputRss20() {
		if (isset($this->getInput()->xml->attributes()->version) && ($this->getInput()->xml->attributes()->version == '2.0')) {
			return true;
		}

		return false;
	}

	/**
	 * Get an RSS stream
	 *
	 * @return
	 */
	public function stream() {
		$this->setup();

		return $this->getRssStrategy()->get($this->getInput(), $this->getLimit());
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
	 * @param  $val
	 * @return void
	 */
	public function setSource($val) {
		$this->source = $val;
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
