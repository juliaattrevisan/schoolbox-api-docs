<?php
/**
 * Calendar_Api_Sharepoint_Stk
 *
 * St. Kentigern uses iport, a customisation of Sharepoint.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Sharepoint_Stk implements Calendar_Api_Interface {

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
		'login' => 'alaress',
		'password' => 'Tjs782',
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
        'Arts'             => 1,
        'Assembly'         => 2,
        'Chapel'           => 2,
        'ETT'              => 2,
        'Assessment'       => 3,
        'Meeting P'        => 4,
        'Meeting S'        => 5,
        'Administration'   => 7,
        'Deadline'         => 7,
        'Photos'           => 7,
        'Other'            => 7,
        'Sport'            => 8,
        'EOTC'             => 11,
        'Event'            => 9,
    );

    /* Contains all event types for reference during mapping */
    private $typeList = array();


    /**
	 * Constructor
	 */
	public function __construct() {

		/* Create soap client
		 */
		try {
			$soapClient = new SoapClient($this->getWsdl(), $this->getCredentials());
		}
		catch (SoapFault $fault) {
			var_dump($fault);
			die();
		}

		$this->setSoap($soapClient);

        $this->setCalendarHandler(new Calendar());
        $this->setEventTypes($this->getCalendarHandler()->getTypeList());
	}

	/**
	 * Get day events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getDayEvents($currentDay) {
		$now = new Alaress_Date_Format($currentDay, 'U');

		$start = new Alaress_Date_Format($now->getDatabaseDate() . ' 00:00:01', 'y-m-d h:i:s');
		$end = new Alaress_Date_Format($now->getDatabaseDate() . ' 23:59:59', 'y-m-d h:i:s');

		$startDate = $start->getDatabase();
		$endDate = $end->getDatabase();

		$xmlPayload = new Calendar_Api_Sharepoint_Stk_Payload($startDate, $endDate);

		$response = $this->getSoap()->GetListItems(new SoapVar($xmlPayload->getPayload(), XSD_ANYXML));

		$allEvents = $xmlPayload->parseXml($response->GetListItemsResult->any);

		/* Fill in your query result set here and populate $collection
		 */
		$collection = new Calendar_Event_Collection();

		foreach($allEvents->rsdata->zrow as $currentEvent) {
			$attribs = $currentEvent->attributes();

            $eventTypeID = $this->getTypeIdFromName((string)$attribs->ows_ContentType);

			$event = new Calendar_Event_Type_Day();
			$event->setAuthorName('');
			$event->setAuthorId('');
			$event->setEventId(1);
			$event->setStartTime(new Alaress_Date_Format((string)$attribs->ows_EventDate, 'y-m-d h:i:s'));
			$event->setEndTime(new Alaress_Date_Format((string)$attribs->ows_EndDate, 'y-m-d h:i:s'));
			$event->setType($eventTypeID);
			$event->setNote((string)$attribs->ows_Title);
			$event->setLocation((string)$attribs->ows_School);
			$event->setAccess(Calendar_Event_Type_Abstract::CAMPUS_ACCESS);
			$event->setCampusList($this->getCampusIdFromName((string)$attribs->ows_School));
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

			$collection->add($event);
		}

		return $collection;
	}

	/**
	 * Get student birthdays from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getStudentBirthdays($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
	}

	/**
	 * Get submission events from this data source
	 *
	 * @param $currentDay
	 *
	 * @return Calendar_Event_Collection
	 */
	public function getSubmissionEvents($currentDay) {
		$collection = new Calendar_Event_Collection();

		/* Fill in your query result set here and populate $collection
		 */

		return $collection;
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
	 * @return null
	 */
	private function getSoap() {
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
        foreach($this->typeList as $type) {
            if($type['eventTypeID'] == $eventTypeID) {
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
}
