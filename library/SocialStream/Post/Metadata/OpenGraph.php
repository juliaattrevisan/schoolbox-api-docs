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
		$query = "//meta[@property='og:title']";

		$allElements = $this->getXml()->query($query);

		foreach($allElements as $currentElement) {
			foreach($currentElement->attributes as $currentAttribute) {
				if ($currentAttribute->nodeName == 'content') {
					$this->setTitle(trim($currentAttribute->nodeValue));

					return;
				}
			}
		}
	}

	/**
	 * Find the description
	 */
	private function analyseDescription() {
		$query = "//meta[@property='og:description']";

		$allElements = $this->getXml()->query($query);

		foreach($allElements as $currentElement) {
			foreach($currentElement->attributes as $currentAttribute) {
				if ($currentAttribute->nodeName == 'content') {
					$this->setDescription(trim($currentAttribute->nodeValue));

					return;
				}
			}
		}
	}
}