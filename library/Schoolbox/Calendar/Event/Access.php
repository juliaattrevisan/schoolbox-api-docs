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
use Schoolbox\Calendar\Event;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class Access
{
    /**
     * Checks if a given user has permission to write an event
     *
     * @param User $user
     * @param Event $event
     * @param $groups
     * @param $campuses
     *
     * @return bool
     */
    public function checkAccess(User $user, Event $event, $groups, $campuses)
    {
        /* Check superuser
         */
        if ($user->isSuperuser()) {
            return true;
        }

        /* If event is on a personal level, check user is the owner or the author
         */
        if ($event->isPersonalLevel()) {
            if ($user->getId() == $event->getOwner()->getId() || $user->getId() == $event->getAuthor()->getId()) {
                return true;
            }
        }

        /* If the event is on a campus level, check if this user
         * is a calendar moderator for one of the campuses selected
         */
        if ($event->isCampusLevel()) {
            $accessList = $user->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR)->filterBy('accessLevel', UserAccess::ACCESS_FULL);

            foreach ($accessList as $currentAccess) {
                /** @var UserAccess $currentAccess */
                // Loop through the campuses selected for the event
                foreach ($campuses as $campus) {
                    if (is_object($campus)) {
                        $campusId = $campus->getCampusId();
                    } else {
                        $campusId = $campus;
                    }

                    if ($campusId == $currentAccess->getServiceIdentifier()) {
                        return true;
                    }
                }
            }
        }

        /* If the event is on a group level, check if the user is a group manager
         */
        if ($event->isGroupLevel()) {
            $userAccessMapper = UserAccessMapper::getInstance();

            /* Loop through the groups selected for this event
             */
            foreach ($groups as $group) {

                if (is_object($group)) {
                    $groupId = $group->getCategoryId();
                } else {
                    $groupId = $group;
                }

                $access = $userAccessMapper->getAccess($user, $groupId);
                if ($access->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
                    return true;
                }
            }
        }

        /* User didn't pass any of the checks, deny!
         */
        return false;
    }

}