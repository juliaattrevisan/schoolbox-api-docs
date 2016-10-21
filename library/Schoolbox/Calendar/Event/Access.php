<?php
/**
 * Calendar Event Access
 * TODO: Roll this into Event/Controller once it exists
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar\Event;

use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Calendar\Event;
use Schoolbox\Permission\Access\UserAccess;

class Access
{
    /**
     * Checks if a given user has permission to modify a given event
     *
     * @param User $user
     * @param Event $event
     *
     * @return bool
     */
    public function checkAccess(User $user, Event $event)
    {
        $userMapper = new UserMapper();

        /* Check superuser
         */
        $isSuperuser = $user->isSuperuser();


        /* Check if user is parent of the owner
         */
        $event->getOwner()->loadParents();
        $isParentOfOwner = $user->isParentOf($event->getOwner());


        /* If the event is on a campus level, check if this user
         * is a calendar moderator for one of the campuses selected
         */
        $isModerator = false;

        if ($event->isCampusLevel()) {

            $accessList = $user->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR)->filterBy('accessLevel', UserAccess::ACCESS_FULL);

            /** @var UserAccess $currentAccess */
            foreach ($accessList as $currentAccess) {

                // Loop through the campuses selected for the event
                foreach ($event->getCampusCollection() as $currentCampus) {
                    if ($currentCampus->getCampusId() == $currentAccess->getServiceIdentifier()) {
                        $isModerator = true;
                        break;
                    }
                }
            }
        }


        /* If the event is on a group level, check if the user is a group manager
         */
        $isGroupManager = false;

        if ($event->isGroupLevel()) {

            /* Loop through the groups selected for this event
             */
            foreach ($event->getCategoryCollection() as $category) {

                /* Loop through the managers for this group and check for $user
                 */
                $groupManagers = $userMapper->findByAccess(UserAccess::SERVICE_HOMEPAGE, UserAccess::ACCESS_WRITE, $category->getCategoryId());
                foreach ($groupManagers as $groupManager) {
                    if ($groupManager->getId() === $user->getId()) {
                        $isGroupManager = true;
                        break;
                    }
                }
            }
        }


        /* For Personal events, owners only
         */
        $isOwner = false;

        if ($event->isPersonalLevel()) {
            if ($user->getId() == $event->getOwner()->getId()) {
                $isOwner = true;
            }
        }


        /* Roll through the checks, return true if the user passed any
         */
        if ($isSuperuser || $isParentOfOwner || $isModerator || $isGroupManager || $isOwner) {
            return true;
        }

        return false;

    }

}