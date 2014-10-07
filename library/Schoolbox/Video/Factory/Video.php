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


use Schoolbox\Video\ResourceVideo;

class Video extends Base {

	/**
	 * Check perms
	 *
	 * @param ResourceVideo $video
	 * @throws \Exception
	 */
	protected function permissionCheck($video) {
		if ($this->getGroupHandler()->getPermission($video->getFile()->getFolder()->getId()) < READ_ACCESS) {
			throw new \Exception('You do not have permission to view this video.');
		}
	}
}