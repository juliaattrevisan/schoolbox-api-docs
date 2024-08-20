<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Component\Eportfolio\FileListMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\IFile;

/**
 * Class Video
 *
 * @package Schoolbox\Video\Factory
 */
class Video extends Base
{
    /**
     * Check perms
     *
     * @param IFile $file
     * @return void
     * @throws Exception
     * @throws NotFound
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    protected function permissionCheck(IFile $file): void
    {
        /* We're not in Aura, sorry
         */
        $currentUser = $_SESSION['user'];

        /* If you are the owner
         * then you can view the content
         */
        if ($file->getOwner()->getId() === $currentUser->getId()) {
            return;
        }

        /* Check eportfolio permissions; if you can see a page that shows the video
         * (remembering that multiple eportfolio pages could show a given video)
         * then you can view the content
         */
        $fileListMapper = FileListMapper::getInstance();

        $allFiles = $fileListMapper->findByColumn('file_id', $file->getId());

        /* If you can view one of the pages that this file is shared on,
         * break out of here
         */
        foreach ($allFiles as $currentFile) {
            if ((new \Eportfolio_Authenticator($currentUser, $file->getOwner(), $currentFile->getPageId()))->canView()) {
                return;
            }
        }

        /* If the video was in a folder where you have read access,
         * then you can view the content
         */
        if ($this->getAccess()->getFolderAccess($currentUser, $file->getFolder()->getId()) >= UserAccess::ACCESS_READ) {
            return;
        }

        /* Every other use case: you get kicked out
         */
        throw new NotFound('You do not have permission to view this video.');
    }
}
