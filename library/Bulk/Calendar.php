<?php
/**
 * Bulk_Calendar
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Bulk_Calendar extends Bulk_Abstract {

	/**
	 * Wrapper for uploading a calendar file which could be in either format
	 *
	 * @return array
	 */
	public function addCalendar() {
		if (is_file($this->getFile())) {
            /* Calendar Owner override
             */
            if (isset($_POST['userid']) && is_numeric($_POST['userid'])) {
                $userMapper = new \Schoolbox\UserMapper();
                $user = $userMapper->findById($_POST['userid']);
                $author = $user;
            }
            else {
                $user = $_SESSION['user'];
                $author = $_SESSION['user'];
            }

			/* Set up specified campus list
			 * Set up specified group list
			 * Set up specified category list
			 */
			$groupList = array();
			$campusList = array();
			$categoryList = array();

			if (is_array($_POST['userGroup'])) {
				$groupList = $_POST['userGroup'];
			}

			if (is_array($_POST['campusList'])) {
				$campusList = $_POST['campusList'];
			}

			if (is_array($_POST['categoryList'])) {
				$categoryList = $_POST['categoryList'];
			}

			/* Use factory to spawn a builder
			 */
			$factory = new \Calendar\Import\Factory($user->getId(), $author->getId(), $categoryList, $groupList, $campusList, \Schoolbox\Calendar\Event::ACCESS_CAMPUS, $this->getFile());
			$strategy = $factory->build();
			$strategy->load();

			return $strategy->getOutput();
		}

		return array();
	}
}
