<?php
/**
 * Alaress_Rss_Strategy_LastRss
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
 * @version     SVN: $Id$
 */
class Alaress_Rss_Strategy_LastRss extends Alaress_Rss_Strategy_LastRssOriginal {

	/**
	 * Constructor
	 * Override the lastRss dir setting
	 */
	public function __construct() {
		$this->setCacheDir(Cfg::get('xml_dir'));
	}

	/**
	 * Set the cache dir used in lastRss natively
	 *
	 * @param  $val
	 * @return void
	 */
	public function setCacheDir($val) {
		$this->cache_dir = $val;
	}

	/**
	 * Get the cache dir used in lastRss natively
	 *
	 * @return
	 */
	public function getCacheDir() {
		return $this->cache_dir;
	}
}
