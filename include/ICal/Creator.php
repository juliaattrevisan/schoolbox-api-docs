<?php
/**
 * ICal_Creator
 *
 * Wrapper class for vcal creational tool
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
require_once('iCalcreator.class.php');

class ICal_Creator {

	/* Source file
	 */
	private $file = null;

	/* Stuff from $_POST
	 */
    private $creator = null;
	private $uid = null;
	private $groupList = array();
	private $campusList = array();

	/* All the events found in the imported ics
	 */
	private $allEvents = array();

	/* Verbose output for confirmation screen
	 */
	private $output = array();

	/* Calendar handler
	 */
	private $calendarHandler = null;

	/**
	 * Constructor
	 *
	 * @param $uid
	 * @param $groupList
	 * @param $campusList
	 * @param $file
	 */
	public function __construct($uid, $creator, $groupList, $campusList, $file) {
//		date_default_timezone_set('Australia/Melbourne');

		$this->setUid($uid);
        $this->setCreator($creator);
		$this->setGroupList($groupList);
		$this->setCampusList($campusList);
		$this->setFile($file);
		$this->setCalendarHandler(new Calendar());

		$this->setAllEvents($this->getEvents());
	}

	/**
	 * Instantiates a calendar object.
	 *
	 * @return vcalendar
	 */
	private function getVcalendar() {
		$init = array(
			'unique_id' => 'scoolbox',
		);

		$config = array(
			'directory' => dirname($this->getFile()),
			'filename' => basename($this->getFile())
		);

		$v = new vcalendar($init);
		$v->setConfig($config);

		return $v;
	}

	/**
	 * Gets a list of events
	 *
	 * @return array
	 */
	private function getEvents() {

		$v = $this->getVcalendar();

		$v->setProperty('calscale', 'GREGORIAN');
		$v->setProperty('method', 'PUBLISH');
		$v->setProperty('X-WR-TIMEZONE', Cfg::get('date_timezone'));
		$v->parse();
		$v->sort();

		$output = $v->selectComponents(date('Y'), 01, 01, date('Y')+1, 12, 31);

		return $output;
	}

	/**
	 * Figure out start/end times omfg
	 *
	 * @param $year
	 * @param $month
	 * @param $day
	 * @param $dtArray
	 *
	 * @internal param $hour
	 * @internal param $min
	 * @return \Alaress_Date_Format
	 */
	private function decideTimes($year, $month, $day, $dtArray) {
		$elements = array(
			$year,
			str_pad($month, 2, '0', STR_PAD_LEFT),
			str_pad($day, 2, '0', STR_PAD_LEFT),
			'T',
			str_pad($dtArray['hour'], 2, '0', STR_PAD_LEFT),
			str_pad($dtArray['min'], 2, '0', STR_PAD_LEFT),
			'00Z'
		);

		$unixTime = strtotime(implode('', $elements));

		/* Temporary object
		 */
		$tempTime = new Alaress_Date_Format($unixTime, 'U');

		/* Check if a timezone was supplied
		 * If not, calculate manually -_-
		 */
		if (!isset($dtArray['tz'])) {
			$tempTime->addHours(-1 * date_offset_get(new DateTime())/3600);
		}

		return $tempTime;
	}

	/**
	 * Load events into db from file
	 */
	public function load() {
		$eventArray = $this->getAllEvents();

		$eventTypeList = $this->calendarHandler->getTypeList();

		foreach($eventArray as $year => $yearArray) {
			foreach($yearArray as $month => $monthArray) {
				foreach($monthArray as $day => $dailyEventsArray) {
					foreach($dailyEventsArray as $vevent) {

						/* if member of a recurrence set,
						 * returns array( 'x-current-dtstart', <DATE>)
						 * <DATE> = (string) date("Y-m-d [H:i:s][timezone/UTC offset]")
						 */
						$dtstart = $vevent->getProperty('dtstart');
						$dtend   = $vevent->getProperty('dtend');
						$allday  = !array_key_exists('hour', $dtstart);

						/* dtstart required, one occurence, (orig. start date)
						 */
						$summary = $vevent->getProperty('summary');
						//
						// Description: \n needs to be replaced by real linebreaks
						$description = strtr(
							$vevent->getProperty('description'),
							array('\n' => '<br />', '\N' => '<br />',)
						);
						$location = $vevent->getProperty('location');

						$start = $this->decideTimes($year, $month, $day, $dtstart);
						$end = $this->decideTimes($year, $month, $day, $dtend);

						// Match the category against event type
						$categoryList = explode(';', "{$vevent->getProperty('categories')}");
						$eventTypeId  = 1;
						foreach ($categoryList as $category) {
							foreach ($eventTypeList as $eventType) {
								if (strcasecmp($eventType['eventType'], $category) === 0) {
									$eventTypeId = $eventType['eventTypeID'];
									break;
								}
							}
						}

						$event = array(
							'owner' => $this->getCreator(),
                            'author_id' => $this->getUid(),
							'access' => 3,
							'groupList' => array_map(array('Scrub', 'text'), $this->getGroupList()),
							'campusList' => array_map(array('Scrub', 'text'), $this->getCampusList()),
							'type' => $eventTypeId,
							'note' => $summary,
							'detail' => $description,
							'location' => $location,
							'startTime' => $allday ? '' : $start->getDatabaseTime(),
							'endTime' => $allday ? '' : $end->getDatabaseTime(),
							'startDate' => $start->getDatabaseDate(),
							'endDate' => $end->getDatabaseDate(),
							'allday' => $allday,
						);

						if ($this->getCalendarHandler()->create($event)) {
							$output[] = array(
								'name' => $summary,
								'status' => 'Added',
								'error' => 'OK',
							);
						}
						else {
							$output[] = array(
								'name' => $summary,
								'status' => 'Not Added',
								'error' => 'Could not create event',
							);
						}
					}
				}
			}
		}

		$this->setOutput($output);
	}

	/* Getters/Setters
	 */

	/**
	 * Set file
	 *
	 * @param $file
	 */
	private function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	private function getFile() {
		return $this->file;
	}

	/**
	 * Set all events
	 *
	 * @param $allEvents
	 */
	private function setAllEvents($allEvents) {
		$this->allEvents = $allEvents;
	}

	/**
	 * Get all events
	 *
	 * @return array
	 */
	private function getAllEvents() {
		return $this->allEvents;
	}

	/**
	 * Set campus list
	 *
	 * @param $campusList
	 */
	private function setCampusList($campusList) {
		$this->campusList = $campusList;
	}

	/**
	 * Get campus list
	 *
	 * @return array
	 */
	private function getCampusList() {
		return $this->campusList;
	}

	/**
	 * Set group list
	 *
	 * @param $groupList
	 */
	private function setGroupList($groupList) {
		$this->groupList = $groupList;
	}

	/**
	 * Get group list
	 *
	 * @return array
	 */
	private function getGroupList() {
		return $this->groupList;
	}

	/**
	 * Set uid
	 *
	 * @param $uid
	 */
	private function setUid($uid) {
		$this->uid = $uid;
	}

	/**
	 * Get uid
	 *
	 * @return null
	 */
	private function getUid() {
		return $this->uid;
	}

    /**
     * Set creator
     *
     * @param $creator
     */
    private function setCreator($creator) {
        $this->creator = $creator;
    }

    /**
     * Get creator
     *
     * @return null
     */
    private function getCreator() {
        return $this->creator;
    }


	/**
	 * Set output
	 *
	 * @param $output
	 */
	private function setOutput($output) {
		$this->output = $output;
	}

	/**
	 * Get output
	 *
	 * @return array
	 */
	public function getOutput() {
		return $this->output;
	}

	/**
	 * Set calendar handler
	 *
	 * @param $calendarHandler
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

	/* Utility methods
	 */

	public function probe($data) {
		echo "<PRE>";
		print_r($data);
		echo "</PRE>";
	}

	public function probeDie($data) {
		$this->probe($data);
		die();
	}
}
