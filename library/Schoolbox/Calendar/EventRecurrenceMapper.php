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
use Alaress\Lumberjack\Logger;

class EventRecurrenceMapper
{

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
        $this->notif = $this->getEventMapper()
                            ->getNotifier();

        // silence!! but srsly, in this context, the event mapper needs to not raise notifications
        $this->getEventMapper()
             ->setNotifier(new SilentNotif(new Logger(null)));

        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
    }

    /**
     * Get the series recurrence settings
     *
     * @param $event
     * @return array
     */
    public function getSeriesRecurrence($event)
    {
        $series = $this->getEventMapper()
                       ->getAllInSeries($event);
        if (count($series) <= 1) {
            return array();
        }

        $dateFirst = $series->first()->getStartDateTime();
        $dateFirst->startOfDay();
        $dateNext  = $series->next()->current()->getStartDateTime();
        $dateNext->startOfDay();
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
                'freq'  => $recurMap[$diff],
                'start' => $dateFirst,
                'end'   => $dateLast
            );
        }

        return array(
            'freq'  => 'periodically',
            'start' => $dateFirst,
            'end'   => $dateLast
        );
    }


    /**
     * Totally custom save method
     *
     * @param Event                              $event
     * @param                                    $efficacy
     * @param                                    $repeatSetting
     * @param                                    $until
     * @param Alaress_Date_Format                $startDate
     * @param Alaress_Date_Format                $endDate
     * @param                                    $access
     * @param \Schoolbox\Calendar\EventRelations $eventRelations
     */
    public function save(Event $event, $efficacy, $repeatSetting, $until, Alaress_Date_Format $startDate, Alaress_Date_Format $endDate, $access, EventRelations $eventRelations)
    {

        /* Set up recurrences, if appropriate
         * This is mandatory as events are saved via EventRecurrence
         */
        $eventRecurrence = new EventRecurrence($this->getEventMapper(), $efficacy, $repeatSetting, $until, $startDate, $endDate);
        $eventRecurrence->setup();

        /* Modify an existing event
         */
        if (is_numeric($event->getId())) {

            /* Remember first event id in a series
             */
            $this->setFirstEventIdInSeries($event->getId());

            $previous = $this->getEventMapper()->findById($event->getId());

            /* Update details of an already-existing set of recurring event incidents
             * (Change details of each instance of a recurring event, for example)
             */
            if ($efficacy == 'series') {
                $series = $this->getEventMapper()->getAllInSeries($event);

                if ($series->count() == 0) {
                    // there's no point continuing if there's nothing to work with
                    return;
                }

                $event->setAccess($access);

                // Use start and end deltas to set time of each series event
                $originalEvent = $this->getEventMapper()->findById($event->getId());
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
                    // Adjust each event's start and end individually
                    $seriesEvent->getStartDateTime()->getDateTimeObject()->add($startDelta);
                    $seriesEvent->getEndDateTime()->getDateTimeObject()->add($endDelta);
                    $this->getEventMapper()->save($seriesEvent);

                    /* Assign relations
                     */
                    $eventRelations->resetRelations($seriesEvent);
                }

                // seriesEvent will currently be the last event from the iteration, which will be compared to the last event as retrieved from the DB
                // yes. this is cheating.
                $this->raiseNotification($event, Schoolbox\Mapper::METHOD_UPDATE, $previous);
            } // Modify an existing event and add recurring incidents
            elseif ($efficacy == 'self') {
                // If there's any recurrence: update first only, and create the
                // rest from scratch.
                $first_date_set = $eventRecurrence->getDateCollection()->first();

                $event->setSeriesId($eventRecurrence->getSeriesId());
                $event->setStartDate($first_date_set['start']);
                $event->setEndDate($first_date_set['end']);
                $event->setAccess($access);

                $this->getEventMapper()->save($event);

                /* Assign relations
                 */
                $eventRelations->resetRelations($event);

                if (!$event->getSeriesId()) {
                    return;
                }

                /* Skip the first element, spawn new event records
                 */
                $eventRecurrence->getDateCollection()->next();

                $this->persist($event, $eventRecurrence->getDateCollection(), $eventRecurrence->getSeriesId(), $eventRelations, $access, $previous);
            }
        } /* Create N recurring events which are wholly new
		 *
		 * When you save a model through a mapper, it gives that model's instance the
		 * affectedId (that is, the id it will have in the db). To save clones of event
		 * with different dates, need to set the primary key to null explicitly
		 */
        else {
            $this->persist($event, $eventRecurrence->getDateCollection(), $eventRecurrence->getSeriesId(), $eventRelations, $access);
        }
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

        $event = new Schoolbox\Realtime\Schedule\Event($sess['user'], $verb, $object, $em, $diff);

        $this->notif->setTopic(Schoolbox\Notif\Notif::TOPIC_APPLICATION)->notice(serialize($event));

        return true;
    }

    /**
     * @param Event          $event
     * @param Traversable    $dates
     * @param EventRelations $eventRelations
     * @param int            $sid
     * @param int            $access
     * @param Event          $previous
     */
    protected function persist(Event $event, Traversable $dates, $sid, EventRelations $eventRelations, $access, $previous = null)
    {
        $method = $event->getId() ? Schoolbox\Mapper::METHOD_UPDATE : Schoolbox\Mapper::METHOD_CREATE;

        $event->setSeriesId($sid)
              ->setAccess($access);

        foreach ($dates as $currentDate) {
            $event->setId(null)
                  ->setStartDate($currentDate['start'])
                  ->setEndDate($currentDate['end']);

            $this->getEventMapper()->save($event);

            /* Remember first event id in a series
             */
            if (!$this->getFirstEventIdInSeries()) {
                $this->setFirstEventIdInSeries($event->getId());
            }

            // Assign relations
            $eventRelations->resetRelations($event);
        }

        $this->raiseNotification($event, $method, $previous);
    }

    /**
     * Another totally custom recurrence save method - this one uses an iterable
     * date source to create dates.
     *
     * @param Event                   $event
     * @param DateTime[]|\Traversable $dateSource
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
    public function getSequenceFromIterator(Event $event, $dateSource = null)
    {
        $collection = new Collection();
        if (!isset($dateSource)) {
            // just get the single event
            $collection->add($event);
        } else {
            // dateSource could be a datePeriod
            // How many days are there between $start and $end?
            $startDate = clone $event->getStartDateTime();
            $endDate   = clone $event->getEndDateTime();
            $startDate->startOfDay();
            $endDate->startOfDay();
            $dayDiff      = $startDate->diffInDays($endDate);
            $seriesId  = $this->getEventMapper()->getUpperSeriesIndex();
            foreach ($dateSource as $currentDate) {
                $year  = $currentDate->format('Y');
                $month = $currentDate->format('m');
                $day   = $currentDate->format('d');

                $newEvent = clone $event;

                $newStart = clone $newEvent->getStartDateTime();
                $newStart->setDate($year, $month, $day);

                $newEnd = clone $newEvent->getEndDateTime();
                $newEnd->setDate($year, $month, $day)->modify("+$dayDiff days");

                $newEvent->setSeriesId($seriesId);
                $newEvent->setId(null);
                $newEvent->setStartDateTime($newStart);
                $newEvent->setEndDateTime($newEnd);
                $collection->add($newEvent);
            }
        }
        return $collection;
    }

    /**
     * Post-event save hook, for augmentation by subclasses
     *
     * @param Event $event
     */
    protected function saveHook(Event $event)
    {
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
