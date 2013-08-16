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


class ArbitraryFile extends Thumbnail {

	/**
	 * Some kind of resource that is not an image
	 * such as a pdf, a docx, etc.
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {

		/* If it has at least the right extension, download it directly
		 */
		$mapper = new \Schoolbox\File\MimetypeMapper();

		try {
			$icon = $mapper->getIconForFilename($this->getUrl());
		}
		catch(\Schoolbox\File\Exception $e) {
			$icon = 'default icon';
		}

		return $icon;
	}
}