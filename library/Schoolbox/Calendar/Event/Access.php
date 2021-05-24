<?php

/**
 * Calendar Event Access
 * TODO: Roll this into Event/Controller once it exists
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Event;

use InvalidArgumentException;
use LogicException;
use Schoolbox\Campus;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Schoolbox\Calendar\Event;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;
use UnexpectedValueException;

/**
 * Class Access
 *
 * This class is used for perm checking for $effector changing $event;
 *   - checks whether $effector can access $event to view it,
 *   - and whether $effector can apply settings to $event
 *
 * This could, perhaps, somehow/someway be moved into an Auth rule but it needs reuse in different ways
 * which I found precluded the Auth rule intention
 *
 * @package Schoolbox\Calendar\Event
 */
class Access
{

    /**
     * @var FolderAccess
     */
    private $folderAccess;


    /**
     * Access constructor.
     *
     * @param FolderAccess $folderAccess
     */
    public function __construct(FolderAccess $folderAccess)
    {
        $this->folderAccess = $folderAccess;
    }


    /**
     * Takes an existing event object, and uses checkDestination below to check access for a given user
     *
     * @param User  $effector
     * @param Event $event
     * @return bool
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    public function checkExisting(User $effector, Event $event): bool
    {
        /* Superusers can always make changes
         */
        if ($effector->isSuperuser()) {
            return true;
        }

        // Split groups into an array of ints, if there are any
        if ($event->getFolderCollection()) {
            $groups = $event->getFolderCollection()->getPropertyValues('id');
        } else {
            $groups = [];
        }

        /* Split campuses into an array of ints, if there are any
         *
         * $event->getCampusCollection() can return one of three possible types of data:
         * a collection of Relation\Campus, a collection of Campus, a collection of int
         *
         * Mine them for their most useful part
         */
        $campuses = [];

        if ($event->getCampusCollection()) {
            foreach ($event->getCampusCollection() as $campus) {
                if ($campus instanceof Campus) {
                    $id = $campus->getId();
                } elseif ($campus instanceof \Schoolbox\Calendar\Relation\Campus) {
                    $id = $campus->getCampusId();
                } else {
                    $id = $campus;
                }

                $campuses[] = (int)$id;
            }
        } else {
            $campuses = [];
        }

        return $this->checkDestination(
            $effector,
            $event->getAccess(),
            $event->getAuthor(),
            $event->getOwner(),
            $groups,
            $campuses
        );
    }


    /**
     * Checks if a given user has permission to write or modify an event
     *
     * @param User  $effector
     * @param int   $accessLevel
     * @param User  $author
     * @param User  $owner
     * @param int[] $folders
     * @param int[] $campuses
     * @return bool
     * @throws InvalidArgumentException
     * @throws LogicException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    public function checkDestination(
        User $effector,
        int $accessLevel,
        User $author,
        User $owner,
        array $folders = [],
        array $campuses = []
    ): bool {

        // Superuser passes immediately
        if ($effector->isSuperuser()) {
            return true;
        }

        /* If event is on a personal level:
         * Users may edit events of which they are the owner *and* author
         * Staff may edit events of which they they are the owner *or* author
         * Parents may edit events of their child, of which they are the author
         */
        if ($accessLevel === Event::ACCESS_PERSONAL) {
            if ($owner->getRole()->isStudent() && $effector->getRole()->isParent()) {
                $isParentOfOwner = $owner->loadParents()->isChildOf($effector);
            } else {
                $isParentOfOwner = false;
            }

            $isStaff = $effector->getRole()->isStaff();
            $isOwner = (int)$effector->getId() === (int)$owner->getId();
            $isAuthor = (int)$effector->getId() === (int)$author->getId();

            if (($isOwner && $isAuthor) || ($isAuthor && $isParentOfOwner) || ($isStaff && ($isOwner || $isAuthor))) {
                return true;
            }
        }

        /* If the event is on a group level:
         * check if the user is a group manager (write access to the folder)
         */
        if ($accessLevel === Event::ACCESS_GROUP) {
            $minFolderAccess = $this->folderAccess->getMinFolderAccess($effector, $folders);

            if ($minFolderAccess >= UserAccess::ACCESS_WRITE) {
                return true;
            }
        }

        /* If the event is on a campus level:
         * check if this user is a calendar moderator for one of the campuses selected
         */
        if ($accessLevel === Event::ACCESS_CAMPUS) {
            // Get list of calendar permissions the effector has
            $accessList = $effector->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR);

            foreach ($accessList as $currentAccess) {
                if ($currentAccess->getAccessLevel() >= UserAccess::ACCESS_WRITE) {
                    // Loop through campuses for the event and check if one matches with this access
                    foreach ($campuses as $campusId) {
                        if ($campusId === (int)$currentAccess->getServiceIdentifier()) {
                            return true;
                        }
                    }
                }
            }
        }

        // User didn't pass any of the checks, deny!
        return false;
    }
}
