<?php
/**
 * My Review
 *
 * A list of all EOTC events that $sessionUser created
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if ($sessionUser->getRole()->isStaff() || $sessionUser->isSuperuser()) {
	$this->setFile(TEMPLATE_content, 'modules/eotc/myReview.html');
	$this->setBlock(TEMPLATE_content, 'event', 'event_block');

	$this->setVar(array(
		'event_block' => ''
	));

	$eventMapper = \EOTC\EventMapper::getInstance();
	$allEvents = $eventMapper->findAllByListGroupsForUser($sessionUser);

	/* using foreach() won't increment the $position, which I use to get the name
	 * of the collection currently being iterated
	 */
	foreach($allEvents as $currentEvent) {
		$this->setVar(array(
			'EVENT_ID' => $currentEvent->getId(),
			'EVENT_STATUS_NAME' => $currentEvent->getMetadata()->getStatus()->getName(),
			'EVENT_TITLE' => $currentEvent->getTitle(),
			'EVENT_START_DATETIME' => $currentEvent->getStartDatetime()->getShort(),
			'EVENT_TEACHER_IN_CHARGE_ID' => $currentEvent->getTeacherInCharge()->getId(),
			'EVENT_TEACHER_IN_CHARGE_FULLNAME' => $currentEvent->getTeacherInCharge()->getFullname(),
			'EVENT_CREATED_AT' => $currentEvent->getMetadata()->getCreatedAt()->getShort(),
			'EVENT_STUDENTS' => $currentEvent->getMetadata()->getStudentAttendees()->count(),
			'EVENT_RAMS_REQUIRED' => $currentEvent->getMetadata()->getRamsRequired() ? 'Y' : 'N',
		));

		$this->parse('event_block', 'event', true);
	}
}
else {
	error(19);
}