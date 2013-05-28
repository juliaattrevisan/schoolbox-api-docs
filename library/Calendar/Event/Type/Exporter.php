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

			$start_time = $currentEvent->getStartTime()->getDateTimeObject();
			if (!($start_time instanceof DateTime)) {
				$start_time = new DateTime($currentEvent->getStartTime()->getDatabase());
			}
			$end_time = $currentEvent->getEndTime()->getDateTimeObject();
			if (!($end_time instanceof DateTime)) {
				$end_time = new DateTime($currentEvent->getEndTime()->getDatabase());
			}
			if (!$currentEvent->getAllday()) {

				//
				// Assume that start/end are set up with current timezone.
				// Convert them to UTC.
				$utc_timezone = new DateTimeZone('UTC');
				$start_time->setTimezone($utc_timezone);
				$end_time->setTimezone($utc_timezone);
				$e->setProperty(
					'dtstart',
					$start_time->format('Y'),
					$start_time->format('m'),
					$start_time->format('d'),
					$start_time->format('H'),
					$start_time->format('i'),
					$start_time->format('s'),
					'Z'
				);
				$e->setProperty(
					'dtend',
					$end_time->format('Y'),
					$end_time->format('m'),
					$end_time->format('d'),
					$end_time->format('H'),
					$end_time->format('i'),
					$end_time->format('s'),
					'Z'
				);
			}
			else {
				$e->setProperty(
					'dtstart',
					$start_time->format('Y'),
					$start_time->format('m'),
					$start_time->format('d'),
					false,
					false,
					false,
					false,
					array('VALUE' => 'DATE')
				);
				//
				// Schoolbox stores all day end time as the same day.
				// VCAL expects it as start of next, so add a day to SB value.
				$end_time->add(new DateInterval('P1D'));
				$e->setProperty(
					'dtend',
					$end_time->format('Y'),
					$end_time->format('m'),
					$end_time->format('d'),
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
