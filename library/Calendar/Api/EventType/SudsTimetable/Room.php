<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Class Room
 *
 * Finds all the rooms currently in use between the prescribed timestamps.
 * This is used to populate the Resource Booking calendar to indicate when
 * a Room is currently in use so people don't try to book it at the same time.
 */
class Calendar_Api_EventType_SudsTimetable_Room implements Calendar_Api_EventType_Interface
{
    /**
     * Seemingly arbitrarily hardcdoed colour
     */
    private const RESOURCE_BOOKING_COLOUR = '#c1e5ff';


    /**
     * Constructor.
     *
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     * @param array                            $roomCodes
     */
    public function __construct(
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private array $roomCodes = []
    ) {
    }


    /**
     * Find the usages of the $roomCodes between the timestamps given,
     * $startTimestamp and $endTimestamp
     *
     * $roomCodes is a tuple (room code, resource booking item instance id)
     *
     * If you don't provide any $roomCodes, it won't returns an empty set.
     *
     * @param int        $startTimestamp
     * @param int        $endTimestamp
     * @param array|null $allowedEventTypes
     * @return \Calendar\Event\Collection
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): \Calendar\Event\Collection
    {
        /* Find all the SUDS Timetable records in use between $startTimestamp and $endTimestamp
         */
        $statement = $this->eventMapper->getDatabase()->prepare("
            SELECT *
            FROM {$this->eventMapper->getTable()} se
            WHERE start_at >= :startTimestamp
            AND finish_at <= :endTimestamp
        ");

        $statement->execute([
            'startTimestamp' => \Alaress_Date_Format::newFromTimestamp($startTimestamp)->getDatabase(),
            'endTimestamp' => \Alaress_Date_Format::newFromTimestamp($endTimestamp)->getDatabase(),
        ]);

        /* Collect their Location value, name and class code
         * so we can push it into a \Calendar\Event\Type\SudsTimetable\Event instance
         */
        $eventIds = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $eventIds[] = $row['id'];
        }

        /* Then pop out the collection as a subscription that can be used
         * among all the other calendar subscription
         *
         * Also provide all the modal's usual info
         */
        $timetableEvents = $this->eventMapper->findWithCheapStaff($eventIds);

        /* Lowercase the room codes provided in constructor
         * so it's case insensitive matching
         */
        $resourceLookUp = array_change_key_case($this->roomCodes);

        /* Set up a collection to store all the events we can match
         */
        $stack = new \Calendar\Event\Collection();

        /** @var \Schoolbox\External\Event\Model $timetableEvent */
        foreach ($timetableEvents as $timetableEvent) {
            /* Check if the Location can case-insensitive match the $roomCodes arg
             * If it doesn't match, just skip it and continue
             */
            $resourceIdMatchingRoomCode = $resourceLookUp[strtolower($timetableEvent->getLocation())] ?? null;

            /* Couldn't match room code from the constructor args to
             * this $timetableEvent's location; skip it
             */
            if ($resourceIdMatchingRoomCode === null) {
                continue;
            }

            $instance = new \Calendar\Event\Type\SudsTimetable\Event();
            $instance->setEventId($timetableEvent->getId());
            $instance->setTitle($timetableEvent->getTitle());
            $instance->setDetail($timetableEvent->getDetail());
            $instance->setLocation($timetableEvent->getLocation());
            $instance->setCode($timetableEvent->getCode());
            $instance->setDay($timetableEvent->getDay());
            $instance->setPeriod($timetableEvent->getPeriod());
            $instance->setStartTime($timetableEvent->getStartAt());
            $instance->setEndTime($timetableEvent->getFinishAt());
            $instance->setAllDay(false);
            $instance->setFolder($timetableEvent->getFolder());
            $instance->setStaff($timetableEvent->getStaff());

            /* Specific meaning/usage in Resource Booking
             */
            $instance->setConnectorId(Calendar_Api_Config_Mapper::CONFIGURATION_NONE);
            $instance->setType(\Calendar\Event\Type\SudsTimetable\Event::CAMPUS_ACCESS);
            $instance->setPublishDate(null);
            $instance->setColour(self::RESOURCE_BOOKING_COLOUR);
            $instance->setResourceId($resourceIdMatchingRoomCode);

            $stack->add($instance);
        }

        return $stack;
    }


    public function getEvent($eventId)
    {
        // TODO: Implement getEvent() method.
        // TODO: I don't think this is even used anymore
    }
}
