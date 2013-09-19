<?php
/**
 * Null
 *
 * Used when no meta tags/opengraph policy used
 * LIKE ON GOOGLE NEXUS.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace SocialStream\Post\Metadata;


class Null extends Metadata {

	/**
	 * Fetch information out of the meta tags
	 *
	 * @return mixed
	 */
	public function fetchMetadata() {
		return $this;
	}
}