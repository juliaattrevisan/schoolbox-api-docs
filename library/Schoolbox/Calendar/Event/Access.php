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
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Relation\Campus as RelationCampus;
use Schoolbox\Calendar\Relation\Category as RelationCategory;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;

class Access
{

    /**
     * Takes an existing event object, and uses checkDestination below to check access for a given user
     *
     * @param User $effector
     * @param Event $event
     * @return bool
     */
    public function checkExisting(User $effector, Event $event)
    {
        // Split groups into an array of ints, if there are any
        if ($event->getCategoryCollection()) {
            $groups = $event->getCategoryCollection()->getColumnValues('categoryId');
        } else {
            $groups = [];
        }

        // Split campuses into an array of ints, if there are any
        if ($event->getCampusCollection()) {
            $campus = $event->getCampusCollection()->getColumnValues('campusId');
        } else {
            $campus = [];
        }

        return $this->checkDestination(
            $effector,
            $event->getAccess(),
            $event->getAuthor(),
            $event->getOwner(),
            $groups,
            $campus
        );
    }


    /**
     * Checks if a given user has permission to write or modify an event
     *
     * @param User $effector
     * @param int $accessLevel
     * @param User $author
     * @param User $owner
     * @param int[] $groups
     * @param int[] $campuses
     * @return bool
     */
    public function checkDestination(User $effector, $accessLevel, User $author, User $owner, array $groups = [], array $campuses = [])
    {
        /* Check superuser
         */
        if ($effector->isSuperuser()) {
            return true;
        }


        /* If event is on a personal level, check effector is the owner or the author
         */
        if ($accessLevel == Event::ACCESS_PERSONAL) {
            if ($effector->getId() == $owner->getId() || $effector->getId() == $author->getId()) {
                return true;
            }
        }


        /* If the event is on a campus level, check if this user
         * is a calendar moderator for one of the campuses selected
         */
        if ($accessLevel == Event::ACCESS_CAMPUS) {

            /* Get list of calendar permissions the effector has
             */
            $accessList = $effector->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR);

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
        if ($accessLevel == Event::ACCESS_GROUP) {

            /* Loop through the groups selected for this event
             */
            $groupIds = [];
            foreach ($groups as $group) {

                if ($group instanceof RelationCategory) {
                    $groupId = $group->getCategoryId();
                } else {
                    $groupId = $group;
                }
                $groupIds[] = $groupId;
            }

            if (FolderAccess::getInstance()->getMinFolderAccess($effector, $groupIds) >= UserAccess::ACCESS_WRITE) {
                return true;
            }
        }


        /* User didn't pass any of the checks, deny!
         */
        return false;
    }

}
