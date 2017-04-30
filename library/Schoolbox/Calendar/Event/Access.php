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

use Schoolbox\Campus;
use Schoolbox\User;
use Schoolbox\Collection;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Relation\Campus as RelationCampus;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

class Access
{
    /**
     * Checks if a given user has permission to write or modify an event
     *
     * @param User $user
     * @param Event $event
     * @param array|Collection $groups
     * @param Campus[] $campuses
     *
     * @return bool
     */
    public function checkAccess(User $user, Event $event, $groups = [], $campuses = [])
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

            /* Get list of calendar permissions this user has
             */
            $accessList = $user->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR);

            foreach ($accessList as $currentAccess) {
                /** @var UserAccess $currentAccess */

                if ($currentAccess->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
                    /* Loop through campuses for the event and check if one
                     * matches with this access
                     */
                    foreach ($campuses as $campus) {

                        if ($campus instanceof Campus) {
                            // CampusCollection is a collection of Schoolbox\Campus instances
                            // when importing events from csv or vcal
                            $campusId = $campus->getId();
                        } elseif ($campus instanceof RelationCampus) {
                            // CampusCollection is a collection of Calendar\Relation\Campus instances
                            // when modifying a series of events
                            $campusId = $campus->getCampusId();
                        } else {
                            // CampusCollection is a collection of campus ID's
                            // when editing a single event
                            $campusId = $campus;
                        }

                        if ($campusId == $currentAccess->getServiceIdentifier()) {
                            return true;
                        }
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

                try {
                    $access = $userAccessMapper->getAccess($user, $groupId);
                    /* TODO: write access should only be able to edit if they
                     * are author or owner. The logic to show the modify button
                     * will need to change first
                     */
                    if ($access->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
                        return true;
                    }
                } catch (\Schoolbox\Mapper\Exception $e) {
                    /* User didn't have access for this group but might
                     * for another one in this loop
                     */
                }


            }
        }


        /* User didn't pass any of the checks, deny!
         */
        return false;
    }

}
