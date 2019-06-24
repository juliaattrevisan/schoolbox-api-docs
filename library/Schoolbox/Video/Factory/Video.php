<?php
/**
 * Video
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Video\Factory;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Model;

class Video extends Base {

	/**
	 * Check perms
	 *
	 * @param Model $file
	 * @throws \Exception
	 */
	protected function permissionCheck($file) {
		if ($this->getAccess()->getFolderAccess($_SESSION['user'], $file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
			throw new \Exception('You do not have permission to view this video.');
		}
	}
}
