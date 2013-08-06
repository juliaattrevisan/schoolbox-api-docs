<?php
/**
 * OpenGraph
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class OpenGraph extends Thumbnail {

	/**
	 * Find og:image
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$query = "/html/head/meta[@property='og:image']";

		$allElements = $this->getXml()->query($query);

		foreach($allElements as $currentElement) {
			foreach($currentElement->attributes as $currentAttribute) {
				if ($currentAttribute->nodeName == 'content') {
					$match = trim($currentAttribute->nodeValue);

					/* Check this path for extension and if it is an image extension,
					 * presume something useful was found
					 */
					$this->getFileType()->setPath($match);

					if ($this->getFileType()->isImageExtension()) {
						return $match;
					}
				}
			}
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}