<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$this->setFile(TEMPLATE_content, 'modules/eotc/review.html');
$this->setBlock(TEMPLATE_content, 'event_status', 'event_status_block');
$this->setBlock('event_status', 'event', 'event_block');

$eventMapper = new \EOTC\EventMapper();
$allStatus = $eventMapper->findAll();

foreach ($allStatus as $currentStatus) {
	if ($currentStatus->count()) {
		foreach ($currentStatus as $currentEvent) {
			$this->setVar(array(
				'EVENT_ID' => $currentEvent->getId(),
				'EVENT_STATUS_NAME' => $currentEvent->getMetadata()->getStatus()->getName(),
				'EVENT_NOTE' => $currentEvent->getNote(),
				'EVENT_START_DATETIME' => $currentEvent->getStartDatetime()->getShort(),
				'EVENT_TEACHER_IN_CHARGE_ID' => $currentEvent->getTeacherInCharge()->getId(),
				'EVENT_TEACHER_IN_CHARGE_FULLNAME' => $currentEvent->getTeacherInCharge()->getFullname(),
				'EVENT_CREATED_AT' => $currentEvent->getMetadata()->getCreatedAt()->getShort(),
				'EVENT_STUDENTS' => $currentEvent->getStudentAttendees()->count(),
				'EVENT_RAMS_REQUIRED' => $currentEvent->getMetadata()->getRamsRequired() ? 'Y' : 'N',
			));

			$this->parse('event_block', 'event', true);
		}

		$this->parse('event_status_block', 'event_status', true);

		/* Clear children after parsing out the parent
		 */
		$this->setVar(array(
			'event_block' => ''
		));
	}
}
