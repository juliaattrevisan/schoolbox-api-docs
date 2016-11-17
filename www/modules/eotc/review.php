<?php
/**
 * Review
 *
 * Show all the EOTC events to an EOTC Administrator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use \EOTC\EventStatusMapper;
use \EOTC\EventMapper;

/* Only EOTC Administrators can view this page
 */
$authenticator = new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

if (!$authenticator->isEOTCAdministrator($sessionUser)) {
	error(19);
}

$this->setFile(TEMPLATE_content, 'modules/eotc/review.html');
$this->setBlock(TEMPLATE_content, 'event_list_group', 'event_list_group_block');
$this->setBlock('event_list_group', 'event', 'event_block');

$this->setVar(array(
	'event_list_group_block' => ''
));

/** @var \EOTC\ListGroup\Collection $allListGroups */
$allListGroups = EventMapper::getInstance()->findAllByListGroups([
	EventStatusMapper::STATUS_DRAFT,
	EventStatusMapper::STATUS_INPRINCIPLE,
	EventStatusMapper::STATUS_DETAILSCOMPLETED,
	EventStatusMapper::STATUS_READYFORFINALAPPROVAL,
	EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL,
], EventMapper::INTERVAL_MAX_6MONTH_OLD);

foreach ($allListGroups as $key => $currentListGroup) {
	if ($currentListGroup->count()) {
		$this->setVar(array(
			'EVENT_LIST_GROUP_NAME' => $allListGroups->getCurrentName($key)
		));

		$i = 0;

		/** @var \EOTC\Event $currentEvent */
		foreach ($currentListGroup as $currentEvent) {

			/* Only list events you can manipulate
			 */
			if ($authenticator->isEOTCAdministrator($sessionUser, $currentEvent)) {
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
