<?php
/**
 * Local
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Cache;


class Local extends \Alaress_Cache_Abstract {

	/**
	 * A prefix for cached filenames
	 *
	 * @var string
	 */
	protected $cacheFilePrefix = 'socialstream_thumbnail';

	/**
	 * Hook method
	 *
	 * @return mixed|void
	 */
	protected function setup() {
		$this->setCachedTime(\Cfg::get('xml_cache_timeout'));
		$this->setLockTime($this->getLockTimeMultiplier() * $this->getCachedTime());
	}

	/**
	 * Figger out what the cached filename should be
	 *
	 * @return string
	 */
	protected function generateCachedFilename() {
		return \Cfg::get('stream_dir') . '/socialstream/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl());
	}
}