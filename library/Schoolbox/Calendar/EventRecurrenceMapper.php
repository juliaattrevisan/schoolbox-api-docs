<?php
/**
 * EventRecurrenceMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;

use Alaress\Date\Interval;
use Alaress_Date_Format;
use DateTime;
use Schoolbox;
use Schoolbox\Collection;
use Schoolbox\Notif\NotifInterface;
use Schoolbox\Notif\SilentNotif;
use Traversable;

class EventRecurrenceMapper
{

    const EFFICACY_SELF   = 'self';
    const EFFICACY_SERIES = 'series';

    /**
     * An instance of \Schoolbox\Calendar\EventMapper
     *
     * @var EventMapper
     */
    protected $eventMapper = null;

    /** @var NotifInterface */
    protected $notif;

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
     * @param EventMapper $eventMapper
     */
    public function __construct($eventMapper)
    {
        $this->setEventMapper($eventMapper);

        // stash the existing notifier so we can use it ourselves
        $this->notif = $eventMapper->getNotifier();

        // silence!! but srsly, in this context, the event mapper needs to not raise notifications
        $eventMapper->setNotifier(new SilentNotif());
    }


    /**
     * Get the series recurrence settings
     *
     * @param $event
     * @return array
     */
    public function getSeriesRecurrence($event)
    {
        $series = $this->getEventMapper()->getAllInSeries($event);

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
        $dateNext  = $series->next()->current()->getStartDateTime();
        $dateNext->startOfDay();
        /** @var Alaress_Date_Format $dateLast */
        $dateLast  = $series->last()->getStartDateTime();
        $dateLast->startOfDay();

        //$diff = (int)(($dateNext->getUnixTimestamp() - $dateFirst->getUnixTimestamp()) / 86400);
        $diff = $dateFirst->diffInDays($dateNext);

        $recurMap = array(
            1  => 'daily',
            7  => 'weekly',
            14 => 'fortnightly',
            28 => 'monthly',
            29 => 'monthly',
            30 => 'monthly',
            31 => 'monthly',
        );

        if (isset($recurMap[$diff])) {
            return array(
                'num'   => count($series),
                'freq'  => $recurMap[$diff],
                'start' => $dateFirst,
                'end'   => $dateLast
            );
        }

        return array(
            'num'   => count($series),
            'freq'  => 'periodically',
            'start' => $dateFirst,
            'end'   => $dateLast
        );
    }


    /**
     * Totally custom save method
     *
     * @param Event               $event
     * @param string              $efficacy
     * @param string              $repeatSetting
     * @param Alaress_Date_Format $until
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param int                 $access
     * @param EventRelations      $eventRelations
     */
    public function save(Event $event, $efficacy, $repeatSetting, $until, Alaress_Date_Format $startDate, Alaress_Date_Format $endDate, $access, EventRelations $eventRelations)
    {

        /* Set up recurrences, if appropriate
         * This is mandatory as events are saved via EventRecurrence
         */
        $eventRecurrence = new EventRecurrence($this->getEventMapper(), $efficacy, $repeatSetting, $until, $startDate, $endDate);

        if (is_numeric($event->getId())) {
            // pre-existing event

            /* Remember first event id in a series
             */
            $this->setFirstEventIdInSeries($event->getId());

            $previous = $this->getEventMapper()->findOneByColumn($this->getEventMapper()->getIdentifierColumn(), $event->getId(), ['cache'=>false]);

            if ($efficacy == self::EFFICACY_SERIES) {
                $this->persistSeries($event, $eventRelations, $access, $startDate, $endDate);
            } elseif ($efficacy == self::EFFICACY_SELF) {
                $this->persistSingle($event, $eventRelations, $access, $eventRecurrence);
            }

        } else {
            // this is a new object
            $previous = null;

            /* Create N recurring events which are wholly new. When you save a model through a mapper, it gives that model's instance the affectedId (that is,
             * the id it will have in the db). To save clones of event with different dates, need to set the primary key to null explicitly
             */
            $this->persist($event, $eventRecurrence->getDateCollection(), $eventRecurrence->getSeriesId(), $eventRelations, $access);
        }

        $action = $previous ? Schoolbox\Mapper::METHOD_UPDATE : Schoolbox\Mapper::METHOD_CREATE;

        // generate the notification on behalf of the EventMapper that was suppressed
        $this->raiseNotification($event, $action, $previous);
    }


    /**
     * Persist an entire series
     *
     * @param Event               $event
     * @param EventRelations      $eventRelations
     * @param                     $access
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @throws Schoolbox\Mapper\Exception
     */
    protected function persistSeries(Event $event, EventRelations $eventRelations, $access, Alaress_Date_Format $startDate, Alaress_Date_Format $endDate)
    {
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
        $series = $this->getEventMapper()->getAllInSeries($event);


        if ($series->count() == 0) {
            // there's no point continuing if there's nothing to work with
            return;
        }

        $event->setAccess($access);

        // Use start and end deltas to set time of each series event
        $originalEvent = $this->getEventMapper()->findOneByColumn('id', $event->getId(), [], ['cache' => false]);
        $startDelta    = Interval::createFromDateTimeDifference(
            $originalEvent->getStartDateTime()->getDateTimeObject(),
            $startDate->getDateTimeObject()
        )->getAsDateInterval();
        $endDelta      = Interval::createFromDateTimeDifference(
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

            // Adjust each event's start and end individually
            $seriesEvent->getStartDateTime()->getDateTimeObject()->add($startDelta);
            $seriesEvent->getEndDateTime()->getDateTimeObject()->add($endDelta);
            $this->getEventMapper()->save($seriesEvent);
            $this->getEventMapper()->updateMetadata($seriesMetadata, $seriesEvent);

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
     * @param                 $access
     * @param EventRecurrence $eventRecurrence
     */
    protected function persistSingle(Event $event, EventRelations $eventRelations, $access, EventRecurrence $eventRecurrence)
    {
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
        if (is_object($eventMetadata)) {
            $metadata = clone $eventMetadata;
        } else {
            $metadata = $eventMetadata;
        }

        $this->getEventMapper()->save($event);

        /* Some weird caching problems occur if you don't do this separately
         * The staff attendees list doesn't get updated, for example
         */
        $this->getEventMapper()->updateMetadata($metadata, $event);

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
     * @param Event  $object
     * @param string $verb
     * @param Event  $previous
     * @throws Schoolbox\Mapper\Exception
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        $em = $this->getEventMapper();

        $sess = $em->getSessionData();

        if (empty($sess['user'])) {
            return false;
        }

        $diff = $previous && ($verb != Schoolbox\Mapper::METHOD_DELETE) ? $em->diff($object, $previous) : null;

        try {
            $em->getEventPropagator()->propagate($sess['user'], $verb, $object, $em, $diff);
        }
        catch( \ErrorException $e) {
            //Unable to connect to notification system
            return false;
        }

        return true;
    }

    /**
     * @param Event          $event
     * @param Traversable    $dates
     * @param EventRelations $eventRelations
     * @param int            $sid
     * @param int            $access
     */
    protected function persist(Event $event, Traversable $dates, $sid, EventRelations $eventRelations, $access)
    {
        $event->setSeriesId($sid)
              ->setAccess($access);

        foreach ($dates as $currentDate) {
            $event->setStartDate($currentDate['start'])
                  ->setEndDate($currentDate['end']);

            $this->getEventMapper()->create($event);
            $this->getEventMapper()->updateMetadata($event->getMetadata(), $event);

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
     * @param Event                   $event
     * @param DateTime[]|Traversable  $dateSource
     * @param EventRelations          $eventRelations
     * @return Collection|Event[]
     */
    public function createSequenceFromIterator(
        Event $event,
        $dateSource = null,
        EventRelations $eventRelations = null
    ) {
        $sequence = $this->getSequenceFromIterator($event, $dateSource);

        foreach ($sequence as $event) {
            $this->getEventMapper()->save($event);

            if ($eventRelations instanceof EventRelations) {
                $eventRelations->resetRelations($event);
            }
        }

        return $sequence;
    }

    /**
     * Gets an event sequence from a base event and an iterable date source.
     *
     * @param Event                  $event
     * @param DateTime[]|Traversable $dateSource
     *
     * @return Collection|Event[]
     */
    protected function getSequenceFromIterator(Event $event, $dateSource = null)
    {
        if (empty($dateSource)) {
            // just get the single event
            return new Collection([ $event ]);
        }

        // dateSource could be a datePeriod
        // How many days are there between $start and $end?
        $startDate = clone $event->getStartDateTime();
        $endDate   = clone $event->getEndDateTime();

        $startDate->startOfDay();
        $endDate->startOfDay();

        $dayDiff  = $startDate->diffInDays($endDate);
        $seriesId = $this->getEventMapper()->getUpperSeriesIndex();

        $collection = new Collection();

        foreach ($dateSource as $currentDate) {
            $year  = $currentDate->format('Y');
            $month = $currentDate->format('m');
            $day   = $currentDate->format('d');

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

    /* Getters/Setters
     */

    /**
     * Get event mapper
     *
     * @return EventMapper
     */
    protected function getEventMapper()
    {
        return $this->eventMapper;
    }

    /**
     * Set event mapper
     *
     * @param EventMapper $eventMapper
     */
    protected function setEventMapper($eventMapper)
    {
        $this->eventMapper = $eventMapper;
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
