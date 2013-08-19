<?php
/**
 * AbstractFactory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Factory;


abstract class AbstractFactory {

	/**
	 * The submitted url
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * Regexes that permit the relevant sites
	 * These are used to spawn a SocialStream_Post_Video instance
	 *
	 * @var array
	 */
	protected $videoSites = array(
		'#^(http://)?(www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_\-]+)#',
		'#^(http://)?vimeo\.com/([0-9]+)#',
		'#^(http://)?www\.ted\.com/talks/([a-zA-Z0-9_]+)\.html#',
	);

	/**
	 * Produce an object
	 *
	 * @return mixed
	 */
	abstract public function produce();

	/* Getters/Setters
	 */

	/**
	 * Set video sites
	 *
	 * @param array $videoSites
	 */
	protected function setVideoSites($videoSites) {
		$this->videoSites = $videoSites;
	}

	/**
	 * Get video sites
	 *
	 * @return array
	 */
	protected function getVideoSites() {
		return $this->videoSites;
	}

	/**
	 * Set url
	 *
	 * @param string $url
	 */
	protected function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	protected function getUrl() {
		return $this->url;
	}
}