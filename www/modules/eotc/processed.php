<?php
/**
 * Processed
 *
 * Show approved/cancelled events that are at most 1 month old
 * -or- starting in the future
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Only EOTC Administrators can view this page
 */
$authenticator = new \EOTC\Authenticator();

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$authenticator->isEOTCAdministrator($sessionUser)) {
	error(19);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/processed.html');
$this->setBlock(TEMPLATE_content, 'event_list_group', 'event_list_group_block');
$this->setBlock('event_list_group', 'event', 'event_block');

$this->setVar(array(
	'event_list_group_block' => ''
));

$eventMapper = \EOTC\EventMapper::getInstance();

/** @var \EOTC\ListGroup\Collection $allListGroups */
$allListGroups = $eventMapper->findAllByListGroups([
	\EOTC\EventStatusMapper::STATUS_APPROVED,
	\EOTC\EventStatusMapper::STATUS_REJECTED
], \EOTC\EventMapper::INTERVAL_MAX_1MONTH_OLD_AND_FUTURE);

/* Using foreach() won't increment the $position, which I use to get the name
 * of the collection currently being iterated
 */
foreach ($allListGroups as $key => $currentListGroup) {
	if ($currentListGroup->count()) {
		$this->setVar(array(
			'EVENT_LIST_GROUP_NAME' => $allListGroups->getCurrentName($key)
		));

		$i = 0;

		/** @var \EOTC\Event $currentEvent */
		foreach ($currentListGroup as $currentEvent) {
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

			$i++;
		}

		/* Only parse if an event you can manipulate was found
		 */
		if ($i) {
			$this->parse('event_list_group_block', 'event_list_group', true);
		}

		/* Clear children after parsing out the parent
		 */
		$this->setVar(array(
			'event_block' => ''
		));
	}
}
