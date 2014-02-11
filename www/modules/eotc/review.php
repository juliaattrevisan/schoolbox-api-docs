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
$this->setBlock(TEMPLATE_content, 'event_list_group', 'event_list_group_block');
$this->setBlock('event_list_group', 'event', 'event_block');

$this->setVar(array(
	'event_list_group_block' => ''
));

$eventMapper = new \EOTC\EventMapper();
$allListGroups = $eventMapper->findAllByListGroups();

/* using foreach() won't increment the $position, which I use to get the name
 * of the collection currently being iterated
 */
for ($allListGroups->rewind(); $allListGroups->valid(); $allListGroups->next()) {
	$currentListGroup = $allListGroups->current();

	if ($currentListGroup->count()) {
		$this->setVar(array(
			'EVENT_LIST_GROUP_NAME' => $allListGroups->getCurrentName()
		));

		foreach ($currentListGroup as $currentEvent) {
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

		$this->parse('event_list_group_block', 'event_list_group', true);

		/* Clear children after parsing out the parent
		 */
		$this->setVar(array(
			'event_block' => ''
		));
	}
}
