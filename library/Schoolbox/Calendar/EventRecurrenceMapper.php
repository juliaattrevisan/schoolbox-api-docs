<?php

/**
 * EventRecurrenceMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Calendar;

use Alaress\Date\Interval;
use Alaress_Date_Format;
use DateTime;
use EOTC\EventMetadata;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Traversable;

class EventRecurrenceMapper
{

    const EFFICACY_SELF = 'self';
    const EFFICACY_SERIES = 'series';

    /**
     * An instance of \Schoolbox\Calendar\EventMapper
     *
     * @var EventMapper
     */
    protected $eventMapper = null;

    /**
     * eventId corresponding to the first instance in a series
     * Typically used to redirect back to the first instance of
     * an event series rather than the last instance
     *
     * @var int
     */
    protected $firstEventIdInSeries = 0;

    /**
     * Constructor
     *
     * @param EventMapper      $eventMapper
     */
    public function __construct($eventMapper)
    {
        $this->eventMapper      = $eventMapper;
    }

    /**
     * Get the series recurrence settings
     *
     * @param Event $event
     * @return array
     */
    public function getSeriesRecurrence($event)
    {
        $series = $this->eventMapper->getAllInSeries($event);

        if (count($series) <= 1) {
            return array();
        }

        /* This collection was indexed by event id
         */
        $series->reindex();

        /** @var Alaress_Date_Format $dateFirst */
        $dateFirst = $series->first()->getStartDateTime();
        $dateFirst->startOfDay();
        /** @var Alaress_Date_Format $dateNext */
        $dateNext = $series->next()->current()->getStartDateTime();
        $dateNext->startOfDay();
        /** @var Alaress_Date_Format $dateLast */
        $dateLast = $series->last()->getStartDateTime();
        $dateLast->startOfDay();

        //$diff = (int)(($dateNext->getUnixTimestamp() - $dateFirst->getUnixTimestamp()) / 86400);
        $diff = $dateFirst->diffInDays($dateNext);

        $recurMap = array(
            1 => 'daily',
            7 => 'weekly',
            14 => 'fortnightly',
            28 => 'monthly',
            29 => 'monthly',
            30 => 'monthly',
            31 => 'monthly',
        );

        if (isset($recurMap[$diff])) {
            return array(
                'num' => count($series),
                'freq' => $recurMap[$diff],
                'start' => $dateFirst,
                'end' => $dateLast
            );
        }

        return array(
            'num' => count($series),
            'freq' => 'periodically',
            'start' => $dateFirst,
            'end' => $dateLast
        );
    }

    /**
     * Totally custom save method
     *
     * @param Event               $event
     * @param string|null              $efficacy
     * @param string|null              $repeatSetting
     * @param Alaress_Date_Format|null $until
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param int|null                 $access
     * @param EventRelations      $eventRelations
     * @throws MapperException
     */
    public function save(
        Event $event,
        ?string $efficacy,
        ?string $repeatSetting,
        ?Alaress_Date_Format $until,
        Alaress_Date_Format $startDate,
        Alaress_Date_Format $endDate,
        ?int $access,
        EventRelations $eventRelations
    ) {

        /* Set up recurrences, if appropriate
         * This is mandatory as events are saved via EventRecurrence
         */
        $eventRecurrence = new EventRecurrence(
            $this->eventMapper,
            $efficacy,
            $repeatSetting,
            $until,
            $startDate,
            $endDate
        );

        // silence!! but srsly, in this context, the event mapper needs to not raise notifications
        $eventPropagator = $this->eventMapper->getEventPropagator();
        $this->eventMapper->setEventPropagator(new MockPropagator());

        if (is_numeric($event->getId())) {
            // pre-existing event

            /* Remember first event id in a series
             */
            $this->setFirstEventIdInSeries($event->getId());

            try {
                /** @var Event $previous */
                $previous = $this->eventMapper->findOneByColumn(
                    $this->eventMapper->getIdentifierColumn(),
                    $event->getId(),
                    ['cache' => false]
                );
                $previous = $this->eventMapper->populateGuests($previous);
            } catch (MapperException $e) {
                $previous = null;
            }

            if ($efficacy == self::EFFICACY_SERIES) {
                $this->persistSeries($event, $eventRelations, $access, $startDate, $endDate);
            } elseif ($efficacy == self::EFFICACY_SELF) {
                $this->persistSingle($event, $eventRelations, $access, $eventRecurrence, (bool)$previous->getSeriesId());
            }
        } else {
            // this is a new object
            $previous = null;

            /* Create N recurring events which are wholly new. When you save a model through a mapper, it gives that model's instance the affectedId (that is,
             * the id it will have in the db). To save clones of event with different dates, need to set the primary key to null explicitly
             */
            $this->persist(
                $event,
                $eventRecurrence->getDateCollection(),
                $eventRecurrence->getSeriesId(),
                $eventRelations,
                $access
            );
        }

        $action = $previous ? Schoolbox\Mapper::METHOD_UPDATE : Schoolbox\Mapper::METHOD_CREATE;

        // generate the notification on behalf of the EventMapper that was suppressed
        $this->eventMapper->setEventPropagator($eventPropagator);
        $this->raiseNotification($event, $action, $previous);
    }

    /**
     * Persist an entire series
     *
     * @param Event               $event
     * @param EventRelations      $eventRelations
     * @param int                 $access
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @throws MapperException
     */
    protected function persistSeries(
        Event $event,
        EventRelations $eventRelations,
        $access,
        Alaress_Date_Format $startDate,
        Alaress_Date_Format $endDate
    ) {
        // Clone metadata IMMEDIATELY otherwise it will be clobbered by refetch
        // of $event in getAllInSeries below. (╯°□°）╯︵ ┻━┻
        $seriesEventMetadata = $event->getMetadata();
        if (is_object($seriesEventMetadata)) {
            $metadata = clone $seriesEventMetadata;
        } else {
            $metadata = $seriesEventMetadata;
        }

        /* Update details of an already-existing set of recurring event incidents
         * (Change details of each instance of a recurring event, for example)
         */
        $series = $this->eventMapper->getAllInSeries($event);


        if ($series->count() == 0) {
            // there's no point continuing if there's nothing to work with
            return;
        }

        $event->setAccess($access);

        // Use start and end deltas to set time of each series event
        /** @var Event $originalEvent */
        $originalEvent = $this->eventMapper->findOneByColumn('id', $event->getId(), ['cache' => false]);

        $startDelta = Interval::createFromDateTimeDifference(
            $originalEvent->getStartDateTime()->getDateTimeObject(),
            $startDate->getDateTimeObject()
        )->getAsDateInterval();

        $endDelta = Interval::createFromDateTimeDifference(
            $originalEvent->getEndDateTime()->getDateTimeObject(),
            $endDate->getDateTimeObject()
        )->getAsDateInterval();

        foreach ($series as $seriesEvent) {
            $this->setSeriesEventData($seriesEvent, $event);

            // clone AGAIN
            if (is_object($metadata)) {
                $seriesMetadata = clone $metadata;
            } else {
                $seriesMetadata = $metadata;
            }

            // Adjust each event's start, end and publish date individually
            $seriesEvent->getStartDateTime()->getDateTimeObject()->add($startDelta);
            $seriesEvent->getEndDateTime()->getDateTimeObject()->add($endDelta);

            // Assign publishDateTime within the condition (On purpose)
            if ($publishDateTime = $event->getPublishDateTime()) {
                $seriesEvent->setPublishDateTime($publishDateTime);
            } else {
                $seriesEvent->setPublishDateTime(null);
            }

            $this->eventMapper->save($seriesEvent);
            $this->eventMapper->updateMetadata($seriesMetadata, $seriesEvent);

            /* Assign relations
             */
            $eventRelations->resetRelations($seriesEvent);
        }
    }

    /**
     * Persist a single event
     *
     * @param Event           $event
     * @param EventRelations  $eventRelations
     * @param int|null        $access
     * @param EventRecurrence $eventRecurrence
     * @param bool            $recreateMetadata
     * @throws MapperException
     */
    protected function persistSingle(
        Event $event,
        EventRelations $eventRelations,
        ?int $access,
        EventRecurrence $eventRecurrence,
        bool $recreateMetadata
    ) {
        // Modify an existing event and add recurring incidents
        // If there's any recurrence: update first only, and create the
        // rest from scratch.
        $first_date_set = $eventRecurrence->getDateCollection()->first();

        /* Tried to set a recurrence date in the past
         * You can't repeat events into the past
         */
        if ($first_date_set == null) {
            return;
        }

        $event->setSeriesId($eventRecurrence->getSeriesId());
        $event->setStartDate($first_date_set['start']);
        $event->setEndDate($first_date_set['end']);
        $event->setAccess($access);

        $eventMetadata = $event->getMetadata();
        if ($eventMetadata instanceof EventMetadata) {
            $metadata = clone $eventMetadata;
            if ($recreateMetadata) {
                $this->eventMapper->deleteMetadataRelations($event);
                $metadata->setId(null);
            }
        } else {
            $metadata = $eventMetadata;
        }

        $this->eventMapper->save($event);

        /* Some weird caching problems occur if you don't do this separately
         * The staff attendees list doesn't get updated, for example
         */
        $this->eventMapper->updateMetadata($metadata, $event);

        /* Assign relations
         */
        $eventRelations->resetRelations($event);

        if (!$event->getSeriesId()) {
            // there is no series ID, so we have nothing further to save.
            return;
        }

        /* Skip the first element because we just processed it.
         * Spawn new event records for the rest.
         * ($eventRecurrence is mutable, but not used outside this
         *  method, so that's tolerable.)
         */
        $this->persist(
            $event,
            $eventRecurrence->getDateCollection()->slice(1),
            $eventRecurrence->getSeriesId(),
            $eventRelations,
            $access
        );
    }

    /**
     * @param Event $seriesEvent
     * @param Event $coreEvent
     */
    protected function setSeriesEventData($seriesEvent, $coreEvent)
    {
        /* Copy date from $event to each thingy in the series
         * to preserve other series -related data
         */
        $seriesEvent->setTitle($coreEvent->getTitle());
        $seriesEvent->setDetail($coreEvent->getDetail());
        $seriesEvent->setLocation($coreEvent->getLocation());
        $seriesEvent->setAllDay($coreEvent->getAllDay());
        $seriesEvent->setCampusCollection($coreEvent->getCampusCollection());
        $seriesEvent->setRoleCollection($coreEvent->getRoleCollection());
        $seriesEvent->setAccess($coreEvent->getAccess());
        $seriesEvent->setType($coreEvent->getType());
    }

    /**
     * Match signature of Schoolbox\Mapper and emulate an Event notification
     *
     * @param Event      $object
     * @param string     $verb
     * @param Event|null $previous
     * @return bool
     */
    public function raiseNotification(Event $object, string $verb, ?Event $previous = null): bool
    {
        $sessionUser = $this->eventMapper->getSessionUser();
        if (!$sessionUser) {
            return false;
        }

        try {
            // TODO: diff doesn't work for roles, folders and campuses
            $diff = $previous && ($verb != Schoolbox\Mapper::METHOD_DELETE) ? $this->eventMapper->diff($object, $previous) : null;
        } catch (MapperException $e) {
            return false;
        }
        $this->eventMapper->getEventPropagator()->propagate($sessionUser, $verb, $object, $this->eventMapper, $diff);
        return true;
    }

    /**
     * @param Event          $event
     * @param Traversable    $dates
     * @param EventRelations $eventRelations
     * @param int            $sid
     * @param int            $access
     * @throws MapperException
     */
    protected function persist(Event $event, Traversable $dates, $sid, EventRelations $eventRelations, $access)
    {
        $event->setSeriesId($sid)->setAccess($access);

        foreach ($dates as $currentDate) {
            $event->setStartDate($currentDate['start'])->setEndDate($currentDate['end']);

            $this->eventMapper->create($event);
            $this->eventMapper->updateMetadata($event->getMetadata(), $event);

            /* Remember first event id in a series
             */
            if (!$this->getFirstEventIdInSeries()) {
                $this->setFirstEventIdInSeries($event->getId());
            }

            // Assign relations
            $eventRelations->resetRelations($event);
        }
    }

    /**
     * Another totally custom recurrence save method - this one uses an iterable
     * date source to create dates.
     *
     * @param Event                  $event
     * @param DateTime[]|Traversable $dateSource
     * @param EventRelations         $eventRelations
     * @return Collection|Event[]
     * @throws MapperException
     */
    public function createSequenceFromIterator(
        Event $event,
        $dateSource = null,
        EventRelations $eventRelations = null
    ) {
        $sequence = $this->getSequenceFromIterator($event, $dateSource);

        foreach ($sequence as $event) {
            $this->eventMapper->save($event);

            if ($eventRelations instanceof EventRelations) {
                $eventRelations->resetRelations($event);
            }
        }

        return $sequence;
    }

    /**
     * Gets an event sequence from a base event and an iterable date source.
     *
     * @param Event $event
     * @param DateTime[]|Traversable $dateSource
     *
     * @return Collection|Event[]
     */
    protected function getSequenceFromIterator(Event $event, $dateSource = null)
    {
        if (empty($dateSource)) {
            // just get the single event
            return new Collection([$event]);
        }

        // dateSource could be a datePeriod
        // How many days are there between $start and $end?
        $startDate = clone $event->getStartDateTime();
        $endDate = clone $event->getEndDateTime();

        $startDate->startOfDay();
        $endDate->startOfDay();

        $dayDiff = $startDate->diffInDays($endDate);
        $seriesId = $this->eventMapper->getUpperSeriesIndex();

        $collection = new Collection();

        foreach ($dateSource as $currentDate) {
            $year = $currentDate->format('Y');
            $month = $currentDate->format('m');
            $day = $currentDate->format('d');

            $newEvent = clone $event;

            $newStart = clone $newEvent->getStartDateTime();
            $newStart->setDate($year, $month, $day);

            $newEnd = clone $newEvent->getEndDateTime();
            $newEnd->setDate($year, $month, $day)->addDays($dayDiff);

            $newEvent->setSeriesId($seriesId);
            $newEvent->setId(null);
            $newEvent->setStartDateTime($newStart);
            $newEvent->setEndDateTime($newEnd);

            $collection->add($newEvent);
        }

        return $collection;
    }

    /**
     * Set first event id in series
     *
     * @param int $firstEventIdInSeries
     */
    protected function setFirstEventIdInSeries($firstEventIdInSeries)
    {
        $this->firstEventIdInSeries = $firstEventIdInSeries;
    }

    /**
     * Get first event id in series
     *
     * @return int
     */
    public function getFirstEventIdInSeries()
    {
        return $this->firstEventIdInSeries;
    }
}
