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


class MetaTag extends Metadata {

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
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*name=[\"\']title[\"\']#ms';
		$allPatterns[] = '#name=[\"\']title[\"\'].*content=[\"\']([^\"\']+)[\"\']#ms';

		/* Google uses itemprop!
		 */
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']name[\"\']#ms';
		$allPatterns[] = '#itemprop=[\"\']name[\"\'].*content=[\"\']([^\"\']+)[\"\']#ms';

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
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*name=[\"\']description[\"\']#ms';
		$allPatterns[] = '#name=[\"\']description[\"\'].*content=[\"\']([^\"\']+)[\"\']#ms';

		/* Google uses itemprop!
		 */
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']description[\"\']#ms';
		$allPatterns[] = '#itemprop=[\"\']description[\"\'].*content=[\"\']([^\"\']+)[\"\']#ms';

		foreach($allPatterns as $currentPattern) {
			preg_match($currentPattern, $this->getCurl()->getResult(), $matches);
		}

		if (count($matches)) {
			$this->setDescription($matches[1]);
		}
	}
}