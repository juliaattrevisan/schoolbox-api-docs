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

		/* Indexes
		 */
		$description = 0;
		$name = 1;
		$dtstart = 2;
		$dtend = 3;
		$location = 11;

		//http://dc01.alrs/owa/calendar/ade01b2b2d6143578d38c4bb45f2b71e@demo2008r2.alrs/6bb887da9d854711b8b015a5bdd73c1929569147020194918/calendar.ics

		for($allConfigs->rewind(), $currentConfig = $allConfigs->current(); $allConfigs->valid(); $allConfigs->next()) {
			$source = file_get_contents($currentConfig->decodeConfig()->getUrl());

			$gloop = Sabre_VObject_Reader::read($source);

			foreach($gloop->children as $currentEvent) {
				if ($currentEvent instanceof Sabre_VObject_Component_VEvent) {
					$eventStartTimestamp = strtotime($currentEvent->children[$dtstart]->value);
					$eventEndTimestamp = strtotime($currentEvent->children[$dtend]->value);

					if (($eventStartTimestamp >= $startTimestamp) && ($eventEndTimestamp <= $endTimestamp)) {
						$event = new Calendar_Event_Type_Day();

						$event->setAuthorName('');
						$event->setAuthorId('');
						$event->setEventId(-1);

						$event->setStartTime(new Alaress_Date_Format($eventStartTimestamp, 'U'));
						$event->setEndTime(new Alaress_Date_Format($eventEndTimestamp, 'U'));

						$event->setType('');
						$event->setNote($currentEvent->children[$name]->value);
						$event->setAccess('');
						$event->setEventType('Day');
						$event->setColour('');
						$event->setCampusList('');
						$event->setDetail($currentEvent->children[$description]->value);
						$event->setLocation($currentEvent->children[$location]->value);
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
