<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Schoolbox\Mapper\Exception;
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
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return bool
     * @throws Exception
     */
    protected function permissionCheckFolder(AbstractFile $file): bool
    {
        if ($this->access->getFolderAccess($this->user, $file->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
            return true;
        }

        \SitePage::redirectToBrokenImageOfSize(64, 64, 403);
    }
}
