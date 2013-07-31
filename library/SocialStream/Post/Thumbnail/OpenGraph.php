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
		$allPatterns = array();
		$allPatterns[] = '#property=[\"\']og:image[\"\'].*content=[\"\']([^\"\']+)[\"\']#m';
		$allPatterns[] = '#content=[\"\']([^\"\']+)[\"\'].*property=[\"\']og:image[\"\']#m';

		return $this->analyse($allPatterns);
	}
}