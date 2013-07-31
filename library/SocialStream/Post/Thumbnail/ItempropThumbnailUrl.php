<?php
/**
 * ItempropPhoto
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropPhoto extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		$allPatterns = array();
		$allPatterns[] = '#href=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']thumbnailUrl[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']thumbnailUrl[\"\'].*href=[\"\']([^\"\']+)[\"\']#m';

		for ($i=0, $found=false; ($i < count($allPatterns)) && !$found; $i++) {
			if (preg_match($allPatterns[$i], $this->getCurl()->getResult(), $matches)) {
				$found = true;
			}
		}

		/* If an opengraph image was found, curl that too
		 */
		if ($found) {
			return $matches[1];
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}