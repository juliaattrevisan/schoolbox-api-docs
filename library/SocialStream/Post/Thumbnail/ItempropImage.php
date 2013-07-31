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
		$allPatterns = array();

		/* Search for content/itemprop combo
 		 */
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']image[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']image[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';

		/* Search for src/itemprop combo
		 */
		$allPatterns[] = '#src=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']image[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']image[\"\'].*src=[\"\']([^\"\']+)[\"\']#m';

		return $this->analyse($allPatterns);
	}
}