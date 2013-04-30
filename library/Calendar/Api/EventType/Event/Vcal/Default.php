<?php
/**
 * Calendar_Api_EventType_Event_Vcal_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_EventType_Event_Vcal_Default extends Calendar_Api_EventType_Abstract {

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

		$group_handler = new Group();
		$is_superuser  = $group_handler->isSuperuser();

		foreach($allConfigs as $currentConfig) {
			$decodedConfig = $currentConfig->decodeConfig();

			/* Check permissions - if you can view these events according to the Permissions
			 * settings then accumulate the events. Otherwise ignore them.
			 * (Superusers can view all events, so skip permissions on those)
			 */
			if ($is_superuser || $decodedConfig->hasPermissions($_SESSION['campus'], $_SESSION['gid'])) {

				/* Load calendar from locker cache
				 */
				$cache = new Calendar_Api_Cache();
				$source = $cache->init($decodedConfig->getUrl())->getStreamContents();

				/* Parse it all
				 */
				try {
					$allEvents = Sabre\VObject\Reader::read($source);
				}
				catch(Exception $e) {

					/* Some google calendars don't follow sabre's expected format?
					echo $decodedConfig->getUrl();
					echo $e->getMessage();
					 */
				}

				if ($allEvents !== null) {
					foreach($allEvents->children as $currentEvent) {

						/* gloop returns a whole bunch of stuff all mixed up
						 *
						 * We only want to deal with Sabre\VObject\Component\VEvent objects
						 * as they seem to be the calendar items themselves
						 */
						if ($currentEvent instanceof Sabre\VObject\Component\VEvent) {
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
								$event->setCampusList(implode(',', $decodedConfig->getCampus()));
								$event->setDetail($currentEvent->DESCRIPTION->value);
								$event->setLocation($currentEvent->LOCATION->value);
								$event->setPublishDate('');
								$event->setSid('');

								$event->setIsEditable(false);

								$event->setAttendance(0);
								$event->setCompleted(0);

								/* Give the calendar_connector id so this event knows where it came from
								 * This allows you to create an event detail screen in Mobile
								 */
								$event->setConnectorId($this->getConnectorId());

								$collection->add($event);
							}
						}
					}
				}
			}
		}

		return $collection;
	}

	/**
	 * Find a particular event from this source
	 *
	 * Do everything the same as usual, but only stack up one event (if you find it) and
	 * return that instead of a collection of everything
	 *
	 * @param $eventId
	 * @return Collection
	 */
	public function getEvent($eventId) {
		$collection = new Calendar_Event_Collection();

		$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(array(
			'event_type' => 'Event',
			'connector_class' => 'Vcal',
			'status' => 1
		));

		foreach($allConfigs as $currentConfig) {
			$decodedConfig = $currentConfig->decodeConfig();

			/* Check permissions - if you can view these events according to the Permissions
			 * settings then accumulate the events. Otherwise ignore them.
			 */
			if ($decodedConfig->hasPermissions($_SESSION['campus'], $_SESSION['gid'])) {

				/* Load calendar from locker cache
				 */
				$cache = new Calendar_Api_Cache();
				$source = $cache->init($decodedConfig->getUrl())->getStreamContents();

				/* Parse it all
				 */
				try {
					$allEvents = Sabre_VObject_Reader::read($source);
				}
				catch(Exception $e) {

					/* Some google calendars don't follow sabre's expected format?
					echo $decodedConfig->getUrl();
					echo $e->getMessage();
					 */
				}

				if ($allEvents !== null) {
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
							if ($eventId == $currentEvent->UID->value) {
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

								/* Give the calendar_connector id so this event knows where it came from
								 * This allows you to create an event detail screen in Mobile
								 */
								$event->setConnectorId($this->getConnectorId());

								$collection->add($event);

								/* Found the event, so exit
								 */
								return $collection;
							}
						}
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
