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
     */
    public function __construct(User $user, External_TimetableInterface $timetable)
    {
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
            /* TODO: Inject this
             */
            $config = \Schoolbox\Config\Loader::getInstance();
            $eventMapper = \Schoolbox\External\Event\Mapper::getInstance();

            $roomCodes = [$roomCode => $resource->getId()];

            if (in_array($config->get('schooldata_import_endpoint_host'), \Schoolbox\External\Importer\Endpoint\Factory::CYCLIC_GRID, true)) {
                $timetableApi = new Calendar_Api_EventType_Timetable_Room(
                    Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                    $this->user,
                    $this->user,
                    $this->timetable,
                    $roomCodes
                );
            } else {
                $timetableApi = new \Calendar_Api_EventType_SudsTimetable_Room($eventMapper, $roomCodes);
            }

            $api->add($timetableApi);
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
            $roomCode      = trim($resource->getRoomCode());
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
            /* TODO: Inject this
             */
            $config = \Schoolbox\Config\Loader::getInstance();
            $eventMapper = \Schoolbox\External\Event\Mapper::getInstance();

            if (in_array($config->get('schooldata_import_endpoint_host'), \Schoolbox\External\Importer\Endpoint\Factory::CYCLIC_GRID, true)) {
                $timetableApi = new Calendar_Api_EventType_Timetable_Room(
                    Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                    $this->user,
                    $this->user,
                    $this->timetable,
                    $roomCodes
                );
            } else {
                $timetableApi = new \Calendar_Api_EventType_SudsTimetable_Room($eventMapper, $roomCodes);
            }

            $api->add($timetableApi);
        }
        return $api;
    }
}
