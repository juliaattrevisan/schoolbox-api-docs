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
     * * Instructor  - can see anytime (so long as not past close and we are hiding closed)
     * * Learner     - can only see after publish date
     *
     * Instructor - is considered any user with WRITE+ perm to folder
     * Learner    - is considered any user with READ perm to folder
     *
     * @param IAssessable $assessment
     * @param User        $user
     * @param bool        $showClosed Should we hide assessments after their close date?
     * @return bool
     */
    public function isVisibleToUser(IAssessable $assessment, User $user, $showClosed = true)
    {
        $folderAccessLevel = $this->getAccess()->getFolderAccess($user, [$assessment->getBaseContext()->getFolder()->getId()]);

        // Are we hiding assessments past their close date and we are past the close date ?
        if(!$showClosed && $assessment->isPastClose()){
            return false;
        }

        // Is user an instructor?
        if ($folderAccessLevel >= UserAccess::ACCESS_WRITE) {
            return true;
        }
        // Else is user a learner and assessment is published ?
        elseif($folderAccessLevel == UserAccess::ACCESS_READ && $assessment->isPublished()){
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
