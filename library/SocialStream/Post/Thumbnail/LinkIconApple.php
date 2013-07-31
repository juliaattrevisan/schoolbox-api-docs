<?php
/**
 * LinkIconApple
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class LinkIconApple extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$allPatterns = array();
		$allPatterns[] = '#href=[\"\']([^\"\']+)[\"\'].*rel=[\"\']apple\-touch\-icon[\"\']#m';
		$allPatterns[] = '#rel=[\"\']apple\-touch\-icon[\"\'].*href=[\"\']([^\"\']+)[\"\']#m';

		return $this->analyse($allPatterns);
	}
}