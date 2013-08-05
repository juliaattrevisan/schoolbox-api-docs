<?php
/**
 * Vimeo
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Player;


class Vimeo extends Player {

	/**
	 *
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		preg_match('/vimeo.com\/([0-9]+)/', $this->getUrl(), $matches);

		if (count($matches)) {
			return "http://player.vimeo.com/video/{$matches[1]}?color=999999";
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}