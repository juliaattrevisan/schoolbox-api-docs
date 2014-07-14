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

use Alaress_Date_Format;
use Alaress\Date\Interval;
use DateTime;
use Schoolbox\Calendar\EventRelations;

class EventRecurrenceMapper {

	/**
	 * An instance of \Schoolbox\Calendar\EventMapper
	 *
	 * @var EventMapper
	 */
	protected $eventMapper = null;

	/**
	 * Constructor
	 */
	public function __construct($eventMapper) {
		$this->setEventMapper($eventMapper);

		$this->setup();
	}

	/**
	 * Hook method
	 */
	protected function setup() {
	}

	/**
	 * Get the series recurrence settings
	 *
	 * @param $event
	 * @return array
	 */
	public function getSeriesRecurrence($event) {
		$series = $this->getEventMapper()->getAllInSeries($event);

		$dateFirst = new Alaress_Date_Format($series->first()->getStartDate(), 'y-m-d');
		$dateNext = new Alaress_Date_Format($series->next()->current()->getStartDate(), 'y-m-d');
		$dateLast = new Alaress_Date_Format($series->last()->getStartDate(), 'y-m-d');

		$diff = (int)(($dateNext->getUnixTimestamp() - $dateFirst->getUnixTimestamp()) / 86400);

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
				'freq' => $recurMap[$diff],
				'start' => $dateFirst,
				'end' => $dateLast
			);
		}

		return array(
			'freq' => 'periodically',
			'start' => $dateFirst,
			'end' => $dateLast
		);
	}

    /**
     * Totally custom save method
     *
     * @param Event $event
     * @param $efficacy
     * @param $repeatSetting
     * @param $until
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param $access
     * @param \Schoolbox\Calendar\EventRelations $eventRelations
     */
    public function save(Event $event, $efficacy, $repeatSetting, $until, Alaress_Date_Format $startDate, Alaress_Date_Format $endDate, $access, EventRelations $eventRelations) {

		/* Set up recurrences, if appropriate
		 * This is mandatory as events are saved via EventRecurrence
		 */
		$eventRecurrence = new EventRecurrence($this->getEventMapper(), $efficacy, $repeatSetting, $until, $startDate, $endDate);
		$eventRecurrence->setup();

		/* Modify an existing event
		 */
		if (is_numeric($event->getId())) {

			/* Update details of an already-existing set of recurring event incidents
			 * (Change details of each instance of a recurring event, for example)
			 */
			if ($efficacy == 'series') {
				$series = $this->getEventMapper()->getAllInSeries($event);
                $event->setAccess($access);

				// Use start and end deltas to set time of each series event
				$originalEvent = $this->getEventMapper()->findById($event->getId());
				$startDelta = Interval::createFromDateTimeDifference(
					$originalEvent->getStartDateTime()->getDateTimeObject(),
					$startDate->getDateTimeObject()
				)->getAsDateInterval();
				$endDelta   = Interval::createFromDateTimeDifference(
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
			}
			/* Modify an existing event and add recurring incidents
			 */
			else if ($efficacy == 'self') {

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

				/* Skip the first element, spawn new event records
				 */
				$eventRecurrence->getDateCollection()->next();

				for (; $eventRecurrence->getDateCollection()->valid(); $eventRecurrence->getDateCollection()->next()) {
					$currentDate = $eventRecurrence->getDateCollection()->current();

					$event->setId(null);
					$event->setSeriesId($eventRecurrence->getSeriesId());
					$event->setStartDate($currentDate['start']);
					$event->setEndDate($currentDate['end']);
                    $event->setAccess($access);

					$this->getEventMapper()->save($event);

                    /* Assign relations
                     */
                    $eventRelations->resetRelations($event);
				}
			}
		}
		/* Create N recurring events which are wholly new
		 *
		 * When you save a model through a mapper, it gives that model's instance the
		 * affectedId (that is, the id it will have in the db). To save clones of event
		 * with different dates, need to set the primary key to null explicitly
		 */
		else {
			foreach ($eventRecurrence->getDateCollection() as $currentDate) {
				$event->setId(null);
				$event->setSeriesId($eventRecurrence->getSeriesId());
				$event->setStartDate($currentDate['start']);
				$event->setEndDate($currentDate['end']);
                $event->setAccess($access);

				$this->getEventMapper()->save($event);

                /* Assign relations
                 */
                $eventRelations->resetRelations($event);
			}
		}
	}

	/**
	 * Another totally custom recurrence save method - this one uses an iterable
	 * date source to create dates.
	 *
	 * @param Event                   $event
	 * @param DateTime[]|\Traversable $dateSource
	 */
	public function createSequenceFromIterator(Event $event, $dateSource = null) {
		if (!isset($dateSource)) {
			// just save the single event
			$this->getEventMapper()->save($event);
		}
		else {
			// dateSource could be a datePeriod
			// How many days are there between $start and $end?
			$startDate = new DateTime($event->getStartDate().' 00:00:00');
			$endDate   = new DateTime($event->getEndDate().' 00:00:00');
			$diff      = $startDate->diff($endDate);
			$dayDiff   = $diff->days;
			$seriesId  = $this->getEventMapper()->getUpperSeriesIndex();
			foreach ($dateSource as $currentDate) {
				// Save event in a sequence.
				$event->setId(null);
				$event->setSeriesId($seriesId);
				$event->setStartDate($currentDate->format('Y-m-d'));
				$event->setEndDate($currentDate->modify("+$dayDiff days")->format('Y-m-d'));
				$this->getEventMapper()->save($event);
			}
		}
	}


	/**
	 * @param Event $seriesEvent
	 * @param Event $coreEvent
	 */
	protected function setSeriesEventData($seriesEvent, $coreEvent) {

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
	}

	/**
	 * Post-event save hook, for augmentation by subclasses
	 *
	 * @param Event $event
	 */
	protected function saveHook(Event $event) {
	}

	/* Getters/Setters
	 */

	/**
	 * Set event mapper
	 *
	 * @param EventMapper $eventMapper
	 */
	protected function setEventMapper($eventMapper) {
		$this->eventMapper = $eventMapper;
	}

	/**
	 * Get event mapper
	 *
	 * @return EventMapper
	 */
	protected function getEventMapper() {
		return $this->eventMapper;
	}
}