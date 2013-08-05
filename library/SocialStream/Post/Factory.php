<?php
/**
 * SocialStream_Post_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Post;

class Factory {

	/**
	 * The submitted url
	 *
 	 * @var string
	 */
	private $url = '';

	/**
	 * Regexes that permit the relevant sites
	 * These are used to spawn a SocialStream_Post_Video instance
	 *
	 * @var array
	 */
	private $videoSites = array(
		'#^(http://)?(www\.)?youtube\.com/watch\?v=([a-zA-Z0-9_\-]+)#',
		'#^(http://)?vimeo\.com/([0-9]+)#',
	);

	/**
	 * Constructor
	 *
	 * @param $url
	 */
	public function __construct($url) {
		$this->setUrl($url);
	}

	/**
	 * Produce an instance of SocialStream_Post_Abstract subclass
	 *
	 * @return mixed
	 */
	public function produce() {
		$url = trim($this->getUrl());

		/* If a url was provided, determine what kind it was
		 */
		if (strlen($url)) {

			/* Figure out if it's a video
			 */
			foreach ($this->getVideoSites() as $currentPattern) {
				if (preg_match($currentPattern, $url)) {
					return new \SocialStream_Post_Video();
				}
			}

			/* Alternatively it's just a link to the internet
			 */
			return new \SocialStream_Post_Url();
		}

		/* If it was not a video, link to the internet, then assume it's
		 * just a plain old text message
		 */
		return new \SocialStream_Post_String();
	}

	/* Getters/Setters
	 */

	/**
	 * Set video sites
	 *
	 * @param array $videoSites
	 */
	private function setVideoSites($videoSites) {
		$this->videoSites = $videoSites;
	}

	/**
	 * Get video sites
	 *
	 * @return array
	 */
	private function getVideoSites() {
		return $this->videoSites;
	}

	/**
	 * Set url
	 *
	 * @param string $url
	 */
	private function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	private function getUrl() {
		return $this->url;
	}
}