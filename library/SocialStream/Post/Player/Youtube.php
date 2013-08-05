<?php
/**
 * Youtube
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Player;


class Youtube extends Player {

	/**
	 *
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		preg_match('/v=([a-zA-Z0-9_]+)&?/', $this->getUrl(), $matches);

		if (count($matches)) {
			return "//www.youtube.com/embed/{$matches[1]}";
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}