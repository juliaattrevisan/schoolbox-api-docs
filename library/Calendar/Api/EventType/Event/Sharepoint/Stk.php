<?php
/**
 * Calendar_Api_EventType_Event_Sharepoint_Stk
 *
 * St. Kentigern uses iport, a customisation of Sharepoint.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_Api_EventType_Event_Sharepoint_Stk extends Calendar_Api_EventType_Abstract {

    /* An instance of the built-in php5 soap
     */
    private $soap = null;

    /* The place where we found the wsdl file
     * Note: Sharepoint queries are sent here too, otherwise it won't work!
     */
    private $wsdl = 'http://iportexternal/_vti_bin/Lists.asmx?wsdl';

    /* STK credentials
     * User account to authenticate with. (Must have read/write/edit permissions to given Lists)
     */
    private $credentials = array(
        'login' => 'sk-sb-iport',
        'password' => 'j7tdPyZ1Zc',
        'authentication' => SOAP_AUTHENTICATION_BASIC,
        'trace' => true,
    );

    /* A campus name-id relation from the luCampus table.
     */
    private $campusList = array(
        'SKC' => 1,
        'SKG' => 2,
        'SKB' => 3,
        'SKP' => 4,
    );

    /* Event type mapping from the luEventType table.
     */
    private $typeMap = array(
        'Arts' => 1,
        'Assembly' => 2,
        'Chapel' => 2,
        'ETT' => 2,
        'Assessment' => 3,
        'Meeting P' => 4,
        'Meeting S' => 5,
        'Administration' => 7,
        'Deadline' => 7,
        'Photos' => 7,
        'Other' => 7,
        'Sport' => 8,
        'EOTC' => 11,
        'Event' => 9,
        'Co-curricular' => 9,
        'SKOCA' => 10,
        'Curriculum' => 11,
    );

    /* List of event types parents can see */
    private $parentAllowedTypes = array(1, 3, 4, 8, 9, 10, 11);

    /* List of event types students can see */
    private $studentAllowedTypes = array(1, 2, 3, 8, 9, 11);

    /* Contains all event types for reference during mapping */
    private $typeList = array();

	/**
	 * This maps the attributes for recurring events to a numerican day of the week
	 * to find out whether to display a recurring event on a particular day
	 *
	 * @var array
	 */
	private $weekMapping = array(
		'su' => 0,
		'mo' => 1,
		'tu' => 2,
		'we' => 3,
		'th' => 4,
		'fr' => 5,
		'sa' => 6
	);

	/**
	 * Whether the SOAP connection failed. If it did, silently proceed
	 *
	 * @var bool
	 */
	private $error = false;

    /**
     * Constructor
     */
	public function __construct($config) {

        /* Create soap client
         */
        try {
            $soapClient = new SoapClient($this->getWsdl(), $this->getCredentials());

            $this->setSoap($soapClient);

            $this->setCalendarHandler(new Calendar());
            $this->setEventTypes($this->getCalendarHandler()->getTypeList());
        }
		catch (SoapFault $fault) {
			$this->setError($fault);

            return false;
        }
    }

	/**
	 * Get day events from this data source
	 *
	 * @param $startTimestamp
	 * @param $endTimestamp
	 * @internal param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
    public function getEvents($startTimestamp, $endTimestamp) {
        $groupHandler = new Group();

		/* TODO: Update this for SK
		 */
        $now   = new Alaress_Date_Format(new DateTime(), 'U');
        $start = new Alaress_Date_Format($startTimestamp, 'U');
        $end   = new Alaress_Date_Format($endTimestamp, 'U');

		$startDate = $start->getUsingFormat('Y-m-d\\TH:i:s\\Z');
		$endDate   = $end->getUsingFormat('Y-m-d\\TH:i:s\\Z');

        $xmlPayload = new Calendar_Api_EventType_Event_Sharepoint_Stk_Payload($startDate, $endDate);
//	    new Alaress_Probe($xmlPayload->getPayload());

		/* Fill in your query result set here and populate $collection
		 */
		$collection = new Calendar_Event_Collection();

		/* If the SOAP connection succeeded, proceed normally
		 * Otherwise return an empty collection
		 */
		if (!$this->hasError()) {
			$response = $this->getSoap()->GetListItems(new SoapVar($xmlPayload->getPayload(), XSD_ANYXML));

			$allEvents = $xmlPayload->parseXml($response->GetListItemsResult->any);
//			new Alaress_Probe($allEvents);


			foreach ($allEvents->rsdata->zrow as $currentEvent) {
				$attribs = $currentEvent->attributes();

				$eventTypeID = $this->getTypeIdFromName((string)$attribs->ows_ContentType);
				$eventCampusID = $this->getCampusIdFromName((string)$attribs->ows_School);

				/* Hide events based on role and type of event */
				if ($groupHandler->isParent() && !in_array($eventTypeID, $this->parentAllowedTypes)) {
					continue;
				}

				if ($groupHandler->isStudent() && !in_array($eventTypeID, $this->studentAllowedTypes)) {
					continue;
				}

				/* Hide events that are not in the users campus list */
				if (!in_array($eventCampusID, $_SESSION['campus'])) {
					continue;
				}

				/* Find out if this is a recurring event and if it should be displayed
				 * on the currently viewed day. If not, ignore it and don't put it on
				 * the stack at all.
				 */
				if (!$this->isRecurringToday($now, $attribs)) {
					continue;
				}

				/* An event is meant to display today so let's add it to the stack
				 */
				$event = new Calendar_Event_Type_Day();
				$event->setAuthorName('');
				$event->setAuthorId('');
				$event->setEventId(1);
				$event->setType($eventTypeID);
				$event->setNote((string)$attribs->ows_Title);
				$event->setLocation((string)$attribs->ows_School);
				$event->setAccess(Calendar_Event_Type_Abstract::CAMPUS_ACCESS);
				$event->setCampusList($eventCampusID);
				$event->setEventType($this->getTypeNameByID($eventTypeID));
				$event->setColour($this->getCalendarHandler()->displayColour($eventTypeID));
				$event->setPublishDate((string)$attribs->ows_Created);
				$event->setSid($this->getSidFromName((string)$attribs->ows_School));
				$event->setCategoryId('');
				$event->setCategoryName('');
				$event->setAttendance('');

				/* Prevent modify link
				 */
				$event->setIsEditable(false);

				/* Give the calendar_connector id so this event knows where it came from
				 * This allows you to create an event detail screen in Mobile
				 */
				$event->setConnectorId($this->getConnectorId());

				$event->setAllday((string)$attribs->ows_fAllDayEvent == 1);

				$this->addEventsToCollection(
					$collection,
					$event,
					$attribs,
					$startTimestamp,
					$endTimestamp
				);
			}
		}

        return $collection;
    }

	/**
	 * Find a particular event from this source
	 *
	 * Doesn't do anything, probably doesn't matter. If it did matter, I'd
	 * probably create a new query to GetListItems, with the ID as the only
	 * parameter.
	 *
	 * @param $eventId
	 * @return Calendar_Event_Collection
	 */
	public function getEvent($eventId) {
		$collection = new Calendar_Event_Collection();
		return $collection;
	}


	/**
	 * Adds events based on recurrence
	 *
	 * @param Calendar_Event_Collection    $collection Collection to add to
	 * @param Calendar_Event_Type_Abstract $event      Event to add. If it recurs,
	 *                                                 clones of this are added
	 *                                                 for each recurrence.
	 * @param stdclass                     $attributes Attributes from the SOAP call
	 * @param int                          $from       Timestamp to add recurrences
	 *                                                 from.
	 * @param int                          $until      Timestamp to add recurrences
	 *                                                 until.
	 *
	 * @return void
	 */
	protected function addEventsToCollection(
		Calendar_Event_Collection $collection,
		Calendar_Event_Type_Abstract $event,
		$attributes,
		$from,
		$until
	) {
		$duration = (int)$attributes->ows_Duration;

		//
		// Only start and end time should be set here, everything else should be
		// set in getEvents()
		if ($attributes->ows_RecurrenceData) {
			//
			// recur weekly/daily/whatever
			$recurrence = simplexml_load_string((string)$attributes->ows_RecurrenceData, 'SimpleXMLElement', LIBXML_NOCDATA);
			$rule = $recurrence->rule;
//			var_dump($until);
			$until = Datetime::createFromFormat('U', $until);
//			var_dump($until);

			//
			// How long to repeat for?
			$window_start = Datetime::createFromFormat('U', $from);
			$window_end   = $until;
			if ($rule->windowEnd) {
				$temp_window_end = new DateTime((string)$rule->windowEnd);
				if ($temp_window_end < $window_end) {
					$window_end = $temp_window_end;
				}
			}

			//
			// How many instances? (-1 = until window end)
			$instances = -1;
			if ($rule->repeatInstances) {
				$instances = (int)$rule->repeatInstances;
			}

			//
			// If no instances, populate starting from $from: otherwise, from
			// event date

			$start_time = new DateTime((string)$attributes->ows_EventDate);
			if ($rule->repeat->daily) {
				//
				// Repeat on each day
				// When do we start?
				$days = max((int)$rule->repeat->daily->dayFrequency, 1);
				while ($instances != 0 && $start_time < $window_end) {
					$start_time->modify("+$days day");
					if ($start_time > $window_end) {
						break;
					}
					if ($start_time >= $window_start) {
						$this->addEventAtTime($collection, $event, $start_time, $duration);
						$instances--;
					}
				}
			}
			elseif ($rule->repeat->weekly) {
				$repeat_attributes = $rule->repeat->weekly->attributes();
				$weeks             = max((int)$repeat_attributes->weekFrequency, 1);
				$weekday_map       = $this->getWeekMapping();
				$days_into_week    = (int)$start_time->format('w');
				while ($instances != 0 && $start_time < $window_end) {
					//
					// Go through each weekday.
					// For each weekday, get the start time of the event on this
					// day by offsetting the start time back/forward to that
					// day of the week.
					foreach ($weekday_map as $day_code => $day_no) {
						$day_offset = ($day_no-$days_into_week);
						$day_start  = clone $start_time;
						$day_start->modify("$day_offset day");

						if ($day_start < $window_start) {
							continue;
						}
						if ($day_start > $window_end) {
							break;
						}
						if ($repeat_attributes->$day_code) {
							if ($day_start >= $window_start) {
								$this->addEventAtTime($collection, $event, $day_start, $duration);
								$instances--;
							}
						}
						if ($instances == 0) {
							break;
						}
					}
					$start_time->modify("+$weeks week");
				}
			}
		}
		else {
			//
			// Recur just once
			$this->addEventAtTime($collection, $event, new DateTime($attributes->ows_EventDate), $duration);
//			$event->setStartTime(new Alaress_Date_Format((string)$attributes->ows_EventDate, 'y-m-d h:i:s'));
//			$event->setEndTime(new Alaress_Date_Format((string)$attributes->ows_EndDate, 'y-m-d h:i:s'));
//			$collection->add($event);
		}

//		/* Simulate all-day event if appropriate since Sharepoint thinks the end of the
//		 * day is at 11:59pm, whereas Schoolbox makes it 12:00am. "Mangle it", he said.
//		 */
//		$startDate = new Alaress_Date_Format((string)$attributes->ows_EventDate, 'y-m-d h:i:s');
//
//		//
//		// Is there recurrence? If so, create multiple events
//		// (within $startTimestamp, $endTimestamp only)
//		$event->setStartTime($startDate);
//
//		if ((string)$attributes->ows_fAllDayEvent == 1) {
//			$endDate = substr((string)$attributes->ows_EndDate, 0, 10);
//			$event->setEndTime(new Alaress_Date_Format($endDate . ' 00:00:00', 'y-m-d h:i:s'));
//		}
//		else {
//			$event->setEndTime(new Alaress_Date_Format((string)$attributes->ows_EndDate, 'y-m-d h:i:s'));
//		}

//		/* If it is recurring today, set the event's start date to Today.
//		 * Sharepoint only knows the start and end PERIOD of a recurring
//		 * event, and does not specify the exact date when a recurrence
//		 * takes place.
//		 *
//			[ows_EventDate] => 2012-09-14 09:15:00
//			[ows_EndDate] => 2012-12-21 09:30:00
//			[ows_RecurrenceData] => mo2012-12-20T20:30:00Z
//		 */
//		if ($event->getEndTime()->getDatabaseDate() >= $now->getDatabaseDate()) {
//			$startTime = new Alaress_Date_Format((string)$attributes->ows_EventDate, 'y-m-d h:i:s');
//			$event->setStartTime(new Alaress_Date_Format($now->getDatabaseDate() . ' ' . $startTime->getDatabaseTime(), 'y-m-d h:i:s'));
//		}
//		/* Sometimes events come out that shouldn't so we manually exclude them
//		 * due to Sharepoint timezones
//		 */

	}

	/**
	 * Adds a single instance of an event.
	 *
	 * @param Calendar_Event_Collection    $collection Collection to add to
	 * @param Calendar_Event_Type_Abstract $event      Event to add a copy of.
	 * @param DateTime                     $start_time When the event starts
	 * @param int                          $duration   Number of seconds the
	 *                                                 event lasts.
	 */
	protected function addEventAtTime(
		Calendar_Event_Collection $collection,
		Calendar_Event_Type_Abstract $event,
		DateTime $start_time,
		$duration
	) {
		$new_event = clone $event;
		$new_event->setStartTime(new Alaress_Date_Format($start_time, 'U'));
		//
		// Need to offset end time too
		$end_time = clone $start_time;
		$end_time->modify("+$duration second");
		$new_event->setEndTime(new Alaress_Date_Format($end_time, 'U'));
		$collection->add($new_event);
	}

	/**
	 * Figure out if a particular event should recur today and thus be
	 * displayed in the calendar
	 *
	 * @param $now
	 * @param $attribs
	 * @return bool
	 */
	private function isRecurringToday($now, $attribs) {
		if ($attribs->ows_RecurrenceData) {
			$currentDayOfWeek = $now->getUsingFormat('w');

			$recurrenceXml = simplexml_load_string((string)$attribs->ows_RecurrenceData, 'SimpleXMLElement', LIBXML_NOCDATA);

			/* If it repeats weekly
			 */
			if ($recurrenceXml->rule->repeat->weekly) {
				$recurrenceAttribs = $recurrenceXml->rule->repeat->weekly->attributes();

				/* Find out this stupid attribute rubbish to figure out
				 * when an event recurs
				 */
				$index = -1;

				foreach($recurrenceAttribs as $key => $value) {
					$mapping = $this->getWeekMapping();

					if (array_key_exists($key, $mapping)) {
						$index = $mapping[$key];

						break;
					}
				}

				/* Forget this loop if the recurring event is not meant to
				 * display today
				 */
				if ($currentDayOfWeek != $index) {
					return false;
				}

				return true;
			}

			/* TODO: If it repeats daily
			 */
		}

		return true;
	}

    /**
     * Get the campus id from its initials as provided by Sharepoint.
     * Save some db calls by copying the luCampus table into this class.
     *
     * If "All" is provided, it gets all the campuses in the class var
     * except "All" (which obviously doesn't exist as a campus)
     *
     * @param $name
     * @return mixed
     */
    private function getCampusIdFromName($name) {
        if ($name == 'ALL') {
            $allCampuses = $this->getCampusList();

            /* Remove 'All'
             */
            array_pop($allCampuses);

            return implode(',', $allCampuses);
        }

        return $this->campusList[$name];
    }

    /**
     * Get content to use in the query for sid
     *
     * @param $name
     * @return mixed
     */
    private function getSidFromName($name) {
        if ($name == 'ALL') {
            $allCampuses = $this->getCampusList();

            return $allCampuses[0] . implode(' OR sid = ', $allCampuses);
        }

        return $this->campusList[$name];
    }

    /**
     * Used to map event types from STK to Schoolbox
     *
     * @param $name
     * @return mixed
     */
    private function getTypeIdFromName($name) {
        return $this->typeMap[$name];
    }

    /* Getters/Setters
      */

    /**
     * Set soap
     *
     * @param $soap
     */
    private function setSoap($soap) {
        $this->soap = $soap;
    }

    /**
     * Get soap
	 *
	 * This is actually set to protected for test class inheritance
     *
     * @return null
     */
    protected function getSoap() {
        return $this->soap;
    }

    /**
     * Get wsdl
     *
     * @return string
     */
    private function getWsdl() {
        return $this->wsdl;
    }

    /**
     * Get credentials
     *
     * @return array
     */
    private function getCredentials() {
        return $this->credentials;
    }

    /**
     * Set calendar handler
     *
     * @param $calendarHandler
     */
    public function setCalendarHandler($calendarHandler) {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * Get calendar handler
     *
     * @return null
     */
    public function getCalendarHandler() {
        return $this->calendarHandler;
    }

	/**
	 * Set event type list
	 *
	 * @array $eventTypes
	 *
	 * @param $eventTypes
	 */
    public function setEventTypes($eventTypes) {
        $this->typeList = $eventTypes;
    }

    /**
     * Get event type list based on ID
     *
     * @param $eventTypeID
     * @return string
     */
    public function getTypeNameByID($eventTypeID) {
        foreach ($this->typeList as $type) {
            if ($type['eventTypeID'] == $eventTypeID) {
                return $type['eventType'];
            }
        }
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
	 * Get week mapping
	 *
	 * @return array
	 */
	private function getWeekMapping() {
		return $this->weekMapping;
	}

	/**
	 * @param boolean $error
	 */
	private function setError($error) {
		$this->error = $error;
	}

	/**
	 * @return boolean
	 */
	private function hasError() {
		if ($this->error === false) {
			return false;
		}

		return true;
	}
}
