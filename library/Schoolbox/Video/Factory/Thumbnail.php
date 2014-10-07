<?php
/**
 * Thumbnail
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Video\Factory;


class Thumbnail extends Base {

	/**
	 * Check perms
	 *
	 * @param $video
	 * @throws \Exception
	 */
	protected function permissionCheck($video) {
		if ($this->getGroupHandler()->getPermission($video->getFile()->getFolder()->getId()) < READ_ACCESS) {
			\SitePage::redirectToBrokenImageOfSize(64, 64, 403);
		}
	}
}