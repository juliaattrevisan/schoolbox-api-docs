<?php
/**
 * Visibility
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;


use Schoolbox\User;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;

class Visibility
{
    /**
     * @var Access
     */
    private $access;

    /**
     * Visibility constructor.
     *
     * @param Access $access
     */
    public function __construct(Access $access)
    {
        $this->setAccess($access);
    }


    /**
     * Determines if the specified user can see an IAssessable.
     *
     * * Staff    - can see anytime
     * * Students - can only see after publish date
     *
     * Staff is considered any user with WRITE+ perm to folder
     * Student is considered any user with READ perm to folder
     *
     * @param IAssessable $assessment
     * @param User $user
     * @return bool
     */
    public function isVisibleToUser(IAssessable $assessment, User $user)
    {
        $folderAccessLevel = $this->getAccess()->getFolderAccess($user, [$assessment->getBaseContext()->getFolder()->getId()]);

        if ($folderAccessLevel >= UserAccess::ACCESS_WRITE || ($folderAccessLevel == UserAccess::ACCESS_READ && $assessment->isPublished())) {
            return true;
        }

        return false;
    }


    /* Getters/Setters
     */

    /**
     * @return Access
     */
    private function getAccess()
    {
        return $this->access;
    }

    /**
     * @param Access $access
     * @return $this
     */
    private function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }
}
