<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if ($_SESSION['user']->getRole()->isStaff() || $_SESSION['user']->isSuperuser()) {
	$eventMapper = new \EOTC\EventMapper();

	try {
		$event = $eventMapper->findById($_POST['eventId']);
	}
	catch (\Exception $e) {
		error(160);
	}

	if (is_array($_POST['selected']) && count($_POST['selected'])) {
		$eventMapper->addStudentAttendeesRelationsSpecific($event, $_POST['selected']);
	}

	$this->localRedir('/modules/eotc/manage/attendance/list/' . $event->getId());
}
else {
	error(19);
}