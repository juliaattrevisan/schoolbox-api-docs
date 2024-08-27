<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Component\Eportfolio\FileListMapper;
use Schoolbox\Config\Loader;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\AbstractFile;

/**
 * Class Video
 *
 * @package Schoolbox\Video\Factory
 */
class Video extends Base
{
    /**
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return void
     * @throws NotFound
     */
    protected function permissionCheckOwner(AbstractFile $file): void
    {
        /* If you are the owner
         * then you can view the content
         */
        if ($file->getOwner()->getId() === $this->user->getId()) {
            return;
        }

        /* TODO Should supers be able to access this, as if they were the owner themselves?
         *
         * if ($this->user->isSuperuser()) {
         *     return;
         * }
         */

        /* Every other use case: you get kicked out
         */
        throw new NotFound('You do not have permission to view this video.');
    }


    /**
     * @inheritDoc
     *
     * @param AbstractFile $file
     * @return void
     * @throws NotFound
     */
    protected function permissionCheckEportfolioAccess(AbstractFile $file): void
    {
        /* If the eportfolio feature is globally enabled, dig a little deeper;
         * maybe this lets you see stuff. If it's disabled, skip all this costly
         * investigation entirely and keep going with other checks
         */
        $eportfolioEnabled = (bool)Loader::getInstance()->get('eportfolio_enabled');

        /* Check eportfolio permissions; if you can see a page that shows the video
         * (remembering that multiple eportfolio pages could show a given video)
         * then you can view the content
         */
        if ($eportfolioEnabled) {
            $fileListMapper = FileListMapper::getInstance();

            $files = $fileListMapper->findByColumn('file_id', $file->getId());

            /* If you can view one of the pages that this file is shared on,
             * break out of here
             */
            foreach ($files as $currentFile) {
                if ((new \Eportfolio_Authenticator($this->user, $file->getOwner(), $currentFile->getPageId()))->canView()) {
                    return;
                }
            }
        }

        /* Every other use case: you get kicked out
         */
        throw new NotFound('You do not have permission to view this video.');
    }


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
        /* If the video was in a folder where you have read access,
         * then you can view the content
         */
        if ($this->access->getFolderAccess($this->user, $file->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
            return;
        }

        /* Every other use case: you get kicked out
         */
        throw new NotFound('You do not have permission to view this video.');
    }
}
