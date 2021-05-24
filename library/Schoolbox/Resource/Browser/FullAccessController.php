<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Browser;

use Schoolbox\Permission\Access\UserAccess;

class FullAccessController extends AbstractController
{

    /**
     * Controller constructor.
     */
    public function __construct()
    {

        /* Access to the picker shouldn't cause it to enter the session history stack
         * which opens the opportunity to be redirected back here inadvertently, full-screen
         */
        $this->setAllowBackToTheFuture(false);
    }


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
