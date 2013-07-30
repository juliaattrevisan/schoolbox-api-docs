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
		$matches = array();

		$allPatterns = array();
		$allPatterns[] = '#property=[\"\']og:image[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*property=[\"\']og:image[\"\']#m';

		for ($i=0, $found=false; ($i < count($allPatterns)) && !$found; $i++) {
			if (preg_match($allPatterns[$i], $this->getCurl()->getResult(), $matches)) {
				$found = true;
			}
		}
$found=false;
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