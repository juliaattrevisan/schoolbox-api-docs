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

		$pattern = '#.*content=[\"\']([^\"\']+)[\"\']?.*itemprop=[\"\']?image[\"\']?#m';

		preg_match($pattern, $this->getCurl()->getResult(), $matches);

		/* If an opengraph image was found, curl that too
		 */
		if (count($matches)) {
			return $matches[0];
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}