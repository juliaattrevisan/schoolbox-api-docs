<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
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
     * @throws NotFound
     */
    protected function permissionCheckFolder(AbstractFile $file): bool
    {
        if ($this->getAccess()->getFolderAccess($_SESSION['user'], $file->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
            return true;
        }

        \SitePage::redirectToBrokenImageOfSize(64, 64, 403);
    }


    /**
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return bool
     */
    protected function permissionCheckOwner(AbstractFile $file): bool
    {
        /* Not used in this context.
         */
        return true;
    }


    /**
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return bool
     */
    protected function permissionCheckEportfolioAccess(AbstractFile $file): bool
    {
        /* Not used in this context.
         */
        return true;
    }
}
