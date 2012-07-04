<?php
/**
 * Calendar_Event_Day
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Day extends Calendar_Event_Abstract {

	/**
	 * Event visibility; generates a CSS string
	 *
	 * @return string
	 */
	public function getDerivedEventLevel() {
		$eventLevel = '';

		if ($this->isCampusAccess()) {
			if (stristr($this->getCampusList(), ',')) {
				foreach (explode(',', $this->getCampusList()) as $campus) {
					$eventLevel .= ' campus' . $campus;
				}
			}
			else {
				$eventLevel .= ' campus' . $this->getCampusList();
			}
		}
		else if ($this->isGroupAccess()) {
			$eventLevel = 'group' . $this->getSid();
		}
		else {
			$eventLevel = 'personal';
		}

		return $eventLevel;
	}

	/**
	 * Some cosmetic derivation. If the start and end time are both 12:00AM,
	 * this will return an empty string. Oddly I wonder if it's possible that
	 * the start time is 12:00AM and the end time is something different.
	 *
	 * @return string
	 */
	public function getDerivedTime() {
		$formattedTime = rtrim(
			($this->getStartTime() != "12:00AM" ? $this->getStartTime() : "") . " - " .
			($this->getEndTime() != "12:00AM" ? $this->getEndTime() : ""), "- ");

		return $formattedTime;
	}
}
