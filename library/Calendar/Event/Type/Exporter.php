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

			$e = new vevent();
			$e->setProperty('categories', 'SCHOOL' );

			if (!$currentEvent->getAllday()) {
				$e->setProperty(
					'dtstart',
					$currentEvent->getStartTime()->getUsingFormat('Y'),
					$currentEvent->getStartTime()->getUsingFormat('m'),
					$currentEvent->getStartTime()->getUsingFormat('d'),
					$currentEvent->getStartTime()->getUsingFormat('H'),
					$currentEvent->getStartTime()->getUsingFormat('i'),
					$currentEvent->getStartTime()->getUsingFormat('s')
				);
				$e->setProperty(
					'dtend',
					$currentEvent->getEndTime()->getUsingFormat('Y'),
					$currentEvent->getEndTime()->getUsingFormat('m'),
					$currentEvent->getEndTime()->getUsingFormat('d'),
					$currentEvent->getEndTime()->getUsingFormat('H'),
					$currentEvent->getEndTime()->getUsingFormat('i'),
					$currentEvent->getEndTime()->getUsingFormat('s')
				);
			}
			else {
				$e->setProperty(
					'dtstart',
					$currentEvent->getStartTime()->getUsingFormat('Y'),
					$currentEvent->getStartTime()->getUsingFormat('m'),
					$currentEvent->getStartTime()->getUsingFormat('d'),
					false,
					false,
					false,
					false,
					array('VALUE' => 'DATE')
				);
				$e->setProperty(
					'dtend',
					$currentEvent->getEndTime()->getUsingFormat('Y'),
					$currentEvent->getEndTime()->getUsingFormat('m'),
					$currentEvent->getEndTime()->getUsingFormat('d'),
					false,
					false,
					false,
					false,
					array('VALUE' => 'DATE')
				);
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
