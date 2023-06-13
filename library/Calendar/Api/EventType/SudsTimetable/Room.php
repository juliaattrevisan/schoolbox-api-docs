<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;

/**
 * Class Room
 *
 * Finds all the rooms currently in use between the prescribed timestamps.
 * This is used to populate the Resource Booking calendar to indicate when
 * a Room is currently in use so people don't try to book it at the same time.
 */
class Calendar_Api_EventType_SudsTimetable_Room extends Calendar_Api_EventType_Abstract
{
    /**
     * Seemingly arbitrarily hardcdoed colour
     */
    private const RESOURCE_BOOKING_COLOUR = '#c1e5ff';


    /**
     * Constructor.
     *
     * @param int                              $connectorId
     * @param \Schoolbox\User                  $user
     * @param \Schoolbox\User                  $viewer
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     * @param array                            $roomCodes
     */
    public function __construct(
        private int $connectorId,
        protected \Schoolbox\User $user,
        protected \Schoolbox\User $viewer,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private array $roomCodes = []
    ) {
        parent::__construct($this->connectorId, $user, $viewer);
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
     * @return GenericCollection<EventInterface>
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getEvents(int $startTimestamp, int $endTimestamp, ?array $allowedEventTypes = null): GenericCollection
    {
        /* Find all the SUDS Timetable records in use between $startTimestamp and $endTimestamp
         * which are in the locations specified by $this->roomCodes
         */
        $startAtADF = \Alaress_Date_Format::newFromTimestamp($startTimestamp);
        $finishAtADF = \Alaress_Date_Format::newFromTimestamp($endTimestamp);

        /* Find all the events which are occurring between the two timestamps
         * $startTimestamp, $endTimestamp.
         *
         * This includes all events, EXCEPT for ones which either:
         *   - finish before $startTimestamp
         *   - start after $finishTimestamp
         *
         * We presume that start <= finish.
         */
        $pb = $this->eventMapper->getPredicateBuilder()
            ->in('location', array_keys($this->roomCodes))
            ->rawWhere(
                'NOT (finish_at < :startTimestamp OR start_at > :endTimestamp)',
                [
                    'startTimestamp' => $startAtADF->getDatabase(),
                    'endTimestamp'   => $finishAtADF->getDatabase(),
                ]
            )
        ;

        $eventIds = $this->eventMapper->findIdsByPredicates($pb);

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
        $stack = new GenericCollection(EventInterface::class);

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


    public function getEvent($eventId): GenericCollection
    {
        return new GenericCollection(EventInterface::class);
    }
}
