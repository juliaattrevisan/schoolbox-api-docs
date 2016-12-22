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
     * Determines whether this upcoming IAssessable is visible to $user
     * at its most basic level. For students, the Publish Date must be
     * in the past; for Staff, it is always visible and does not require
     * a past Publish Date
     *
     * IAssessables won't appear if their due dates are in the past
     *
     * @param IAssessable $assessment
     * @param User $user
     * @return bool
     */
    public function isUpcomingVisibleToUser(IAssessable $assessment, User $user)
    {
        /* Closed IAssessables don't show
         */
        if ($assessment->getCloseAt()->isPast()) {
            return false;
        }

        /* Folder perms
         */
        $folderAccessLevel = Access::getInstance()->getFolderAccess($user, [$assessment->getContext()->getFolder()->getId()]);

        if ($folderAccessLevel >= UserAccess::ACCESS_WRITE) {
            return true;
        }
        else if ($folderAccessLevel == UserAccess::ACCESS_READ) {

            /* Unpublished IAssessables don't show
             */
            if ($assessment->getPublishAt()->isFuture()) {
                return false;
            }

            /* Decide whether $user can create a response to this IAssessable
             */
            return $assessment->canCreateResponse($user);
        }

        return false;
    }
}