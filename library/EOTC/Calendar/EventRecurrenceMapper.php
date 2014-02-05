<?php
/**
 * EventRecurrenceMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\Calendar;


class EventRecurrenceMapper {

	/**
	 * An instance of \EOTC\Calendar\EventMapper
	 *
	 * @var null
	 */
	protected $eventMapper = null;

	/**
	 * An instance of calendarHandler
	 *
	 * @var null
	 */
	protected $calendarHandler = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Hook method
	 */
	protected function setup() {
		$this->setEventMapper(new EventMapper());
		$this->setCalendarHandler(new \Calendar());
	}

	/**
	 * Totally custom save method
	 *
	 * @param Event $event
	 * @param $efficacy
	 * @param $repeatSetting
	 * @param $until
	 * @param \Alaress_Date_Format $startDate
	 * @param \Alaress_Date_Format $endDate
	 */
	public function save(Event $event, $efficacy, $repeatSetting, $until, \Alaress_Date_Format $startDate, \Alaress_Date_Format $endDate) {

		/* Set up recurrences, if appropriate
		 * This is mandatory as events are saved via EventRecurrence
		 */
		$eventRecurrence = new EventRecurrence($efficacy, $repeatSetting, $until, $startDate, $endDate);
		$eventRecurrence->setup();

		/* Modify an existing event
		 */
		if (is_numeric($event->getId())) {

			/* Update details of an already-existing set of recurring event incidents
			 * (Change details of each instance of a recurring event, for example)
			 */
			if ($efficacy == 'series') {
				$series = $this->getCalendarHandler()->getAllInSeriesByEventID($event->getId());

				foreach($series as $seriesEvent) {
					$event->setId($seriesEvent['id']);

					$this->getEventMapper()->save($event);
				}
			}
			/* Modify an existing event and add recurring incidents
			 */
			else if ($efficacy == 'self') {

				// If there's any recurrence: update first only, and create the
				// rest from scratch.
				$first_date_set = $eventRecurrence->getDateCollection()->first();

				$event->setStartDate($first_date_set['start']);
				$event->setEndDate($first_date_set['end']);

				$this->getEventMapper()->save($event);

				/* Skip the first element, spawn new event records
				 */
				$eventRecurrence->getDateCollection()->next();

				for (; $eventRecurrence->getDateCollection()->valid(); $eventRecurrence->getDateCollection()->next()) {
					$currentDate = $eventRecurrence->getDateCollection()->current();

					$event->setId(null);
					$event->setSeriesId($eventRecurrence->getSeriesId());
					$event->setStartDate($currentDate['start']);
					$event->setEndDate($currentDate['end']);

					$this->getEventMapper()->save($event);
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

				$this->getEventMapper()->save($event);
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set event mapper
	 *
	 * @param null $eventMapper
	 */
	protected function setEventMapper($eventMapper) {
		$this->eventMapper = $eventMapper;
	}

	/**
	 * Get event mapper
	 *
	 * @return null
	 */
	protected function getEventMapper() {
		return $this->eventMapper;
	}

	/**
	 * Set calendar handler
	 *
	 * @param null $calendarHandler
	 */
	protected function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	protected function getCalendarHandler() {
		return $this->calendarHandler;
	}
}