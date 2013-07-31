<?php
/**
 * Direct
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Thumbnail;


class Direct extends Thumbnail {

	/**
	 * Find itemprop='image'
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {

		/* If it has at least the right extension, download it directly
		 */
		if ($this->getFileType()->isImage()) {

			/* TODO: Check header types via curl
			 * rather than just rely on the file extension
			 */
			
			return $this->getUrl();
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}