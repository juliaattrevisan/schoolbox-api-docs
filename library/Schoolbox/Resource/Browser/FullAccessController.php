<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser;


use Schoolbox\Mapper;
use Schoolbox\Permission\Access\UserAccess;

class FullAccessController extends AbstractController
{

    /**
     * @param int $folderId
     * @return int
     */
    protected function getAccess($folderId)
    {
        // you'll always have access to the folders
        return UserAccess::ACCESS_FULL;
    }

}
