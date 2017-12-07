<?php
/**
 * Alaress_Rss_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
class Alaress_Rss_Cache extends Alaress_Cache_Abstract {

	/**
	 * A prefix for cached filenames
	 *
	 * @var string
	 */
	protected $cacheFilePrefix = 'rss';

	/**
	 * Hook method
	 *
	 * @return mixed|void
	 */
	protected function setup() {
		$this->setCachedTime(Cfg::get('xml_cache_timeout'));
		$this->setLockTime($this->getLockTimeMultiplier() * $this->getCachedTime());
	}


	/**
	 * Load contents from the cache in SimpleXML object
	 *
	 * @return SimpleXMLElement
	 */
	public function load() {
		libxml_use_internal_errors(true);
		return new SimpleXMLElement($this->getCachedFile(), null, true);
	}

}
