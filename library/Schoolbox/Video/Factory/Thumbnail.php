<?php
/**
 * Thumbnail
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Video\Factory;

use Schoolbox\Permission\Access\UserAccess;

class Thumbnail extends Base {

	/**
	 * Check perms
	 *
	 * @param $file
	 * @throws \Exception
	 */
	protected function permissionCheck($file) {
		if ($this->getAccess()->getFolderAccess($_SESSION['user'], $file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
			\SitePage::redirectToBrokenImageOfSize(64, 64, 403);
		}
	}
}
