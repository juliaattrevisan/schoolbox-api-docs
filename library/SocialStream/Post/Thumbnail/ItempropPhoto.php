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
		$allPatterns = array();

		/* Search for content/itemprop combo
		 */
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']photo[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']photo[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';

		/* Search for src/itemprop combo
		 */
		$allPatterns[] = '#src=[\"\']([^\"\']+)[\"\'].*itemprop=[\"\']photo[\"\']#m';
		$allPatterns[] = '#itemprop=[\"\']photo[\"\'].*src=[\"\']([^\"\']+)[\"\']#m';

		return $this->analyse($allPatterns);
	}
}