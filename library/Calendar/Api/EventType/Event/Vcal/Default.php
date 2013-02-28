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
	 * Constructor
	 *
	 * @param $config
	 */
	public function __construct($config) {
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
			'connector_class' => 'Vcal'
		));

		//http://dc01.alrs/owa/calendar/ade01b2b2d6143578d38c4bb45f2b71e@demo2008r2.alrs/6bb887da9d854711b8b015a5bdd73c1929569147020194918/calendar.ics

		for($allConfigs->rewind(), $currentConfig = $allConfigs->current(); $allConfigs->valid(); $allConfigs->next()) {
			$source = file_get_contents($currentConfig->decodeConfig()->getUrl());

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
						$event = new Calendar_Event_Type_Day();

						$event->setAuthorName('');
						$event->setAuthorId(-1);
						$event->setEventId($currentEvent->UID->value);

						$event->setStartTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
						$event->setEndTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));

						$event->setType('');
						$event->setNote($currentEvent->SUMMARY->value);
						$event->setAccess('');
						$event->setEventType('Day');
						$event->setColour('');
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
}
