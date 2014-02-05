<?php
/**
 * EventLevelCollection
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\Calendar;


class EventLevelCollection extends \Schoolbox\Collection {

	/**
	 * Ram in hardcoded values
	 *
	 * @param array $groupList
	 * @param $campusList
	 */
	public function __construct($groupList, $campusList) {
		$this->add(new EventLevel(\EOTC\Calendar\EventMapper::ACCESS_PERSONAL, 'Personal'));

		if (is_array($groupList) && count($groupList)) {
			$this->add(new EventLevel(\EOTC\Calendar\EventMapper::ACCESS_GROUP, 'Group'));
		}

		if ($campusList->count()) {
			$this->add(new EventLevel(\EOTC\Calendar\EventMapper::ACCESS_CAMPUS, 'Campus'));
		}
	}
} 