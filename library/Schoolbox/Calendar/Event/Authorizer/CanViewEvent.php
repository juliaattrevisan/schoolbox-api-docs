<?php

/**
 * This authorization method checks whether a user can view an event.
 *
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar\Event\Authorizer;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\Campus;
use Schoolbox\IConfigLoader;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

class CanViewEvent implements IAuthorizationRule
{
    /**
     * @param IConfigLoader $config
     */
    public function __construct(private IConfigLoader $config)
    {
    }

    /**
     * @param array     $params
     * @param User|null $user
     * @throws \Schoolbox\Mapper\Exception
     */
    public function authorize(array $params = [], ?User $user = null): void
    {
        if (!array_key_exists('event', $params)) {
            throw new \InvalidArgumentException("Expected array key 'event' does not exist.");
        }

        /** @var Event $event */
        $event = $params['event'];

        (new \Schoolbox\External\User(
            $this->config->get('external_type'),
            new \External_Class(),
            new \External_User(),
            UserMapper::getInstance()
        ))->loadChildren([$user]);

        /**
         * Personal Event: owner, parent of owner, staff, or invited guest, can see event
         * Group: READ+ members of group can see event
         * Campus: user can see event if in campus and roles
         */
        if ($event->isPersonalLevel()) {
            $isStaff        = $user->getRole()->isStaff();
            $isOwner        = $event->getOwner()->getId() === $user->getId();
            $isParentOfUser = $user->isParentOf($event->getAuthor());

            /* I suppose if you're on the invited list,
             * you should be able to see it
             */
            $isAttendee = (Event\Attendance\Mapper::getInstance())->countByColumnList(
                [
                    'event_id' => $event->getId(),
                    'user_id'  => $user->getId(),
                ]
            );

            if ($isOwner || $isStaff || $isParentOfUser || $isAttendee) {
                return;
            }
        } elseif ($event->isGroupLevel()) {
            /** @var Folder $folder */
            foreach ($event->getFolderCollection()->getAll() as $folder) {
                if (Access::getInstance()->getFolderAccess($user, $folder->getId()) >= UserAccess::ACCESS_READ) {
                    return;
                }
            }
        } elseif ($event->isCampusLevel()) {
            $inRole   = false;
            $inCampus = false;

            foreach ($user->getAccessList()->filterBy('service', UserAccess::SERVICE_CALENDAR) as $access) {
                if ($access->getAccessLevel() >= UserAccess::ACCESS_READ) {
                    /** @var Campus $campus */
                    foreach ($event->getCampusCollection()->getAll() as $campus) {
                        if ($campus->getId() === $access->getServiceIdentifier()) {
                            return;
                        }
                    }
                }
            }

            /** @var Campus $campus */
            foreach ($event->getCampusCollection() as $campus) {
                if ($user->isInCampus($campus)) {
                    $inCampus = true;
                    break;
                }
            }

            if ($event->getRoleCollection()->getBy('roleId', $user->getRole()->getId()) instanceof Role) {
                $inRole = true;
            }

            if ($inCampus && $inRole) {
                return;
            }
        }

        throw new Unauthorized();
    }
}
