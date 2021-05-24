<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video\Factory;

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\File\Model;

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
     * @param Model $file
     * @throws \Exception
     */
    protected function permissionCheck($file)
    {

        /* TODO: Don't throw root \Exception; should probably throw Unauthorised() or NotFound() instead.
         */
        if ($this->getAccess()->getFolderAccess($_SESSION['user'], $file->getFolder()->getId()) < UserAccess::ACCESS_READ) {
            throw new \Exception('You do not have permission to view this video.');
        }
    }
}
