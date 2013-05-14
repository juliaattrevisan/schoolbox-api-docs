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
        $now = new Alaress_Date_Format(new DateTime(), 'U');
        $start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:00', 'y-m-d h:i:s');
        $end = new Alaress_Date_Format($now->getDatabaseDate() . ' 23:59:59', 'y-m-d h:i:s');

		$startDate = $start->getDatabase();
		$endDate = $end->getDatabase();

        $xmlPayload = new Calendar_Api_EventType_Event_Sharepoint_Stk_Payload($startDate, $endDate);

		/* Fill in your query result set here and populate $collection
		 */
		$collection = new Calendar_Event_Collection();

		/* If the SOAP connection succeeded, proceed normally
		 * Otherwise return an empty collection
		 */
		if (!$this->hasError()) {
			$response = $this->getSoap()->GetListItems(new SoapVar($xmlPayload->getPayload(), XSD_ANYXML));

			$allEvents = $xmlPayload->parseXml($response->GetListItemsResult->any);


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

				/* Event inspection
				 * A nice place to see what's currently being examined
				 */
	//			if ((string)$attribs->ows_Title == 'AKSS Junior Boys Volleyball Championships') {
	//				new Alaress_Probe($currentEvent);
	//				new Alaress_Probe($now->getDatabaseDate());
	//			}

				/* Simulate all-day event if appropriate since Sharepoint thinks the end of the
				 * day is at 11:59pm, whereas Schoolbox makes it 12:00am. "Mangle it", he said.
				 */
				$startDate = new Alaress_Date_Format((string)$attribs->ows_EventDate, 'y-m-d h:i:s');

				$event->setStartTime($startDate);

				if ((string)$attribs->ows_fAllDayEvent == 1) {
					$endDate = substr((string)$attribs->ows_EndDate, 0, 10);

					$event->setEndTime(new Alaress_Date_Format($endDate . ' 00:00:00', 'y-m-d h:i:s'));
				}
				else {
					$event->setEndTime(new Alaress_Date_Format((string)$attribs->ows_EndDate, 'y-m-d h:i:s'));
				}

				/* If it is recurring today, set the event's start date to Today.
				 * Sharepoint only knows the start and end PERIOD of a recurring
				 * event, and does not specify the exact date when a recurrence
				 * takes place.
				 *
					[ows_EventDate] => 2012-09-14 09:15:00
					[ows_EndDate] => 2012-12-21 09:30:00
					[ows_RecurrenceData] => mo2012-12-20T20:30:00Z
				 */
				if ($event->getEndTime()->getDatabaseDate() >= $now->getDatabaseDate()) {
					$startTime = new Alaress_Date_Format((string)$attribs->ows_EventDate, 'y-m-d h:i:s');
					$event->setStartTime(new Alaress_Date_Format($now->getDatabaseDate() . ' ' . $startTime->getDatabaseTime(), 'y-m-d h:i:s'));
				}
				/* Sometimes events come out that shouldn't so we manually exclude them
				 * due to Sharepoint timezones
				 */
				else {
					continue;
				}

				$collection->add($event);
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
