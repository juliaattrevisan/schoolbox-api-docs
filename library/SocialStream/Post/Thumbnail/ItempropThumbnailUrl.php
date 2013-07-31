<?php
/**
 * ItempropThumbnailUrl
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class ItempropThumbnailUrl extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$allPatterns = array();
		$allPatterns[] = '#href=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']thumbnailUrl[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']thumbnailUrl[\"\'].*href=[\"\']([^\"\']+)[\"\']#m';

		return $this->analyse($allPatterns);
	}
}