<?php
/**
 * ItempropIcon
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropIcon extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$query = "//*[@itemprop='icon']";

		$allElements = $this->getXml()->query($query);

		foreach($allElements as $currentElement) {
			foreach($currentElement->attributes as $currentAttribute) {
				if (in_array($currentAttribute->nodeName, array('src', 'content'))) {
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