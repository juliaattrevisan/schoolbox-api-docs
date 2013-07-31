<?php
/**
 * ItempropImage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropImage extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		$allPatterns = array();

		/* Search for content/itemprop combo
 		 */
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']image[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']image[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';

		/* Search for src/itemprop combo
		 */
		$allPatterns[] = '#src=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']image[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']image[\"\'].*src=[\"\']([^\"\']+)[\"\']#m';

		for ($i=0, $found=false; ($i < count($allPatterns)) && !$found; $i++) {
			if (preg_match($allPatterns[$i], $this->getCurl()->getResult(), $matches)) {
				if ($this->getFileType()->isImage()) {
					$found = true;
				}
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