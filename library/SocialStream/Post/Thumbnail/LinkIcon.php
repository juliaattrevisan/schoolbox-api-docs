<?php
/**
 * LinkIcon
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class LinkIcon extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$allPatterns = array();
		$allPatterns[] = '#href=[\"\']([^\"\']+)[\"\'].*rel=[\"\']icon[\"\']#m';
		$allPatterns[] = '#rel=[\"\']icon[\"\'].*href=[\"\']([^\"\']+)[\"\']#m';

		return $this->analyse($allPatterns);
	}
}