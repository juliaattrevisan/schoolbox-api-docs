<?php
/**
 * Render the javascript-based Fullcalendar implementation
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
$user_mapper = new \Schoolbox\UserMapper();

/* If both args were provided, assume date and userId respectively
 * Otherwise just assume currently logged in user
 */

if (count($this->arg) == 2) {
	$selectedDate = $this->arg[0];

	/* Check if you are a superuser, a teacher, or a parent of the
	 * person's calendar; or if you are viewing your own calendar.
	 * If you aren't, just error out as usual.
	 */
	if ($_SESSION['user']->getId() == $this->arg[1]) {
		$user = $_SESSION['user'];
	}
	else {
		try {
			$user = $user_mapper->findById($this->arg[1]);
		}
		catch (Exception $e) {
			error(31);
		}

		$groupHandler = new Group();

		if (!$_SESSION['user']->getSuperuser() && !$groupHandler->isStaff() && !$groupHandler->isParentOfChild($this->arg[1])) {
			error(125);
		}
	}
}
else {
	$selectedDate = $this->arg[0];
	$user = $_SESSION['user'];
}

$calendar = new Calendar_Layout_Full_Javascript($this, $user, $selectedDate);
$calendar->render();

