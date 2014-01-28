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
	 * Add hardcoded values
	 */
	public function __construct() {
		$this->add(new EventLevel(1, 'Personal'));

		/* Only provide Group option if groups have been defined
		 */
		$groupHandler = new \Group();
		$groupList = $groupHandler->involvedInCategoryList($_SESSION['id'], true, WRITE_ACCESS);

		if (is_array($groupList) && count($groupList)) {
			$this->add(new EventLevel(2, 'Group'));
		}

		/* Only provide Campus options if campuses have been defined
		 */
		$calendarHandler = new \Calendar();
		$campusList = $calendarHandler->getCampusList();

		if (is_array($campusList) && count($campusList)) {
			$this->add(new EventLevel(3, 'Campus'));
		}
	}
} 