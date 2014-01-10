<?php
/**
 * Video
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Video\Factory;


class Video extends Factory {

	/**
	 * Check perms
	 *
	 * @param $video
	 * @throws \Exception
	 */
	protected function permissionCheck($video) {
		if ($this->getGroupHandler()->getPermission($video->getFile()->getFolder()) < READ_ACCESS) {
			throw new \Exception('You do not have permission to view this video.');
		}
	}
}