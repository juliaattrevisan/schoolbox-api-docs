<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Event;

use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\Relation\Role;
use Schoolbox\ResourceBooking\Booking\Mapper as ResourceBookingMapper;
use Schoolbox\ResourceBooking\Booking\Model as ResourceBookingModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model;
use Schoolbox\RoleMapper;
use Schoolbox\Campus;
use Schoolbox\Mapper\Exception;

class Helper
{
    /**
     * Helper constructor.
     *
     * @param RoleMapper $roleMapper
     * @param FolderMapper $folderMapper
     * @param ResourceBookingMapper $resourceBookingMapper
     */
    public function __construct(
        private RoleMapper $roleMapper,
        private FolderMapper $folderMapper,
        private ResourceBookingMapper $resourceBookingMapper
    ) {
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getCampusCollection(Event $event): array
    {
        $result = [];

        /** @var Campus $campus */
        foreach ($event->getCampusCollection()->getAll() as $campus) {
            $result[] = [
                'id' => $campus->getId(),
                'name' => $campus->getName(),
                'extTimetableCode' => $campus->getExtTimetableCode(),
                'timetableDay' => $campus->getTimetableDay(),
            ];
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getRoleCollection(Event $event): array
    {
        $result = [];

        /** @var Role $role */
        foreach ($event->getRoleCollection()->getAll() as $role) {
            try {
                $result[] = $this->roleMapper->findById($role->getRoleId());
            } catch (Exception) {
                // noop
            }
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return string|null
     */
    public function getEventLevel(Event $event): ?string
    {
        $result = '';

        if ($event->isCampusLevel()) {
            $result = 'campus';
        } elseif ($event->isPersonalLevel()) {
            $result = 'personal';
        } elseif ($event->isGroupLevel()) {
            $result = 'group';
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getFolderCollection(Event $event): array
    {
        $result = [];

        $folders = $this->folderMapper->hydrateCollection($event->getFolderCollection());
        /** @var Model $folder */
        foreach ($folders as $folder) {
            $result[] = [
                'name' => $folder->getNameWithCodes(),
                'id'   => $folder->getId(),
            ];
        }

        return $result;
    }

    /**
     * @param Event $event
     * @return array
     */
    public function getResourceBookingsCollection(Event $event): array
    {
        // Get resource booking objects and ensure their linked event is hydrated.
        $resourceBookings = $this->resourceBookingMapper->findByEventId($event->getId());
        foreach ($resourceBookings as $resourceBooking) {
            $resourceBooking->setEvent($event);
        }

        $result = [];

        /** @var ResourceBookingModel $resourceBooking */
        foreach ($resourceBookings as $resourceBooking) {
            $result[] = $resourceBooking->jsonSerialize();
        }

        return $result;
    }
}
