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


use Schoolbox\Resource\File\Model;

class Video extends Base {

	/**
	 * Check perms
	 *
	 * @param Model $file
	 * @throws \Exception
	 */
	protected function permissionCheck($file) {
		if ($this->getGroupHandler()->getPermission($file->getFolder()->getId()) < READ_ACCESS) {
			throw new \Exception('You do not have permission to view this video.');
		}
	}
}