<?php
/**
 * EventLevelCollection
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Calendar;


class EventLevelCollection extends \Schoolbox\Collection {

	/**
	 * Ram in hardcoded values
	 *
	 * @param array $groupList
	 * @param $campusList
	 */
	public function __construct($groupList, $campusList) {
		$this->add(new EventLevel(\Schoolbox\Calendar\Event::ACCESS_PERSONAL, 'Personal'));

		if (is_array($groupList) && count($groupList)) {
			$this->add(new EventLevel(\Schoolbox\Calendar\Event::ACCESS_GROUP, 'Group'));
		}

		if ($campusList->count()) {
			$this->add(new EventLevel(\Schoolbox\Calendar\Event::ACCESS_CAMPUS, 'Campus'));
		}
	}
} 