<?php
/**
 * Calendar_Api_EventType_Event_Vcal_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Event_Vcal_Default implements Calendar_Api_EventType_Interface {

	/**
	 * A list of luEventType records. You can assign a "colour scheme" to Vcal events
	 * using the luEventType table.
	 *
	 * @var array
	 */
	private $allLuEventTypes = array();

	/**
	 * Constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
		$eventHandler = new Calendar();

		$this->setAllLuEventTypes($eventHandler->getTypeList());
	}

	/**
	 * Associate a colour scheme with this event stream
	 *
	 * @param $id
	 * @return array
	 */
	private function findLuEventType($id) {
		foreach($this->getAllLuEventTypes() as $currentLuEventType) {
			if ($currentLuEventType['eventTypeID'] == $id) {
				return $currentLuEventType;
			}
		}

		return array();
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
		$collection = new Calendar_Event_Collection();

		$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(array(
			'event_type' => 'Event',
			'connector_class' => 'Vcal',
			'status' => 1
		));

		foreach($allConfigs as $currentConfig) {
			$decodedConfig = $currentConfig->decodeConfig();

			/* Load calendar from locker cache
			 */
			$cache = new Calendar_Api_Cache();
			$source = $cache->setVerbose(false)->init($decodedConfig->getUrl())->getStreamContents();

			/* Parse it all
			 */
			$allEvents = Sabre_VObject_Reader::read($source);

			foreach($allEvents->children as $currentEvent) {

				/* gloop returns a whole bunch of stuff all mixed up
				 *
				 * We only want to deal with Sabre_VObject_Component_VEvent objects
				 * as they seem to be the calendar items themselves
				 */
				if ($currentEvent instanceof Sabre_VObject_Component_VEvent) {

					$eventStartTimestamp = strtotime($currentEvent->DTSTART->value);
					$eventEndTimestamp = strtotime($currentEvent->DTEND->value);

					/* Only get events that fit within the range specified
					 */
					if (($eventStartTimestamp >= $startTimestamp) && ($eventEndTimestamp <= $endTimestamp)) {
						$metaDetails = $this->findLuEventType($decodedConfig->getLuEventType());

						$event = new Calendar_Event_Type_Day();

						$event->setAuthorName('');
						$event->setAuthorId(-1);
						$event->setEventId($currentEvent->UID->value);

						$event->setStartTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
						$event->setEndTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));

						$event->setType($metaDetails['eventTypeID']);
						$event->setNote($currentEvent->SUMMARY->value);
						$event->setAccess('');
						$event->setEventType($metaDetails['eventType']);
						$event->setColour($metaDetails['eventColour']);
						$event->setCampusList('');
						$event->setDetail($currentEvent->DESCRIPTION->value);
						$event->setLocation($currentEvent->LOCATION->value);
						$event->setPublishDate('');
						$event->setSid('');

						$event->setIsEditable(false);

						$event->setAttendance(0);
						$event->setCompleted(0);

						$collection->add($event);
					}
				}
			}
		}

		return $collection;
	}

	/* Getters/Setters
	 */

	/**
	 * Set allLuEventTypes
	 *
	 * @param array $allLuEventTypes
	 */
	private function setAllLuEventTypes($allLuEventTypes) {
		$this->allLuEventTypes = $allLuEventTypes;
	}

	/**
	 * Get allLuEventTypes
	 *
	 * @return array
	 */
	private function getAllLuEventTypes() {
		return $this->allLuEventTypes;
	}
}
