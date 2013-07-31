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
		$allPatterns = array();

		/* Search for content/itemprop combo
 		 */
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']icon[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']icon[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';

		/* Search for src/itemprop combo
		 */
		$allPatterns[] = '#src=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']icon[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']icon[\"\'].*src=[\"\']([^\"\']+)[\"\']#m';

		return $this->analyse($allPatterns);
	}
}