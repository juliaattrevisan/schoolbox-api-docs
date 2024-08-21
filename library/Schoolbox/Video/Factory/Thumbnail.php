<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\AbstractFile;

/**
 * Class Thumbnail
 *
 * @package Schoolbox\Video\Factory
 */
class Thumbnail extends Base
{
    /**
     * Check perms
     *
     * @param AbstractFile $file
     * @return void
     * @throws \Exception
     */
    protected function permissionCheck(AbstractFile $file, bool $isMyFile = false): void
    {
        if ($this->getAccess()->getFolderAccess($_SESSION['user'], $file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
            \SitePage::redirectToBrokenImageOfSize(64, 64, 403);
        }
    }
}
