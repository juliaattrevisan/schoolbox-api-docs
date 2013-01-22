<?php
/**
 * Calendar_Event_Type_Exporter
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Type_Exporter extends Calendar_Event_Type_Renderer_Abstract {

	/**
	 * Render this event
	 *
	 * @param Calendar_Event_Type_Abstract $currentEvent
	 * @return \vevent
	 */
	public function visit($currentEvent) {

		/* Show visible events only, in export
		 */
		if ($this->isVisible($currentEvent)) {

			/* Manufacture month and day values
			 */
			$currentDay = strtotime($currentEvent->getStartTime()->getDatabase());
			$month = date('n', $currentDay);
			$day = date('j', $currentDay);

			$e = new vevent();
			$e->setProperty('categories', 'SCHOOL' );

			if ($currentEvent->getStartTime()->getUnixTimestamp()) {
				$startHour = floor($currentEvent->getStartTime()->getUnixTimestamp() / 60 / 60);
				$startMinute = ($currentEvent->getStartTime()->getUnixTimestamp() /60) % 60;

                if($currentEvent->getEndTime()->getUnixTimestamp()){
                    $duration = $currentEvent->getEndTime()->getUnixTimestamp() - $currentEvent->getStartTime()->getUnixTimestamp();
                    $durHour = floor($duration / 60 / 60);
                    $durMinute = ($duration /60) % 60;
                }
                else {
                    $durHour = 1;
                    $durMinute = 0;
                }

                $e->setProperty('dtstart', $currentEvent->getStartTime()->getUsingFormat('Y'), $month, $day, $startHour, $startMinute, array('VALUE' => 'DATE-TIME'));
				$e->setProperty('duration', 0, 0, $durHour, $durMinute );
			}
			else {
				$e->setProperty('dtstart', $currentEvent->getStartTime()->getUsingFormat('Y'), $month, $day, FALSE, FALSE, array('VALUE' => 'DATE'));
			}

			$e->setProperty('summary', $currentEvent->getNote());

			/* Return the event object so you can agglutinate it elsewhere
			 * A slight deviation from classical Visitor, but is ok
			 */
			return $e;
		}
	}

	/**
	 * If the Publish Date hasn't been reached yet, only show the events to Superusers
	 * in a different way so they can still edit/click them. Otherwise, they don't appear
	 * at all for everyone else
	 *
	 * @param $currentEvent
	 *
	 * @return bool
	 */
	private function isVisible($currentEvent) {
		$isFutureEvent = ($currentEvent->getPublishDate()->getUnixTimestamp() >= time());
		$isVisibleToUser = ($this->getGroupHandler()->isSuperuser() || $currentEvent->isOwner() || (is_numeric($currentEvent->getCategoryId()) && ($this->getGroupHandler()->getPermission($currentEvent->getCategoryId())) > READ_ACCESS));

		if ($isFutureEvent && !($isVisibleToUser)) {
			return false;
		}

		return true;
	}
}
