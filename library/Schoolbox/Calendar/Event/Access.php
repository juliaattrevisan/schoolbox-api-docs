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
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;

class Access
{
    /** @var FolderAccess */
    private $folderAccess;

    /**
     * Access constructor.
     * @param FolderAccess $folderAccess
     */
    public function __construct(FolderAccess $folderAccess)
    {
        $this->setFolderAccess($folderAccess);
    }

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
            $campus = $event->getCampusCollection()->getColumnValues('id');
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
    public function checkDestination(
        User $effector,
        $accessLevel,
        User $author,
        User $owner,
        array $groups = [],
        array $campuses = []
    ) {
        // Superuser passes immediately
        if ($effector->isSuperuser()) {
            return true;
        }

        // If event is on a personal level:
        // Users may edit events of which they are the owner *and* author
        // Staff may edit events of which they they are the owner *or* author
        // Parents may edit events of their child, of which they are the author
        if ($accessLevel == Event::ACCESS_PERSONAL) {
            if ($owner->getRole()->isStudent() && $effector->getRole()->isParent()) {
                $isParentOfOwner = $owner->isChildOf($effector);
            } else {
                $isParentOfOwner = false;
            }

            $isStaff = $effector->getRole()->isStaff();
            $isOwner = $effector->getId() == $owner->getId();
            $isAuthor = $effector->getId() == $author->getId();

            if (($isOwner && $isAuthor) || ($isAuthor && $isParentOfOwner) || ($isStaff && ($isOwner || $isAuthor))) {
                return true;
            }
        }

        // If the event is on a campus level, check if this user is a calendar moderator for one of the campuses
        // selected
        if ($accessLevel == Event::ACCESS_CAMPUS) {
            // Get list of calendar permissions the effector has
            $accessList = $effector->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR);

            foreach ($accessList as $currentAccess) {
                /** @var UserAccess $currentAccess */

                if ($currentAccess->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
                    // Loop through campuses for the event and check if one matches with this access
                    foreach ($campuses as $campusId) {
                        if ($campusId == $currentAccess->getServiceIdentifier()) {
                            return true;
                        }
                    }
                }
            }
        }

        // If the event is on a group level, check if the user is a group manager
        if ($accessLevel == Event::ACCESS_GROUP) {
            $minFolderAccess = $this->getFolderAccess()->getMinFolderAccess($effector, $groups);
            if ($minFolderAccess >= UserAccess::ACCESS_WRITE) {
                return true;
            }
        }

        // User didn't pass any of the checks, deny!
        return false;
    }

    /**
     * @return FolderAccess
     */
    private function getFolderAccess()
    {
        return $this->folderAccess;
    }

    /**
     * @param $folderAccess
     * @return $this
     */
    private function setFolderAccess($folderAccess)
    {
        $this->folderAccess = $folderAccess;
        return $this;
    }

}
