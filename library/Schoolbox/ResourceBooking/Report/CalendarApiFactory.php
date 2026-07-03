<?php

/**
 * Builder for calendar APIs for a model.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\ResourceBooking\Report;

use Calendar_Api_Composite;
use Calendar_Api_EventType_Interface;
use External_TimetableInterface;
use Schoolbox\External\Event\Mapper;
use Schoolbox\ResourceBooking\Item;
use Schoolbox\User;
use Calendar_Api_EventType_ResourceBooking_Booking_ForItem;
use Calendar_Api_EventType_Timetable_Room;
use Calendar_Api_Config_Mapper;

class CalendarApiFactory
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var External_TimetableInterface
     */
    private $timetable;


    /**
     * Constructor
     *
     * @param User                        $user
     * @param External_TimetableInterface $timetable
     * @param Mapper                      $eventMapper
     */
    public function __construct(
        User $user,
        External_TimetableInterface $timetable,
        private \Schoolbox\External\Event\Mapper $eventMapper
    ) {
        $this->user      = $user;
        $this->timetable = $timetable;
    }

    /**
     * Creates a calendar API for this resource item.
     *
     * @param  Item\Model $resource
     * @return Calendar_Api_EventType_Interface
     */
    public function build(Item\Model $resource)
    {
        $api = new Calendar_Api_Composite();

        // Add the resource API
        $resourceApi = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
            Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
            $this->user,
            $this->user,
            [$resource->getId()]
        );
        $api->add($resourceApi);

        // Add the room code API if this resource has got a room code
        if ($roomCode = $resource->getRoomCode()) {
            $roomCodes = [$roomCode => $resource->getId()];

            /* Combine schedule and cyclic timetable's data sets
             */
            $api->add(new \Calendar_Api_EventType_SudsTimetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->user,
                $this->user,
                $this->eventMapper,
                $roomCodes
            ));

            $api->add(new Calendar_Api_EventType_Timetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->user,
                $this->user,
                $this->timetable,
                $roomCodes
            ));
        }
        return $api;
    }

    /**
     * Creates a calendar API for a list of resource items.
     *
     * @param Item\Model[] $resourceCollection
     * @param bool         $getTimetabledRooms
     * @param array        $status
     * @return Calendar_Api_EventType_Interface
     */
    public function buildFromResourceCollection($resourceCollection, $getTimetabledRooms = true, $status = [])
    {
        // Get the list of resource IDs and room codes
        $resourceIds = [];
        $roomCodes   = [];
        foreach ($resourceCollection as $resource) {
            if (!$resource instanceof Item\Model) {
                continue;
            }
            $resourceIds[] = $resource->getId();
            $roomCode      = trim((string)$resource->getRoomCode());
            if (strlen($roomCode) > 0) {
                $roomCodes[$roomCode] = $resource->getId();
            }
        }

        // Get the list of resource IDs and room codes
        $api = new Calendar_Api_Composite();
        if (!empty($resourceIds)) {
            $resourceApi = new \Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->user,
                $this->user,
                $resourceIds
            );

            if (!empty($status)) {
                $resourceApi->setStatus($status);
            }

            $api->add($resourceApi);
        }
        if ($getTimetabledRooms && !empty($roomCodes)) {
            /* Combine schedule and cyclic timetable's data sets
             */
            $api->add(new \Calendar_Api_EventType_SudsTimetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->user,
                $this->user,
                $this->eventMapper,
                $roomCodes
            ));

            $api->add(new Calendar_Api_EventType_Timetable_Room(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $this->user,
                $this->user,
                $this->timetable,
                $roomCodes
            ));
        }
        return $api;
    }
}
