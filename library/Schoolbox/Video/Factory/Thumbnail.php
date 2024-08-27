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
     * @return void
     * @throws Exception
     * @throws NotFound
     */
    protected function permissionCheckFolder(AbstractFile $file): void
    {
        if ($this->access->getFolderAccess($this->user, $file->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
            return;
        }

        \SitePage::redirectToBrokenImageOfSize(64, 64, 403);
    }


    /**
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return void
     */
    protected function permissionCheckOwner(AbstractFile $file): void
    {
        /* Not used in this context.
         */
    }


    /**
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return void
     */
    protected function permissionCheckEportfolioAccess(AbstractFile $file): void
    {
        /* Not used in this context.
         */
    }
}
