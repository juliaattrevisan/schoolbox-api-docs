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
	 * @param \Schoolbox\User $user
	 * @param $config
	 */
	public function __construct(\Schoolbox\User $user, $config) {
		$this->setUser($user);

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

		foreach ($allConfigs as $currentConfig) {
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
							//
							// All day event: starts at 12am one day, ends 12am
							// next.
							// (are either value: date)
							$all_day = $currentEvent->DTSTART['value']->value === 'DATE';
							$eventStartTimestamp = strtotime($this->escapeVcalValue($currentEvent->DTSTART->value));
							$eventEndTimestamp   = strtotime($this->escapeVcalValue($currentEvent->DTEND->value));

							//
							// According to VCAL standard: end of all day event
							// is 12am next day
							if ($all_day) {
								$eventEndTimestamp -= 1;
							}

							/* Only get events that overlap the range specified
							 * Event's end time is before start time of span: ignore
							 * Event's start time is before end time of span: ignore
							 */
							if (!($eventEndTimestamp < $startTimestamp || $eventStartTimestamp >= $endTimestamp)) {

								// Get category list for this event.
								// If this event has a category which matches
								// any event type, then apply that event type's
								// color instead of the configured one.
								$category_list = explode(';', $currentEvent->CATEGORIES->value);
								$category_list = array_map('trim',       $category_list);
								$category_list = array_map('strtoupper', $category_list);
								$category_list = array_filter($category_list, 'strlen');
								$metaDetails   = null;
								foreach ($category_list as $category_name) {
									foreach ($this->getAllLuEventTypes() as $event_type) {
										if (strtoupper($event_type['eventType']) === $category_name) {
											$metaDetails = $event_type;
											break;
										}
									}
									if (!empty($metaDetails)) {
										break;
									}
								}
								if (empty($metaDetails)) {
									$metaDetails = $this->findLuEventType($decodedConfig->getLuEventType());
								}

								$event = new Calendar_Event_Type_Day();

								$event->setAuthorName('');
								$event->setAuthorId(-1);
								$event->setEventId($this->escapeVcalValue($currentEvent->UID->value));

								$event->setStartTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
								$event->setEndTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));

								$event->setType($metaDetails['eventTypeID']);
								$event->setNote($this->escapeVcalValue($currentEvent->SUMMARY->value));
								$event->setAccess('');
								$event->setEventType($metaDetails['eventType']);
								$event->setColour($metaDetails['eventColour']);
								$event->setCampusList(implode(',', $decodedConfig->getCampus()));
								$event->setDetail(nl2br($this->escapeVcalValue($currentEvent->DESCRIPTION->value)));
								$event->setLocation($this->escapeVcalValue($currentEvent->LOCATION->value));
								$event->setPublishDate('');
								$event->setSid('');
								$event->setAllday($all_day);

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
	 * @return Calendar_Event_Collection
	 */
	public function getEvent($eventId) {
		$collection = new Calendar_Event_Collection();

		$allConfigs = Calendar_Api_Config_Mapper::getInstance()->findByColumnList(array(
			'event_type' => 'Event',
			'connector_class' => 'Vcal',
			'status' => 1
		));

		foreach ($allConfigs as $currentConfig) {
			/* @var $currentConfig Calendar_Api_Config_Record */
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
						 * We only want to deal with Sabre_VObject_Component_VEvent objects
						 * as they seem to be the calendar items themselves
						 */
						if ($currentEvent instanceof Sabre\VObject\Component\VEvent) {
							$eventStartTimestamp = strtotime($this->escapeVcalValue($currentEvent->DTSTART->value));
							$eventEndTimestamp = strtotime($this->escapeVcalValue($currentEvent->DTEND->value));

							/* Only get events that fit within the range specified
							 */
							if ($eventId == $this->escapeVcalValue($currentEvent->UID->value)) {
								$metaDetails = $this->findLuEventType($decodedConfig->getLuEventType());

								$event = new Calendar_Event_Type_Day();

								$event->setAuthorName('');
								$event->setAuthorId(-1);
								$event->setEventId($this->escapeVcalValue($currentEvent->UID->value));

								$event->setStartTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
								$event->setEndTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));

								$event->setType($metaDetails['eventTypeID']);
								$event->setNote($this->escapeVcalValue($currentEvent->SUMMARY->value));
								$event->setAccess('');
								$event->setEventType($metaDetails['eventType']);
								$event->setColour($metaDetails['eventColour']);
								$event->setCampusList('');
								$event->setDetail($this->escapeVcalValue($currentEvent->DESCRIPTION->value));
								$event->setLocation($this->escapeVcalValue($currentEvent->LOCATION->value));
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

	/**
	 * Patch for sabre/vobject 2 not dealing with escaped ; and , characters.
	 *
	 * @todo Remove this method and all usages when upgrading to sabre/vobject 3
	 *
	 * @param  string $value
	 * @return string
	 */
	protected function escapeVcalValue($value) {
		return strtr($value, array('\,' => ',', '\;' => ';',));
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
