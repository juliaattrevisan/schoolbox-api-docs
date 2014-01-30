<?php
/**
 * EventRecurrence
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\Calendar;


class EventRecurrence {

	/**
	 * Possible values for recurrence
	 *
	 * @var array
	 */
	private $validRepeat = array(
		'daily' => '+1 day',
		'weekly' => '+1 week',
		'fortnightly' => '+2 weeks',
		'monthly' => '+1 month'
	);

	/**
	 * A calendar handler
	 *
	 * @var null
	 */
	private $calendarHandler = null;

	/**
	 * Maximum number of times an event can recurrrrr
	 *
	 * @var int
	 */
	private $maxNumRecurring = 100;

	/**
	 * Series id
	 *
	 * @var int
	 */
	private $seriesId = -1;

	/**
	 * An array of Alaress_Date_Format objects which indicate when a
	 * recurring event incident begins and ends
	 *
	 * @var array
	 */
	private $dateCollection = array();

	/**
	 * The event id
	 *
	 * @var null
	 */
	private $eventId = null;

	/**
	 * Efficacy setting chosen
	 *
	 * @var string
	 */
	private $efficacy = '';

	/**
	 * Possible efficacy values
	 *
	 * @var string
	 */
	private $efficacyValues = array('series', 'self');

	/**
	 * The chosen frequency of event recurrence (daily, weekly, etc)
	 * as selected by the user
	 *
	 * @var string
	 */
	private $repeatSetting = '';

	/**
	 * Recur events until this date
	 * An instance of Alaress_Date_Format
	 *
	 * @var string
	 */
	private $until = null;

	/**
	 * An instance of Alaress_Date_Format
	 *
	 * @var null
	 */
	private $startDate = null;

	/**
	 * An instance of Alaress_Date_Format
	 *
	 * @var null
	 */
	private $endDate = null;

	/**
	 * Constructor
	 *
	 * @param $eventId
	 * @param $efficacy
	 * @param $repeatSetting
	 * @param \Alaress_Date_Format $until
	 * @param \Alaress_Date_Format $startDate
	 * @param \Alaress_Date_Format $endDate
	 */
	public function __construct($eventId, $efficacy, $repeatSetting, \Alaress_Date_Format $until, \Alaress_Date_Format $startDate, \Alaress_Date_Format $endDate) {
		$this->setEventId($eventId);
		$this->setCalendarHandler(new \Calendar());
		$this->setDateCollection(new \Schoolbox\Collection());
		$this->setRepeatSetting($repeatSetting);
		$this->setUntil($until);
		$this->setStartDate($startDate);
		$this->setEndDate($endDate);

		/* Used for recurring events - determines how broadly
		 * changes are made to event details etc
		 */
		if (in_array($efficacy, $this->getEfficacyValues())) {
			$this->setEfficacy($efficacy);
		}
	}

	/**
	 * Set up recurring events
	 * Return an array of Alaress_Date_Format instances
	 *
	 * @return array
	 */
	public function setup() {

		// repeat specified, and and enddate also, populate the dates to be set
		if ($this->isValidInterval() && $this->getUntil()->getUnixTimestamp()) {
			$this->setSeriesId($this->getCalendarHandler()->getUpperSeriesIndex() + 1);

			$start_timestamp = $this->getStartDate()->getUnixTimestamp();
			$end_timestamp = $this->getEndDate()->getUnixTimestamp();

			// Repeat of events: repeat until the start date is after the until
			// date (or x100, whatever is less)
			for ($i = 0; ($start_timestamp <= $this->getUntil()->getUnixTimestamp()) && ($i < $this->getMaxNumRecurring()); $i++) {

				/* Increment to the next incident of the event
				 */
				$start_timestamp = strtotime($this->intervalValue(), $start_timestamp);
				$end_timestamp = strtotime($this->intervalValue(), $end_timestamp);

				$this_start_date = new \Alaress_Date_Format($start_timestamp, 'U');
				$this_end_date = new \Alaress_Date_Format($end_timestamp, 'U');

				$this->getDateCollection()->add(array(
					'start' => $this_start_date->getDatabaseDate(),
					'end' => $this_end_date->getDatabaseDate(),
				));
			}
		}

		// if repeat is set, but no enddate was specified, bail out
		// "not enough data submitted to the form"
		else if($this->isValidInterval() && (!$this->getUntil()->getUnixTimestamp())) {
			error(2);
		}

		// no repeat
		else {
			$this->getDateCollection()->add(array(
				'start' => $this->getStartDate()->getDatabaseDate(),
				'end' => $this->getEndDate()->getDatabaseDate(),
			));
		}

		return $this;
	}

	/**
	 * A pseudo-mapper
	 */
	public function save($event) {
		$event['series_id'] = $this->getSeriesId();

		/* Modify an existing event
		 */
		if (is_numeric($this->getEventId())) {

			/* Update details of an already-existing set of recurring event incidents
			 * (Change details of each instance of a recurring event, for example)
			 */
			if ($this->getEfficacy() == 'series') {
				// don't override the date or seriesID
				unset($event['startDate']);
				unset($event['endDate']);
				unset($event['series_id']);

				$series = $this->getCalendarHandler()->getAllInSeriesByEventID($this->getEventId());

				foreach($series as $seriesEvent) {
					$event['id'] = $seriesEvent['id'];
					$this->getCalendarHandler()->update($event);
				}
			}
			/* Modify an existing event and add recurring incidents
			 */
			else if($this->getEfficacy() == 'self') {
				//
				// If there's any recurrence: update first only, and create the
				// rest from scratch.
				$first_date_set = $this->getDateCollection()->first();
				$event['id'] = $this->getEventId();
				$event['startDate'] = $first_date_set['start'];
				$event['endDate'] = $first_date_set['end'];

				$this->getCalendarHandler()->update($event);

				/* Skip the first element, spawn new event records
				 */
				$this->getDateCollection()->next();

				foreach ($this->getDateCollection() as $currentDate) {
					unset($event['id']);
					$event['startDate'] = $currentDate['start'];
					$event['endDate'] = $currentDate['end'];

					$this->getCalendarHandler()->create($event);
				}
			}
		}
		else {
			/* Create N recurring events which are wholly new
			 */
			foreach ($this->getDateCollection() as $currentDate) {
				$event['startDate'] = $currentDate['start'];
				$event['endDate'] = $currentDate['end'];

				$this->getCalendarHandler()->create($event);
			}
		}
	}

	/**
	 * Get a strtotime interval from a chosen setting
	 *
	 * @return mixed
	 */
	private function intervalValue() {
		return $this->validRepeat[$this->getRepeatSetting()];
	}

	/**
	 * Whether the interval setting chosen is valid
	 *
	 * @return bool
	 */
	private function isValidInterval() {
		if (isset($this->validRepeat[$this->getRepeatSetting()])) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set calendar handler
	 *
	 * @param null $calendarHandler
	 */
	private function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	private function getCalendarHandler() {
		return $this->calendarHandler;
	}

	/**
	 * Set max num recurring
	 *
	 * @param int $maxNumRecurring
	 */
	private function setMaxNumRecurring($maxNumRecurring) {
		$this->maxNumRecurring = $maxNumRecurring;
	}

	/**
	 * Get max num recurring
	 *
	 * @return int
	 */
	private function getMaxNumRecurring() {
		return $this->maxNumRecurring;
	}

	/**
	 * Set series id
	 *
	 * @param int $seriesId
	 */
	private function setSeriesId($seriesId) {
		$this->seriesId = $seriesId;
	}

	/**
	 * Get series id
	 *
	 * @return int
	 */
	public function getSeriesId() {
		return $this->seriesId;
	}

	/**
	 * Set repeat setting
	 *
	 * @param string $repeatSetting
	 */
	private function setRepeatSetting($repeatSetting) {
		$this->repeatSetting = $repeatSetting;
	}

	/**
	 * Get repeat setting
	 *
	 * @return string
	 */
	private function getRepeatSetting() {
		return $this->repeatSetting;
	}

	/**
	 * Set date collection
	 *
	 * @param array $dateCollection
	 */
	private function setDateCollection($dateCollection) {
		$this->dateCollection = $dateCollection;
	}

	/**
	 * Get date collection
	 *
	 * @return array
	 */
	public function getDateCollection() {
		return $this->dateCollection;
	}

	/**
	 * Set efficacy
	 *
	 * @param string $efficacy
	 */
	private function setEfficacy($efficacy) {
		$this->efficacy = $efficacy;
	}

	/**
	 * Get efficacy
	 *
	 * @return string
	 */
	private function getEfficacy() {
		return $this->efficacy;
	}

	/**
	 * Set efficacy values
	 *
	 * @param string $efficacyValues
	 */
	private function setEfficacyValues($efficacyValues) {
		$this->efficacyValues = $efficacyValues;
	}

	/**
	 * Get efficacy values
	 *
	 * @return string
	 */
	private function getEfficacyValues() {
		return $this->efficacyValues;
	}

	/**
	 * Set event id
	 *
	 * @param null $eventId
	 */
	private function setEventId($eventId) {
		$this->eventId = $eventId;
	}

	/**
	 * Get event id
	 *
	 * @return null
	 */
	private function getEventId() {
		return $this->eventId;
	}

	/**
	 * Set until
	 *
	 * @param string $until
	 */
	private function setUntil($until) {
		$this->until = $until;
	}

	/**
	 * Get until
	 *
	 * @return string
	 */
	private function getUntil() {
		return $this->until;
	}

	/**
	 * Set start date
	 *
	 * @param null $startDate
	 */
	private function setStartDate($startDate) {
		$this->startDate = $startDate;
	}

	/**
	 * Get start date
	 *
	 * @return null
	 */
	private function getStartDate() {
		return $this->startDate;
	}

	/**
	 * Set end date
	 *
	 * @param null $endDate
	 */
	private function setEndDate($endDate) {
		$this->endDate = $endDate;
	}

	/**
	 * Get end date
	 * 
	 * @return null
	 */
	private function getEndDate() {
		return $this->endDate;
	}
}