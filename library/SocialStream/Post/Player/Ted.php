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


class Ted extends Player {

	/**
	 * Fetch thumbnail
	 *
	 * @return mixed
	 */
	public function fetchThumbnail() {
		$matches = array();

		preg_match('#www\.ted\.com/talks/([a-zA-Z0-9_]+)\.html#', $this->getUrl(), $matches);

		if (count($matches)) {
			return "http://embed.ted.com/talks/{$matches[1]}.html";
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->fetchThumbnail();
		}
	}
}