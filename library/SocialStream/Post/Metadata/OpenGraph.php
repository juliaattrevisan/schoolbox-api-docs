<?php
/**
 * Vimeo
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Metadata;


class OpenGraph extends Metadata {

	/**
	 * Fetch information out of the meta tags
	 *
	 * @return mixed
	 */
	public function fetchMetadata() {
		$this->analyseTitle();
		$this->analyseDescription();

		if (strlen($this->getTitle()) && strlen($this->getDescription())) {
			return $this;
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchMetadata();
		}
	}

	/**
	 * Find the title
	 */
	private function analyseTitle() {
		$matches = array();

		/* Search for content/title combo
 		 */
		$allPatterns = array();
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*property=[\"\']og:title[\"\']#m';
		$allPatterns[] = '#property=[\"\']og:title[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';

		foreach($allPatterns as $currentPattern) {
			preg_match($currentPattern, $this->getCurl()->getResult(), $matches);
		}

		if (count($matches)) {
			$this->setTitle($matches[1]);
		}
	}

	/**
	 * Find the description
	 */
	private function analyseDescription() {
		$matches = array();

		/* Search for content/title combo
 		 */
		$allPatterns = array();
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*property=[\"\']og:description[\"\']#m';
		$allPatterns[] = '#property=[\"\']og:description[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';

		foreach($allPatterns as $currentPattern) {
			preg_match($currentPattern, $this->getCurl()->getResult(), $matches);
		}

		if (count($matches)) {
			$this->setDescription($matches[1]);
		}
	}
}