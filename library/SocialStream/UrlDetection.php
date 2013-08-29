<?php
/**
 * UrlDetection
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream;


class UrlDetection {

	/**
	 * Urls used by Storage
	 *
	 * @var array
	 */
	private $storagePatterns = array(
		'#^/cms/sendVideo\.php\?#',
		'#^/storage/fetch\.php\?#',
		'#^/storage/image\.php\?#',
	);

	/**
	 * Urls used by the resource browser
	 *
	 * @var array
	 */
	private $resourcePatterns = array(
		'#^/send\.php\?#',
	);

	/**
	 * The url to analyse
	 *
	 * @var string
	 */
	private $url = '';

	/**
	 * Constructor
	 */
	public function __construct($url) {
		$this->setUrl($url);
	}

	/**
	 * Get the hash value out of the url, if provided
	 */
	public function getHash() {
		$bits = parse_url($this->getUrl());
		$query = $bits['query'];

		parse_str($query, $values);
		$hash = $values['hash'];

		return $hash;
	}

	/**
	 * Bypass caching for local urls
	 *
	 * @return bool
	 */
	public function isLocalUrl() {

		/* A list of local streaming urls which are used to
		 * bypass caching for Upload and Resource attachments
		 */
		$allPatterns = array_merge(
			$this->getStoragePatterns(),
			$this->getResourcePatterns()
		);

		foreach($allPatterns as $currentPattern) {
			if (preg_match($currentPattern, $this->getUrl())) {
				return true;
			}
		}
	}

	/**
	 * Bypass caching for local urls
	 *
	 * @return bool
	 */
	public function isStorageUrl() {
		foreach($this->getStoragePatterns() as $currentPattern) {
			if (preg_match($currentPattern, $this->getUrl())) {
				return true;
			}
		}
	}

	/**
	 * Bypass caching for local urls
	 *
	 * @return bool
	 */
	public function isResourceUrl() {
		foreach($this->getResourcePatterns() as $currentPattern) {
			if (preg_match($currentPattern, $this->getUrl())) {
				return true;
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set resource patterns
	 *
	 * @param array $resourcePatterns
	 */
	private function setResourcePatterns($resourcePatterns) {
		$this->resourcePatterns = $resourcePatterns;
	}

	/**
	 * Get resource patterns
	 *
	 * @return array
	 */
	public function getResourcePatterns() {
		return $this->resourcePatterns;
	}

	/**
	 * Set storage patterns
	 *
	 * @param array $storagePatterns
	 */
	private function setStoragePatterns($storagePatterns) {
		$this->storagePatterns = $storagePatterns;
	}

	/**
	 * Get storage patterns
	 *
	 * @return array
	 */
	public function getStoragePatterns() {
		return $this->storagePatterns;
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